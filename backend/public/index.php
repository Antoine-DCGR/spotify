<?php
require_once __DIR__ . '/config/database.php';

$url = $_GET['url'] ?? 'home/index';
$params = explode('/', $url);
$controller = $params[0] ?? 'home';
$method = $params[1] ?? 'index';

$controllerClass = ucfirst($controller) . 'Controller';
$controllerFile = __DIR__ . "/app/Controllers/$controllerClass.php";

if (file_exists($controllerFile)) {
    require_once $controllerFile;
    $ctrl = new $controllerClass();
    if (method_exists($ctrl, $method)) {
        $ctrl->$method();
    } else {
        http_response_code(404);
        echo "404 Not Found (méthode)";
    }
} else {
    http_response_code(404);
    echo "404 Not Found (contrôleur)";
}
