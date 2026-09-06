<?php

class ZipBuilder
{
    public function buildHtmlZip(string $html, string $jobId, array $metadata = []): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Extensao ZipArchive nao disponivel');
        }

        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'clone_' . $jobId;
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $assetsDir = $tmpDir . DIRECTORY_SEPARATOR . 'assets';
        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0777, true);
        }

        $html = $this->extractDataUris($html, $assetsDir);
        $html = $this->extractInlineCss($html, $assetsDir);

        $htmlPath = $tmpDir . DIRECTORY_SEPARATOR . 'index.html';
        file_put_contents($htmlPath, $html);

        $readme = $this->buildReadme($metadata);
        file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'LEIA-ME.txt', $readme);

        $zipPath = $tmpDir . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Nao foi possivel criar o arquivo ZIP');
        }

        $zip->addFile($htmlPath, 'index.html');
        $zip->addFile($tmpDir . DIRECTORY_SEPARATOR . 'LEIA-ME.txt', 'LEIA-ME.txt');

        $this->addAssetsToZip($zip, $assetsDir, 'assets/');

        $zip->close();

        $this->cleanup($tmpDir);

        return $zipPath;
    }

    public function buildWixEmbed(string $html, string $jobId, string $affiliateLink, string $sourceDomain = ''): string
    {
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wix_' . $jobId;
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $assetsDir = $tmpDir . DIRECTORY_SEPARATOR . 'landingpage' . DIRECTORY_SEPARATOR . 'assets';
        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0777, true);
        }

        $html = $this->extractDataUris($html, $assetsDir);
        $html = $this->extractInlineCss($html, $assetsDir);

        $embedHtml = $this->buildWixEmbedContent($affiliateLink);
        file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'wix-embed.html', $embedHtml);

        $readme = $this->buildWixReadme($affiliateLink);
        file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'LEIA-ME-WIX.txt', $readme);

        $htmlHostedPath = $tmpDir . DIRECTORY_SEPARATOR . 'landingpage' . DIRECTORY_SEPARATOR . 'index.html';
        file_put_contents($htmlHostedPath, $html);

        $zip = new ZipArchive();
        $zipPath = $tmpDir . '.zip';
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($htmlHostedPath, 'landingpage/index.html');
        $zip->addFile($tmpDir . DIRECTORY_SEPARATOR . 'wix-embed.html', 'wix-embed.html');
        $zip->addFile($tmpDir . DIRECTORY_SEPARATOR . 'LEIA-ME-WIX.txt', 'LEIA-ME-WIX.txt');

        $this->addAssetsToZip($zip, $assetsDir, 'landingpage/assets/');

        $zip->close();

        $this->cleanup($tmpDir);

        return $zipPath;
    }

    public function buildHostingerPackage(string $html, string $jobId, array $metadata = []): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Extensao ZipArchive nao disponivel');
        }

        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hostinger_' . $jobId;
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $assetsDir = $tmpDir . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'assets';
        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0777, true);
        }

        $html = $this->extractDataUris($html, $assetsDir);
        $html = $this->extractInlineCss($html, $assetsDir);

        $htmlPath = $tmpDir . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'index.html';
        file_put_contents($htmlPath, $html);

        $htaccess = $this->buildHtaccess();
        file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . '.htaccess', $htaccess);

        $instructions = $this->buildHostingerInstructions($metadata);
        file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'INSTRUCOES-HOSTINGER.txt', $instructions);

        $zip = new ZipArchive();
        $zipPath = $tmpDir . '.zip';
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($htmlPath, 'public_html/index.html');
        $zip->addFile($tmpDir . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . '.htaccess', 'public_html/.htaccess');
        $zip->addFile($tmpDir . DIRECTORY_SEPARATOR . 'INSTRUCOES-HOSTINGER.txt', 'INSTRUCOES-HOSTINGER.txt');

        $this->addAssetsToZip($zip, $assetsDir, 'public_html/assets/');

        $zip->close();

        $this->cleanup($tmpDir);

        return $zipPath;
    }

    private function extractDataUris(string $html, string $assetsDir): string
    {
        $counter = 0;

        $html = preg_replace_callback('/url\([\'"]data:([^;]+);base64,([A-Za-z0-9+\/=]+)[\'"]\)/i', function($m) use ($assetsDir, &$counter) {
            $mime = $m[1];
            $data = base64_decode($m[2]);
            if ($data === false) return $m[0];

            $ext = $this->mimeToExt($mime);
            $filename = 'asset_' . (++$counter) . '.' . $ext;
            $filepath = $assetsDir . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($filepath, $data);

            return "url('assets/{$filename}')";
        }, $html);

        $html = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/is', function($m) use ($assetsDir, &$counter) {
            $css = $m[1];
            $css = preg_replace_callback('/url\([\'"]data:([^;]+);base64,([A-Za-z0-9+\/=]+)[\'"]\)/i', function($mm) use ($assetsDir, &$counter) {
                $mime = $mm[1];
                $data = base64_decode($mm[2]);
                if ($data === false) return $mm[0];

                $ext = $this->mimeToExt($mime);
                $filename = 'asset_' . (++$counter) . '.' . $ext;
                $filepath = $assetsDir . DIRECTORY_SEPARATOR . $filename;
                file_put_contents($filepath, $data);

                return "url('assets/{$filename}')";
            }, $css);

            return "<style{$m[0]}>{$css}</style>";
        }, $html);

        return $html;
    }

    private function extractInlineCss(string $html, string $assetsDir): string
    {
        $counter = 0;

        $html = preg_replace_callback('/<style[^>]*data-cloned="true"[^>]*>(.*?)<\/style>/is', function($m) use ($assetsDir, &$counter) {
            $css = $m[1];
            $filename = 'style_' . (++$counter) . '.css';
            $filepath = $assetsDir . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($filepath, $css);

            return "<link rel=\"stylesheet\" href=\"assets/{$filename}\">";
        }, $html);

        return $html;
    }

    private function addAssetsToZip(ZipArchive $zip, string $assetsDir, string $prefix): void
    {
        if (!is_dir($assetsDir)) return;

        $it = new RecursiveDirectoryIterator($assetsDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isFile()) {
                $relative = $prefix . substr($file->getRealPath(), strlen($assetsDir) + 1);
                $zip->addFile($file->getRealPath(), $relative);
            }
        }
    }

    private function mimeToExt(string $mime): string
    {
        $map = [
            'font/woff' => 'woff', 'font/woff2' => 'woff2', 'font/ttf' => 'ttf',
            'application/vnd.ms-fontobject' => 'eot', 'font/otf' => 'otf',
            'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif',
            'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'image/x-icon' => 'ico',
        ];
        return $map[$mime] ?? 'bin';
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }
        @rmdir($dir);
    }

    private function buildReadme(array $metadata): string
    {
        $ctas = $metadata['ctas'] ?? [];
        $link = $metadata['affiliate_link'] ?? '(nao definido)';
        $createdAt = $metadata['created_at'] ?? date('Y-m-d H:i:s');

        $ctaList = '';
        foreach ($ctas as $i => $cta) {
            $n = $i + 1;
            $ctaList .= "  {$n}. [{$cta['type']}] {$cta['text']}\n";
            $ctaList .= "     De: {$cta['old_href']}\n";
            $ctaList .= "     Para: {$cta['new_href']}\n\n";
        }

        return <<<TXT
========================================
CLONE DE LANDING PAGE - AFILIADO
========================================

Gerado em: {$createdAt}
Link do afiliado aplicado: {$link}

CTAS DETECTADOS E SUBSTITUIDOS ({$this->count($ctas)}):
{$ctaList}

COMO USAR:
----------
1. Faca upload de TODOS os arquivos para sua hospedagem
2. Pode ser hospedagem comum (Hostinger, Locaweb, etc)
3. Os links de CTA ja apontam para seu link de afiliado
4. Todos os recursos (CSS, imagens, fonts) estao incluidos

========================================
TXT;
    }

    private function buildWixEmbedContent(string $affiliateLink): string
    {
        $link = htmlspecialchars($affiliateLink, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Embed Landing Page</title>
  <style>
    body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; }
    iframe { width: 100%; height: 100vh; border: 0; display: block; }
  </style>
</head>
<body>
  <iframe src="SUA_URL_AQUI/landingpage/index.html" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>
</body>
</html>
HTML;
    }

    private function buildWixReadme(string $affiliateLink): string
    {
        $link = htmlspecialchars($affiliateLink, ENT_QUOTES, 'UTF-8');
        return <<<TXT
========================================
COMO USAR NO WIX
========================================

1. hospede landingpage/index.html em um host gratuito
2. Abra wix-embed.html e troque SUA_URL_AQUI pela URL publica
3. No Wix, adicione um elemento HTML iframe e cole o conteudo

Seu link de afiliado: {$link}
========================================
TXT;
    }

    private function buildHostingerInstructions(array $metadata): string
    {
        $link = $metadata['affiliate_link'] ?? '(nao definido)';
        return <<<TXT
========================================
COMO SUBIR NO HOSTINGER
========================================

1. Acesse hpanel.hostinger.com
2. Va em "Gerenciador de Arquivos"
3. Navegue ate public_html
4. Faca upload de TODOS os arquivos
5. Pronto!

Seu link de afiliado: {$link}
========================================
TXT;
    }

    private function buildHtaccess(): string
    {
        return <<<HTACCESS
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript
</IfModule>
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 1 month"
  ExpiresByType application/javascript "access plus 1 month"
  ExpiresByType image/png "access plus 1 year"
  ExpiresByType image/jpeg "access plus 1 year"
  ExpiresByType image/webp "access plus 1 year"
  ExpiresByType image/svg+xml "access plus 1 year"
</IfModule>
AddDefaultCharset UTF-8
Options -Indexes
HTACCESS;
    }

    private function count(array $arr): int
    {
        return count($arr);
    }
}
