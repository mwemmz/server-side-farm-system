<?php
// Idle session timeout (30 min). Must be set before session_start so that
// session.gc_maxlifetime actually applies (ini_set after session_start warns).
$idleLimit = 1800;
ini_set('session.gc_maxlifetime', (string) $idleLimit);
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
require_once __DIR__ . '/../config/database.php';

// Run migrations + seeding automatically
require_once __DIR__ . '/../db/migrate.php';

$module = $_GET['module'] ?? 'Dashboard';
$action = $_GET['action'] ?? 'index';

// --- A1: Central CSRF protection for every state-changing request ---
require_once __DIR__ . '/../src/Helpers/SessionHelper.php';
$reqMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$wantsJson = (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
    || (strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest') === 0)
    || ($module === 'Lookup')
    || ($module === 'Insights')
    || ($module === 'Labour' && $action === 'emp_add');
$providedToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

// Actions that must never be reachable by a plain GET (no link-prefetch/CSRF-by-image).
$mustPostActions = ['delete', 'approve', 'reject', 'resolve', 'paid',
                    'mark_read', 'mark_all_read', 'run_rules', 'toggle_rule', 'delete_rule',
                    'panel_act'];
if (in_array($action, $mustPostActions, true) && $reqMethod !== 'POST') {
    http_response_code(405);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'error' => 'This action must be submitted as a POST request.']);
    } else {
        echo "<!doctype html><html lang='en'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>Action not allowed</title></head>"
           . "<body style='font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0'>"
           . "<div style='max-width:26rem;padding:2rem;text-align:center'>"
           . "<h2 style='margin:0 0 .5rem'>Action not allowed</h2>"
           . "<p style='color:#94a3b8'>For your security this action must be submitted from the application.</p>"
           . "<p style='margin-top:1.5rem'><a href='index.php' style='color:#4ade80;font-weight:600'>Back to dashboard</a></p></div></body></html>";
    }
    exit;
}

if ($reqMethod === 'POST' && !SessionHelper::csrfValidate($providedToken)) {
    http_response_code(403);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'error' => 'Invalid or missing security token.']);
    } else {
        echo "<!doctype html><html lang='en'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>Session expired</title></head>"
           . "<body style='font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0'>"
           . "<div style='max-width:26rem;padding:2rem;text-align:center'>"
           . "<h2 style='margin:0 0 .5rem'>Your session expired</h2>"
           . "<p style='color:#94a3b8'>Go back, reload the page and try again.</p>"
           . "<p style='margin-top:1.5rem'><a href='index.php' style='color:#4ade80;font-weight:600'>Back to the app</a></p></div></body></html>";
    }
    exit;
}

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

// --- Idle session timeout (30 min): log it and bounce back to login ---
if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $idleLimit) {
    require_once __DIR__ . '/../src/Modules/Security/SecurityModel.php';
    (new SecurityModel($pdo))->logEvent((int) ($_SESSION['user_id'] ?? 0), 'session_timeout');
    session_unset();
    session_destroy();
    header('Location: index.php?module=Security&action=login&expired=1');
    exit;
}
$_SESSION['last_activity'] = time();

// --- Role-Based Access Control (RBAC) ---
$role = $_SESSION['role'] ?? 'worker';
$financeOnly = ['admin', 'accountant'];
if ($module === 'Finance' && !in_array($role, $financeOnly, true)) {
    die("Access Denied: Only admins can access the Finance module.");
}

$module = $_GET['module'] ?? 'Dashboard';
$action = $_GET['action'] ?? 'index';

// --- Pest is no longer a standalone module: it now lives inside Crop & Livestock.
// Keep the old URL working by redirecting it into the Crop module.
if ($module === 'Pest') {
    header('Location: index.php?module=Crop&action=manage');
    exit;
}

// --- Lookup reference data (dependent combobox options) ---
if ($module === 'Lookup' && $action === 'options') {
    header('Content-Type: application/json; charset=UTF-8');
    require_once __DIR__ . '/../src/Helpers/LookupHelper.php';
    $category = $_GET['category'] ?? '';
    $parent   = $_GET['parent'] ?? '';
    $filter   = ($parent !== '') ? ['parent' => $parent] : [];
    echo json_encode(['success' => true, 'options' => LookupHelper::listOptions($pdo, $category, $filter)]);
    exit;
}

