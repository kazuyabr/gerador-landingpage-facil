<?php
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

if ($path === '/' || $path === '') {
    require __DIR__ . '/index.php';
    return true;
}

$staticExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'woff', 'woff2', 'ttf'];
$ext = pathinfo($path, PATHINFO_EXTENSION);
if (in_array(strtolower($ext), $staticExtensions)) {
    return false;
}

$adminRoutes = [
    '/admin/' => '/admin/index.php',
    '/admin/index.php' => '/admin/index.php',
    '/admin/pages.php' => '/admin/pages.php',
    '/admin/clone.php' => '/admin/clone.php',
    '/admin/pressel.php' => '/admin/pressel.php',
    '/admin/video.php' => '/admin/video.php',
    '/admin/pixel.php' => '/admin/pixel.php',
    '/admin/backredirect.php' => '/admin/backredirect.php',
    '/admin/cookie.php' => '/admin/cookie.php',
    '/admin/domains.php' => '/admin/domains.php',
    '/admin/integrations.php' => '/admin/integrations.php',
    '/admin/settings.php' => '/admin/settings.php',
    '/admin/preview.php' => '/admin/preview.php',
    '/admin/download.php' => '/admin/download.php',
    '/admin/logout.php' => '/admin/logout.php',
    '/admin/api/clone.php' => '/admin/api/clone.php',
    '/admin/api/pages.php' => '/admin/api/pages.php',
];

$normalizedPath = rtrim($path, '/') . '/';
if (isset($adminRoutes[$normalizedPath])) {
    require __DIR__ . $adminRoutes[$normalizedPath];
    return true;
}

$pathNoSlash = rtrim($path, '/');
if (isset($adminRoutes[$pathNoSlash . '/'])) {
    require __DIR__ . $adminRoutes[$pathNoSlash . '/'];
    return true;
}

if (isset($adminRoutes[$path])) {
    require __DIR__ . $adminRoutes[$path];
    return true;
}

http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>Página não encontrada</h1><a href="/">Voltar</a></body></html>';
