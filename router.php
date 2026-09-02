<?php
require_once __DIR__ . '/lib/Config.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$assetsPath = __DIR__ . '/public' . $uri;
if (strpos($uri, '/assets/') === 0) {
    if (file_exists($assetsPath) && !is_dir($assetsPath)) {
        $ext = pathinfo($assetsPath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        readfile($assetsPath);
        return true;
    }
    http_response_code(404);
    echo '404 - Asset não encontrado';
    return true;
}

$filePath = __DIR__ . $uri;
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
    return false;
}

$uriClean = rtrim($uri, '/');

$routes = [
    '' => 'index.php',
    '/' => 'index.php',
    '/index.php' => 'index.php',
    '/process' => 'process.php',
    '/process.php' => 'process.php',
    '/download' => 'download.php',
    '/download.php' => 'download.php',
    '/preview' => 'preview.php',
    '/preview.php' => 'preview.php',
];

if (isset($routes[$uri]) || isset($routes[$uriClean])) {
    $target = $routes[$uri] ?? $routes[$uriClean];
    require __DIR__ . '/' . $target;
    return true;
}

http_response_code(404);
echo '404 - Página não encontrada';
return true;
