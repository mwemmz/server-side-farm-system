<?php
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
require_once __DIR__ . '/../config/database.php';

// Run migrations automatically
require_once __DIR__ . '/../db/migrate.php';

$module = $_GET['module'] ?? 'Dashboard';
$action = $_GET['action'] ?? 'index';

// Simple RBAC check
if ($module === 'Finance' && ($_SESSION['role'] ?? 'worker') !== 'admin') {
    die("Access Denied: Only admins can access the Finance module.");
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
        echo "Action not found.";
    }
} else {
    echo "Module/View not found.";
}
?>
