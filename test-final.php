<?php
require_once '/app/lib/Cloner.php';
require_once '/app/lib/ZipBuilder.php';
require_once '/app/lib/Config.php';

$cloner = new Cloner();
$result = $cloner->fetchUrl('https://preguicaartificial.com/');
$html = $result['html'];

$cloner2 = new Cloner();
$processed = $cloner2->process($html, 'https://go.hotmart.com/teste', 'url');

$zipBuilder = new ZipBuilder();
$zipPath = $zipBuilder->buildHtmlZip($processed['html'], 'finaltest', $processed);

$zip = new ZipArchive();
$zip->open($zipPath);
$htmlContent = $zip->getFromName('index.html');

echo "ZIP size: " . filesize($zipPath) . " bytes\n";
echo "Files: " . $zip->numFiles . "\n";

preg_match_all('/<link\b[^>]+rel=["\']stylesheet/i', $htmlContent, $links);
echo "Link stylesheets: " . count($links[0]) . "\n";

preg_match_all('/<script[^>]*src=["\']https?:\/\/[^"\']+["\']/i', $htmlContent, $scripts);
echo "External script src: " . count($scripts[0]) . "\n";

preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $htmlContent, $inlineScripts);
$tracking = 0;
foreach ($inlineScripts[1] as $content) {
    if (preg_match('/(googletagmanager|cloudflareinsights|dataLayer|gtag|_fbq|_linkedin)/i', $content)) $tracking++;
}
echo "Tracking inline scripts: $tracking\n";

preg_match_all('/src="(?!data:|#)(https?:\/\/[^"]+)"/', $htmlContent, $extSrc);
echo "External src attrs: " . count($extSrc[0]) . "\n";

$zip->close();
echo "\nDone.\n";
