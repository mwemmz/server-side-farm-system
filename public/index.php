<?php
require_once __DIR__ . '/../config/database.php';

$module = $_GET['module'] ?? 'Farm';
$action = $_GET['action'] ?? 'index';

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
