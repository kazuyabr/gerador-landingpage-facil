<?php

require_once __DIR__ . '/../lib/Cloner.php';
require_once __DIR__ . '/../lib/MediaDownloader.php';
require_once __DIR__ . '/../lib/ZipBuilder.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$affiliateLink = trim($_POST['affiliate_link'] ?? '');
$sourceUrl = trim($_POST['source_url'] ?? '');
$sourceHtml = trim($_POST['source_html'] ?? '');

if ($affiliateLink === '') {
    header('Location: index.php?error=' . urlencode('O link do afiliado é obrigatório.'));
    exit;
}

if (!filter_var($affiliateLink, FILTER_VALIDATE_URL)) {
    $affiliateLink = 'https://' . $affiliateLink;
    if (!filter_var($affiliateLink, FILTER_VALIDATE_URL)) {
        header('Location: index.php?error=' . urlencode('Link do afiliado inválido. Use o formato https://...'));
        exit;
    }
}

$html = '';
$mode = 'paste';

if ($sourceHtml !== '') {
    $html = $sourceHtml;
    $mode = 'paste';
} elseif ($sourceUrl !== '') {
    $mode = 'url';
    $cloner = new Cloner();
    $fetchResult = $cloner->fetchUrl($sourceUrl);

    if (!$fetchResult['success']) {
        header('Location: index.php?error=' . urlencode('Não foi possível buscar a URL. Erro: ' . $fetchResult['error'] . '. Tente colar o HTML manualmente.'));
        exit;
    }
    $html = $fetchResult['html'];
} else {
    header('Location: index.php?error=' . urlencode('Forneça uma URL ou cole o HTML da landing page.'));
    exit;
}

if (strlen($html) < 200) {
    header('Location: index.php?error=' . urlencode('O HTML parece estar vazio ou muito pequeno. Cole o código-fonte completo da página.'));
    exit;
}

$cloner = new Cloner();
$result = $cloner->process($html, $affiliateLink, $mode);

$jobId = bin2hex(random_bytes(8));
$jobsDir = getenv('VERCEL') ? '/tmp/jobs' : __DIR__ . '/../jobs';
if (!is_dir($jobsDir)) {
    mkdir($jobsDir, 0777, true);
}

$jobFile = $jobsDir . '/' . $jobId . '.json';
$jobData = [
    'job_id' => $jobId,
    'html' => $result['html'],
    'ctas' => $result['ctas'],
    'affiliate_link' => $affiliateLink,
    'source_domain' => $result['source_domain'] ?? '',
    'mode' => $mode,
    'created_at' => $result['created_at'],
    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
];

file_put_contents($jobFile, json_encode($jobData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

header('Location: index.php?job=' . $jobId);
exit;
