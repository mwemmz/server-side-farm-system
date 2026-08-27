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