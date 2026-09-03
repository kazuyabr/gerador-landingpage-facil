<?php
session_start();

require_once __DIR__ . '/lib/Config.php';
require_once Config::getLibDir() . '/MediaDownloader.php';
require_once Config::getLibDir() . '/ZipBuilder.php';

$jobId = $_GET['job'] ?? '';
$type = $_GET['type'] ?? 'html';

if ($jobId === '' || !preg_match('/^[a-f0-9]{16}$/', $jobId)) {
    http_response_code(400);
    die('Job ID invalido');
}

$jobFile = Config::getJobsDir() . '/' . $jobId . '.json';
if (!file_exists($jobFile)) {
    http_response_code(404);
    die('Job nao encontrado ou expirado');
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

$zipBuilder = new ZipBuilder();

try {
    switch ($type) {
        case 'wix':
            $zipPath = $zipBuilder->buildWixEmbed($jobData['html'], $jobId, $jobData['affiliate_link']);
            $filename = "landingpage-wix-{$jobId}.zip";
            break;
        case 'hostinger':
            $zipPath = $zipBuilder->buildHostingerPackage($jobData['html'], $jobId, $jobData);
            $filename = "landingpage-hostinger-{$jobId}.zip";
            break;
        case 'html':
        default:
            $zipPath = $zipBuilder->buildHtmlZip($jobData['html'], $jobId, $jobData);
            $filename = "landingpage-{$jobId}.zip";
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    die('Erro ao gerar pacote: ' . htmlspecialchars($e->getMessage()));
}

if (!file_exists($zipPath)) {
    http_response_code(500);
    die('Falha ao criar arquivo de download');
}

if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($zipPath);

@unlink($zipPath);

exit;
