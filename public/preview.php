<?php

$jobId = $_GET['job'] ?? '';

if ($jobId === '' || !preg_match('/^[a-f0-9]{16}$/', $jobId)) {
    http_response_code(400);
    die('Job ID inválido');
}

$jobFile = __DIR__ . '/../jobs/' . $jobId . '.json';
if (!file_exists($jobFile)) {
    http_response_code(404);
    die('Job não encontrado ou expirado. Gere um novo clone.');
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

header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: SAMEORIGIN');

echo $jobData['html'];
exit;
