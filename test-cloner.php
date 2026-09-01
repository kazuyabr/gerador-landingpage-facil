<?php
/**
 * Script de teste do Cloner com HTML real
 * Uso: php test-cloner.php
 */

require_once __DIR__ . '/lib/Cloner.php';

$html = file_get_contents(__DIR__ . '/jobs/test-input.html');
if ($html === false) {
    echo "❌ Arquivo de teste não encontrado em jobs/test-input.html\n";
    echo "   Coloque o HTML do site de teste nesse caminho para validar.\n";
    exit(1);
}

echo "=== Teste do Cloner ===\n";
echo "Tamanho do HTML: " . strlen($html) . " bytes\n\n";

$cloner = new Cloner();
$affiliateLink = 'https://go.hotmart.com/SEU_LINK_AFILIADO?off=teste123';

echo "Processando com link de afiliado: {$affiliateLink}\n\n";

$result = $cloner->process($html, $affiliateLink, 'paste');

echo "CTAs detectados: " . count($result['ctas']) . "\n\n";

if (count($result['ctas']) > 0) {
    echo "Detalhes:\n";
    foreach ($result['ctas'] as $i => $cta) {
        echo "  " . ($i + 1) . ". [{$cta['type']}] {$cta['text']}\n";
        echo "     De: {$cta['old_href']}\n";
        echo "     Para: {$cta['new_href']}\n\n";
    }

    file_put_contents(__DIR__ . '/jobs/test-output.html', $result['html']);
    echo "✅ HTML processado salvo em jobs/test-output.html\n";
} else {
    echo "⚠️ Nenhum CTA detectado.\n";
    echo "   Verifique se o HTML tem links de checkout (hotmart, kiwify, etc)\n";
}
