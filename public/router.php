<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = __DIR__ . $uri;

if ($uri !== '/' && file_exists($path) && !is_dir($path)) {
    return false;
}

$candidate = __DIR__ . $uri;
if (is_file($candidate)) {
    return false;
}

$uri = rtrim($uri, '/');
$scriptName = $_SERVER['SCRIPT_NAME'];

$routes = [
    '/' => 'index.php',
    '/index.php' => 'index.php',
    '/process' => 'process.php',
    '/process.php' => 'process.php',
    '/download' => 'download.php',
    '/download.php' => 'download.php',
    '/preview' => 'preview.php',
    '/preview.php' => 'preview.php',
];

if (isset($routes[$uri])) {
    require __DIR__ . '/' . $routes[$uri];
    return true;
}

http_response_code(404);
echo '404 - Página não encontrada';
return true;
