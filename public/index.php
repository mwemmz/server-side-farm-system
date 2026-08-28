<?php
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
require_once __DIR__ . '/../config/database.php';

// Run migrations + seeding automatically
require_once __DIR__ . '/../db/migrate.php';

$module = $_GET['module'] ?? 'Dashboard';
$action = $_GET['action'] ?? 'index';

// --- Authentication actions (login/logout) handled before the auth gate ---
if ($module === 'Security' && in_array($action, ['login', 'logout'], true)) {
    require_once __DIR__ . '/../src/Modules/Security/SecurityController.php';
    $controller = new SecurityController($pdo);

    if ($action === 'logout') {
        $controller->logout();
    }

    // login: controller returns [] on GET, ['error' => ...] on failed POST,
    // and redirects on successful POST.
    $loginData = $controller->login();
    $loginError = $loginData['error'] ?? null;

    $content = '';
    require __DIR__ . '/views/login.php';
    exit;
}

// --- Auth gate: every other page requires a logged-in user ---
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?module=Security&action=login');
    exit;
}

// --- Role-Based Access Control (RBAC) ---
$role = $_SESSION['role'] ?? 'worker';
$financeOnly = ['admin', 'accountant'];
if ($module === 'Finance' && !in_array($role, $financeOnly, true)) {
    die("Access Denied: Only admins can access the Finance module.");
}

$module = $_GET['module'] ?? 'Dashboard';
$action = $_GET['action'] ?? 'index';

// --- AI/BI (Insights + Assistant): JSON actions + the Insights feed page ---
require_once __DIR__ . '/../src/Intelligence/InsightsEngine.php';
require_once __DIR__ . '/../src/Intelligence/Assistant.php';
$insightsEngine = new InsightsEngine($pdo);

if ($module === 'Insights') {
    if ($action === 'recommendations_json') {
        header('Content-Type: application/json; charset=UTF-8');
        $filter = $_GET['module_filter'] ?? null;
        $json = ['success' => true, 'data' => [
            'recommendations' => $insightsEngine->all($filter),
            'stats'           => $insightsEngine->stats(),
        ]];
        echo json_encode($json);
        exit;
    }
    if ($action === 'chat') {
        header('Content-Type: application/json; charset=UTF-8');
        $assistant = new Assistant($pdo);
        $question = $_POST['message'] ?? ($_GET['message'] ?? '');
        $reply = $assistant->answer($question);
        echo json_encode(['success' => true, 'data' => $reply]);
        exit;
    }

    // Feed page.
    $viewFile = __DIR__ . "/views/insights_landing.php";
    if (file_exists($viewFile)) {
        $engine = $insightsEngine;
        $recs   = $engine->all();
        $stats  = $engine->stats();
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        require __DIR__ . "/views/layout.php";
        exit;
    }
}

if ($module === 'Dashboard') {
    $viewFile = __DIR__ . "/views/dashboard.php";
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require __DIR__ . "/views/layout.php";
    exit;
}

$controllerFile = __DIR__ . "/../src/Modules/{$module}/{$module}Controller.php";
$viewFile = __DIR__ . "/views/" . strtolower($module) . ".php";

// --- Phase 6: Control-panel modules (live dashboards) ---
$panelModules = ['Irrigation', 'Storage', 'Equipment', 'Livestock', 'Weather', 'Finance'];
if (in_array($module, $panelModules, true)) {
    // Live polling JSON endpoint (session-authenticated).
    if ($action === 'panel_json' || $action === 'panel_act') {
        require_once __DIR__ . '/../src/Intelligence/Panels/PanelJsonGateway.php';
        PanelJsonGateway::dispatch($pdo, $module, $_GET);
        exit;
    }

    // Module landing (tap) -> panel view, unless an explicit CRUD action is requested.
    $isCrudAction = in_array($action, ['add', 'edit', 'delete', 'manage'], true)
        || ($_SERVER['REQUEST_METHOD'] === 'POST');
    $panelView = __DIR__ . "/views/" . strtolower($module) . "_panel.php";

    if (!$isCrudAction && file_exists($panelView)) {
        require_once $controllerFile;
        $controllerName = "{$module}Controller";
        $controller = new $controllerName($pdo);
        $data = $controller->index();

        ob_start();
        require $panelView;
        $content = ob_get_clean();
        require __DIR__ . "/views/layout.php";
        exit;
    }
}

// --- Phase 6b: landings for form-first modules (CRUD demoted behind a button).
// A general 'manage' action renders each module's original CRUD view (form + list).
if ($action === 'manage' && file_exists($controllerFile) && file_exists($viewFile)) {
    require_once $controllerFile;
    $controllerName = "{$module}Controller";
    $controller = new $controllerName($pdo);
    $data = method_exists($controller, 'index') ? $controller->index() : [];
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require __DIR__ . "/views/layout.php";
    exit;
}

// Module tap ('index' on GET) lands on a relevant view: a bespoke
// {module}_landing.php when one exists (e.g. Farm/Field Leaflet maps),
// otherwise the shared generic landing. Panel modules already exited above.
if ($action === 'index' && $_SERVER['REQUEST_METHOD'] !== 'POST' && !in_array($module, $panelModules, true)) {
    $bespoke = __DIR__ . "/views/" . strtolower($module) . "_landing.php";
    $landing = file_exists($bespoke) ? $bespoke : __DIR__ . "/views/partials/generic_landing.php";
    if (file_exists($controllerFile)) {
        require_once $controllerFile;
        $controllerName = "{$module}Controller";
        $controller = new $controllerName($pdo);
        $data = method_exists($controller, 'index') ? $controller->index() : [];
        ob_start();
        require $landing;
        $content = ob_get_clean();
        require __DIR__ . "/views/layout.php";
        exit;
    }
}

if (file_exists($controllerFile) && file_exists($viewFile)) {
    require_once $controllerFile;
    $controllerName = "{$module}Controller";
    $controller = new $controllerName($pdo);

    if (method_exists($controller, $action)) {
        $data = $controller->$action();

        // Capture view content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Include layout
        require __DIR__ . "/views/layout.php";
    } else {
        http_response_code(404);
        echo "Action not found.";
    }
} else {
    http_response_code(404);
    echo "Module/View not found.";
}
?>