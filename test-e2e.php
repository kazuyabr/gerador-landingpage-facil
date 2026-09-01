<?php
/**
 * Teste end-to-end automatizado
 * Simula o fluxo completo: POST em process.php → gera job → baixa ZIP
 */

require_once __DIR__ . '/lib/Cloner.php';
require_once __DIR__ . '/lib/ZipBuilder.php';

echo "=== Teste E2E do Gerador ===\n\n";

$htmlPath = __DIR__ . '/jobs/test-input.html';
if (!file_exists($htmlPath)) {
    echo "❌ HTML de teste não encontrado em: {$htmlPath}\n";
    echo "   Copie o HTML do youtubesemaparecer.com.br para esse arquivo.\n";
    exit(1);
}

$html = file_get_contents($htmlPath);
echo "1. HTML carregado: " . strlen($html) . " bytes\n";

$affiliateLink = 'https://go.hotmart.com/TEST_AFFILIATE?off=e2e123';
echo "2. Link afiliado: {$affiliateLink}\n\n";

echo "3. Processando com Cloner...\n";
$cloner = new Cloner();
$result = $cloner->process($html, $affiliateLink, 'paste');

echo "   ✓ CTAs detectados: " . count($result['ctas']) . "\n";
echo "   ✓ HTML processado: " . strlen($result['html']) . " bytes\n\n";

if (count($result['ctas']) === 0) {
    echo "⚠️  Nenhum CTA encontrado. Verifique o HTML de teste.\n";
    exit(1);
}

echo "4. CTAs identificados:\n";
foreach ($result['ctas'] as $i => $cta) {
    $n = $i + 1;
    $text = mb_strimwidth($cta['text'], 0, 50, '...');
    $old = mb_strimwidth($cta['old_href'], 0, 50, '...');
    echo "   {$n}. [{$cta['type']}] \"{$text}\"\n";
    echo "      {$old} → {$affiliateLink}\n";
}
echo "\n";

echo "5. Salvando job simulado...\n";
$jobId = bin2hex(random_bytes(8));
$jobData = [
    'job_id' => $jobId,
    'html' => $result['html'],
    'ctas' => $result['ctas'],
    'affiliate_link' => $affiliateLink,
    'mode' => 'paste',
    'created_at' => date('Y-m-d H:i:s'),
    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
];
$jobsDir = __DIR__ . '/jobs';
if (!is_dir($jobsDir)) mkdir($jobsDir, 0777, true);
file_put_contents("{$jobsDir}/{$jobId}.json", json_encode($jobData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "   ✓ Job {$jobId} salvo\n\n";

echo "6. Gerando pacote HTML...\n";
try {
    $zipBuilder = new ZipBuilder();
    $zipPath = $zipBuilder->buildHtmlZip($result['html'], $jobId, $jobData);
    $size = filesize($zipPath);
    echo "   ✓ ZIP HTML criado: {$zipPath} ({$size} bytes)\n";
} catch (Throwable $e) {
    echo "   ✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n7. Gerando pacote Wix...\n";
try {
    $wixZip = $zipBuilder->buildWixEmbed($result['html'], $jobId, $affiliateLink);
    $size = filesize($wixZip);
    echo "   ✓ ZIP Wix criado: {$wixZip} ({$size} bytes)\n";
} catch (Throwable $e) {
    echo "   ✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n8. Gerando pacote Hostinger...\n";
try {
    $hostZip = $zipBuilder->buildHostingerPackage($result['html'], $jobId, $jobData);
    $size = filesize($hostZip);
    echo "   ✓ ZIP Hostinger criado: {$hostZip} ({$size} bytes)\n";
} catch (Throwable $e) {
    echo "   ✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n9. Verificando conteúdo do ZIP HTML...\n";
$zip = new ZipArchive();
if ($zip->open($zipPath) === true) {
    $found = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $found[] = "{$stat['name']} ({$stat['size']} bytes)";
    }
    echo "   ✓ Arquivos no ZIP:\n";
    foreach ($found as $f) {
        echo "     - {$f}\n";
    }
    $zip->close();
}

echo "\n10. Validando que link do afiliado está no HTML final...\n";
if (str_contains($result['html'], $affiliateLink)) {
    $occurrences = substr_count($result['html'], $affiliateLink);
    echo "   ✓ Link do afiliado encontrado {$occurrences} vez(es) no HTML processado\n";
} else {
    echo "   ✗ Link do afiliado NÃO encontrado no HTML processado!\n";
    exit(1);
}

echo "\n11. Limpando arquivos temporários...\n";
@unlink($zipPath);
@unlink($wixZip);
@unlink($hostZip);
@unlink("{$jobsDir}/{$jobId}.json");
echo "   ✓ Limpo\n\n";

echo "=== ✅ TODOS OS TESTES PASSARAM ===\n";
echo "Job ID usado: {$jobId}\n";
echo "Use este mesmo HTML em: http://localhost:8765/\n";
