<?php
require_once __DIR__ . '/../config/database.php';

$module = $_GET['module'] ?? 'Farm';
$action = $_GET['action'] ?? 'index';

// Simple Router
$controllerFile = __DIR__ . "/../src/Modules/{$module}/{$module}Controller.php";

if (file_exists($controllerFile)) {
    require_once $controllerFile;
    $controllerName = "{$module}Controller";
    $controller = new $controllerName($pdo);

    if (method_exists($controller, $action)) {
        $data = $controller->$action();
        require_once __DIR__ . "/views/" . strtolower($module) . ".php";
    } else {
        echo "Action not found.";
    }
} else {
    echo "Module not found.";
}
?>
