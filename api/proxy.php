<?php

require_once __DIR__ . '/../lib/Config.php';

$url = $_GET['url'] ?? '';

if (empty($url) || !preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    die('URL invalida');
}

$allowedExtensions = ['woff', 'woff2', 'ttf', 'eot', 'otf', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico'];
$ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions)) {
    http_response_code(400);
    die('Tipo de arquivo nao permitido');
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
]);

$content = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($content === false || $httpCode >= 400) {
    http_response_code(502);
    die('Erro ao buscar recurso');
}

$mimeTypes = [
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'eot' => 'application/vnd.ms-fontobject',
    'otf' => 'font/otf',
    'css' => 'text/css',
    'js' => 'application/javascript',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
];

header('Content-Type: ' . ($contentType ?: ($mimeTypes[$ext] ?? 'application/octet-stream')));
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: public, max-age=86400');

echo $content;
exit;