// --- Remember a custom value typed into an open combobox (Phase 2) ---
if ($module === 'Lookup' && $action === 'remember') {
    header('Content-Type: application/json; charset=UTF-8');
    require_once __DIR__ . '/../src/Helpers/LookupHelper.php';
    require_once __DIR__ . '/../src/Helpers/SessionHelper.php';

    // Only supervisory roles may add new dropdown values, and the request must
    // carry the session CSRF token (defence against cross-site posting).
    $rememberRoles = ['admin', 'manager', 'accountant'];
    if (!in_array($role, $rememberRoles, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not permitted to add list values.']);
        exit;
    }
    if (!SessionHelper::csrfValidate($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing security token.']);
        exit;
    }

    $category = (string) ($_POST['category'] ?? '');
    $value    = (string) ($_POST['value'] ?? '');
    if (!preg_match('/^[a-z_]{1,40}$/', $category)) {
        echo json_encode(['success' => false, 'error' => 'Invalid category.']);
        exit;
    }
    $canonical = LookupHelper::remember($pdo, $category, $value);
    echo json_encode(['success' => true, 'value' => $canonical]);
    exit;
}

// --- Lookup demo/acceptance page (Phase 1): proves pick-or-type + custom values ---
if ($module === 'Lookup' && $action === 'test') {
    require_once __DIR__ . '/../src/Helpers/LookupHelper.php';
    require_once __DIR__ . '/../src/Helpers/FormHelper.php';
    require_once __DIR__ . '/../src/Helpers/ValidationHelper.php';
    $result = null; $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $breed = trim($_POST['breed'] ?? '');
        $farm  = $_POST['farm_id'] ?? '';
        if ($breed !== '') $breed = LookupHelper::remember($pdo, 'livestock_breed', $breed);
        if (!ValidationHelper::existsIn($pdo, 'farms', $farm)) {
            $errors['farm_id'] = 'Pick an existing farm from the list.';
        }
        if (empty($errors)) $result = ['breed' => $breed, 'farm_id' => (int) $farm];
    }
    $data = ['result' => $result, 'errors' => $errors, 'data' => $_POST];
    ob_start(); require __DIR__ . '/views/lookup_test.php'; $content = ob_get_clean();
    require __DIR__ . '/views/layout.php'; exit;
}

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
        $data['pending_leave_list'] = $labour->hrNew()->leaveRequests(true);
        $data['recent_training'] = array_slice($labour->hrNew()->training(), 0, 5);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        require __DIR__ . "/views/layout.php";
        exit;
    }
}

// --- Finance: loans & budgets (sub-views demoted behind panel buttons) ---
if ($module === 'Finance' && in_array($action, ['loans', 'budgets'], true)) {
    require_once __DIR__ . '/../src/Modules/Finance/FinanceController.php';
    $finance = new FinanceController($pdo);
    $finView = ($action === 'loans') ? 'finance_loans.php' : 'finance_budgets.php';
    $data = $finance->$action();
    ob_start();
    require __DIR__ . '/views/' . $finView;
    $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
    exit;
}

// --- Notifications: mark-as-read + alert-rule actions and rules view ---
if ($module === 'Notifications') {
    require_once __DIR__ . '/../src/Modules/Notifications/NotificationsController.php';
    $notif = new NotificationsController($pdo);
    $notifMap = [
        'mark_read'     => 'markRead',
        'mark_all_read' => 'markAllRead',
        'run_rules'     => 'runRules',
        'toggle_rule'   => 'toggleRule',
        'delete_rule'   => 'deleteRule',
    ];
    if (isset($notifMap[$action])) {
        $method = $notifMap[$action];
        if (in_array($action, ['mark_read', 'toggle_rule', 'delete_rule'], true)) {
            $notif->$method((int) ($_GET['id'] ?? 0));
        } else {
            $notif->$method();
        }
        header('Location: index.php?module=Notifications&action=manage');
        exit;
    }
    if ($action === 'rules') {
        $data = $notif->rules();
        ob_start();
        require __DIR__ . '/views/notifications_rules.php';
        $content = ob_get_clean();
        require __DIR__ . '/views/layout.php';
        exit;
    }
}

// --- Security: password change, user management, audit log ---
if ($module === 'Security') {
    require_once __DIR__ . '/../src/Modules/Security/SecurityController.php';
    $secCtrl = new SecurityController($pdo);
    if ($action === 'change_password') {
        $data = $secCtrl->changePassword();
        ob_start(); require __DIR__ . '/views/security_password.php'; $content = ob_get_clean();
        require __DIR__ . '/views/layout.php'; exit;
    }
    if ($action === 'users') {
        if ($role !== 'admin') { die("Access Denied: User management is restricted to admins."); }
        $data = $secCtrl->users();
        ob_start(); require __DIR__ . '/views/security_users.php'; $content = ob_get_clean();
        require __DIR__ . '/views/layout.php'; exit;
    }
    if ($action === 'audit') {
        $data = $secCtrl->auditLog();
        ob_start(); require __DIR__ . '/views/security_audit.php'; $content = ob_get_clean();
        require __DIR__ . '/views/layout.php'; exit;
    }
    $data = $secCtrl->index();
    ob_start(); require __DIR__ . '/views/security.php'; $content = ob_get_clean();
    require __DIR__ . '/views/layout.php'; exit;
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