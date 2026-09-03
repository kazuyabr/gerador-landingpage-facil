<?php

require_once __DIR__ . '/../lib/Config.php';

$jobId = $_GET['job'] ?? '';

if ($jobId === '' || !preg_match('/^[a-f0-9]{16}$/', $jobId)) {
    http_response_code(400);
    die('Job ID invalido');
}

$jobFile = Config::getJobsDir() . '/' . $jobId . '.json';
if (!file_exists($jobFile)) {
    http_response_code(404);
    die('Job nao encontrado ou expirado. Gere um novo clone.');
}

$jobData = json_decode(file_get_contents($jobFile), true);
if (!$jobData || !isset($jobData['html'])) {
    http_response_code(500);
    die('Dados do job corrompidos');
}

if (isset($jobData['expires_at']) && strtotime($jobData['expires_at']) < time()) {
    @unlink($jobFile);
    http_response_code(410);
    die('Job expirado');
}

$html = $jobData['html'];

header('Content-Type: text/html; charset=UTF-8');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');

echo $html;
exit;
