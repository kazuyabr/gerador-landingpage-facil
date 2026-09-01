<?php
session_start();

require_once __DIR__ . '/../lib/Cloner.php';
require_once __DIR__ . '/../lib/ZipBuilder.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$affiliateLink = trim($_POST['affiliate_link'] ?? '');
$sourceUrl = trim($_POST['source_url'] ?? '');
$sourceHtml = trim($_POST['source_html'] ?? '');

if ($affiliateLink === '') {
    $_SESSION['error'] = 'O link do afiliado é obrigatório.';
    header('Location: index.php');
    exit;
}

if (!filter_var($affiliateLink, FILTER_VALIDATE_URL)) {
    $affiliateLink = 'https://' . $affiliateLink;
    if (!filter_var($affiliateLink, FILTER_VALIDATE_URL)) {
        $_SESSION['error'] = 'Link do afiliado inválido. Use o formato https://...';
        header('Location: index.php');
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
        $_SESSION['error'] = 'Não foi possível buscar a URL. Erro: ' . $fetchResult['error'] . '. Tente colar o HTML manualmente.';
        header('Location: index.php');
        exit;
    }
    $html = $fetchResult['html'];
} else {
    $_SESSION['error'] = 'Forneça uma URL ou cole o HTML da landing page.';
    header('Location: index.php');
    exit;
}

if (strlen($html) < 200) {
    $_SESSION['error'] = 'O HTML parece estar vazio ou muito pequeno. Cole o código-fonte completo da página.';
    header('Location: index.php');
    exit;
}

$cloner = new Cloner();
$result = $cloner->process($html, $affiliateLink, $mode);

$jobId = bin2hex(random_bytes(8));
$jobsDir = __DIR__ . '/../jobs';
if (!is_dir($jobsDir)) {
    mkdir($jobsDir, 0777, true);
}

$jobFile = $jobsDir . '/' . $jobId . '.json';
$jobData = [
    'job_id' => $jobId,
    'html' => $result['html'],
    'ctas' => $result['ctas'],
    'affiliate_link' => $affiliateLink,
    'mode' => $mode,
    'created_at' => $result['created_at'],
    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
];

file_put_contents($jobFile, json_encode($jobData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

$_SESSION['result'] = [
    'job_id' => $jobId,
    'ctas' => $result['ctas'],
    'affiliate_link' => $affiliateLink,
    'created_at' => $result['created_at'],
];

header('Location: index.php');
exit;
