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
require_once __DIR__ . '/../src/Intelligence/MarketAnalysis.php';
$insightsEngine = new InsightsEngine($pdo);
$marketAnalysis = new MarketAnalysis($pdo);

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
        require_once __DIR__ . '/../src/Intelligence/ChatMemory.php';
        $memory   = new ChatMemory($pdo);
        $userId   = (int) ($_SESSION['user_id'] ?? 0);
        $assistant = new Assistant($pdo);
        $question = $_POST['message'] ?? ($_GET['message'] ?? '');
        $sessionId = (int) ($_POST['session_id'] ?? $_GET['session_id'] ?? 0);
        if (trim($question) === '') {
            // Still create a session so the front-end has an anchor.
            if ($sessionId <= 0) $sessionId = $memory->createSession($userId);
            echo json_encode(['success' => true, 'data' => [
                'type' => 'error',
                'text' => 'Ask me something about your farm…',
                'cards' => [],
            ], 'session_id' => $sessionId]);
            exit;
        }
        if ($sessionId <= 0) {
            $sessionId = $memory->createSession($userId, $memory->makeTitle($question));
        } else {
            $own = $memory->history($sessionId, $userId);
            if (!$own) $sessionId = $memory->createSession($userId, $memory->makeTitle($question));
        }
        $memory->append($sessionId, $userId, 'user', $question);
        $reply = $assistant->answer($question);
        $memory->append($sessionId, $userId, 'assistant', $reply['text'], $reply['cards'] ?? []);
        echo json_encode(['success' => true, 'data' => $reply, 'session_id' => $sessionId]);
        exit;
    }

    if ($action === 'chat_sessions') {
        header('Content-Type: application/json; charset=UTF-8');
        require_once __DIR__ . '/../src/Intelligence/ChatMemory.php';
        $memory = new ChatMemory($pdo);
        echo json_encode(['success' => true, 'data' => [
            'sessions' => $memory->sessions((int) ($_SESSION['user_id'] ?? 0)),
            'active'   => (int) ($_GET['session_id'] ?? 0),
        ]]);
        exit;
    }

    if ($action === 'chat_history') {
        header('Content-Type: application/json; charset=UTF-8');
        require_once __DIR__ . '/../src/Intelligence/ChatMemory.php';
        $memory = new ChatMemory($pdo);
        $hist = $memory->history((int) ($_GET['session_id'] ?? 0), (int) ($_SESSION['user_id'] ?? 0));
        if (!$hist) {
            echo json_encode(['success' => false, 'error' => 'Session not found']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $hist]);
        exit;
    }

    // Market Analysis & Price Prediction (Predictive Analytics expansion).
    if ($action === 'market_json') {
        header('Content-Type: application/json; charset=UTF-8');
        $crop  = $_GET['crop'] ?? 'Tomato';
        $plant = max(1, min(12, (int) ($_GET['plant_month'] ?? 2)));
        echo json_encode([
            'success' => true,
            'data' => [
                'crops'      => $marketAnalysis->crops(),
                'crop'       => ucfirst((string) $crop),
                'plant_month'=> $plant,
                'history'    => $marketAnalysis->priceHistory($crop, 2),
                'report'     => $marketAnalysis->decisionReport($crop, $plant),
            ],
        ]);
        exit;
    }

    // Market Analysis landing page (interactive "what to plant" tool).
    if (($_GET['view'] ?? '') === 'market') {
        $viewFile = __DIR__ . "/views/market_analysis.php";
        if (file_exists($viewFile)) {
            $data = [];
            ob_start();
            require $viewFile;
            $content = ob_get_clean();
            require __DIR__ . "/views/layout.php";
            exit;
        }
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

// --- Phase 8: Labour Management -> full HR module (admin/manager only) ---
if ($module === 'Labour') {
    $labourAdminRoles = ['admin', 'manager'];
    if (!in_array($role, $labourAdminRoles, true)) {
        die("Access Denied: Labour/HR management is restricted to admin and manager roles.");
    }
    require_once __DIR__ . '/../src/Modules/Labour/LabourController.php';
    $labour = new LabourController($pdo);
    $sub = $_GET['subsection'] ?? null;

    // Live JSON: employee list (for the live-add table refresh) and live employee add.
    if ($action === 'emp_json') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => true, 'data' => $labour->hrNew()->employees(isset($_GET['dept']) ? (int) $_GET['dept'] : null)]);
        exit;
    }
    if ($action === 'emp_add') {
        header('Content-Type: application/json; charset=UTF-8');
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { echo json_encode(['success' => false, 'error' => 'Name is required.']); exit; }
        $id = $labour->hrNew()->addEmployee($_POST);
        echo json_encode($id ? ['success' => true, 'id' => $id] : ['success' => false, 'error' => 'Failed to add employee.']);
        exit;
    }

    // POST add for any sub-section -> redirect back to its view.
    if ($action === 'add' && $sub) {
        $res = $labour->handleAdd($sub);
        SessionHelper::setFlash($res === true ? 'success' : 'error', $res === true ? "Saved successfully." : (is_string($res) ? $res : "Failed."));
        header('Location: index.php?module=Labour&subsection=' . urlencode($sub));
        exit;
    }

    // Sub-actions (approve/reject leave, resolve grievance, mark payroll paid).
    if (in_array($action, ['approve', 'reject', 'resolve', 'paid'], true) && $sub) {
        $labour->handleAction($sub, $action);
        header('Location: index.php?module=Labour&subsection=' . urlencode($sub));
        exit;
    }

    // Sub-section view.
    if ($sub) {
        $subFile = __DIR__ . "/views/labour_{$sub}.php";
        if (file_exists($subFile)) {
            if (method_exists($labour, $sub . 'View')) {
                $data = $labour->{$sub . 'View'}();
            } else {
                $data = [];
            }
            ob_start();
            require $subFile;
            $content = ob_get_clean();
            require __DIR__ . "/views/layout.php";
            exit;
        }
    }

    // HR dashboard landing.
    $viewFile = __DIR__ . "/views/labour_landing.php";
    if (file_exists($viewFile)) {
        $data = $labour->dashboardView();
        $data['upcoming_leave'] = $labour->hrNew()->upcomingLeave(30);
        $data['pending_leave'] = $labour->hrNew()->leaveRequests(true);
        $data['recent_training'] = array_slice($labour->hrNew()->training(), 0, 5);
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