<?php

require_once __DIR__ . '/../lib/Config.php';
require_once Config::getLibDir() . '/Cloner.php';
require_once Config::getLibDir() . '/ZipBuilder.php';

$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        echo 'index.php';
    } else {
        header('Location: index.php');
    }
    exit;
}

$affiliateLink = trim($_POST['affiliate_link'] ?? '');
$sourceUrl = trim($_POST['source_url'] ?? '');
$sourceHtml = trim($_POST['source_html'] ?? '');

if ($affiliateLink === '') {
    $error = urlencode('O link do afiliado e obrigatorio.');
    if ($isAjax) { echo "index.php?error={$error}"; exit; }
    header('Location: index.php?error=' . $error);
    exit;
}

if (!filter_var($affiliateLink, FILTER_VALIDATE_URL)) {
    $affiliateLink = 'https://' . $affiliateLink;
    if (!filter_var($affiliateLink, FILTER_VALIDATE_URL)) {
        $error = urlencode('Link do afiliado invalido. Use o formato https://...');
        if ($isAjax) { echo "index.php?error={$error}"; exit; }
        header('Location: index.php?error=' . $error);
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
        $error = urlencode('Nao foi possivel buscar a URL. Erro: ' . $fetchResult['error'] . '. Tente colar o HTML manualmente.');
        if ($isAjax) { echo "index.php?error={$error}"; exit; }
        header('Location: index.php?error=' . $error);
        exit;
    }
    $html = $fetchResult['html'];
} else {
    $error = urlencode('Forneça uma URL ou cole o HTML da landing page.');
    if ($isAjax) { echo "index.php?error={$error}"; exit; }
    header('Location: index.php?error=' . $error);
    exit;
}

if (strlen($html) < 200) {
    $error = urlencode('O HTML parece estar vazio ou muito pequeno. Cole o codigo-fonte completo da pagina.');
    if ($isAjax) { echo "index.php?error={$error}"; exit; }
    header('Location: index.php?error=' . $error);
    exit;
}

$cloner = new Cloner();
$result = $cloner->process($html, $affiliateLink, $mode);

$jobId = bin2hex(random_bytes(8));
$jobsDir = Config::getJobsDir();
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

$redirectUrl = 'index.php?job=' . $jobId;
if ($isAjax) {
    echo $redirectUrl;
} else {
    header('Location: ' . $redirectUrl);
}
exit;
