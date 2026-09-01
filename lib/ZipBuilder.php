<?php

class ZipBuilder
{
    private ?MediaDownloader $downloader = null;

    private function ensureMediaDownloaded(string $html, string $jobId, string $sourceDomain): string
    {
        $this->downloader = new MediaDownloader($jobId);
        $this->downloader->downloadAndMap($html, $sourceDomain);
        return $this->downloader->updateHtml($html);
    }

    private function addMediaToZip(ZipArchive $zip, string $prefix): void
    {
        if ($this->downloader === null) {
            return;
        }
        $mediaDir = $this->downloader->getMediaDir();
        if (!is_dir($mediaDir)) {
            return;
        }
        $it = new RecursiveDirectoryIterator($mediaDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isFile()) {
                $relative = $prefix . 'media/' . substr($file->getRealPath(), strlen($mediaDir) + 1);
                $zip->addFile($file->getRealPath(), $relative);
            }
        }
    }

    public function buildHtmlZip(string $html, string $jobId, array $metadata = []): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Extensão ZipArchive não está disponível no PHP');
        }

        $sourceDomain = $metadata['source_domain'] ?? '';
        if ($sourceDomain !== '') {
            $html = $this->ensureMediaDownloaded($html, $jobId, $sourceDomain);
        }

        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'clone_' . $jobId;
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $htmlPath = $tmpDir . DIRECTORY_SEPARATOR . 'index.html';
        file_put_contents($htmlPath, $html);

        $readme = $this->buildReadme($metadata);
        file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'LEIA-ME.txt', $readme);

        $zipPath = $tmpDir . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o arquivo ZIP');
        }

        $zip->addFile($htmlPath, 'index.html');
        $zip->addFile($tmpDir . DIRECTORY_SEPARATOR . 'LEIA-ME.txt', 'LEIA-ME.txt');
        $this->addMediaToZip($zip, '');

        $zip->close();

        if ($this->downloader !== null) {
            $this->downloader->cleanup();
            $this->downloader = null;
        }

        return $zipPath;
    }

    public function buildWixEmbed(string $html, string $jobId, string $affiliateLink, string $sourceDomain = ''): string
    {
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wix_' . $jobId;
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        if ($sourceDomain !== '') {
            $html = $this->ensureMediaDownloaded($html, $jobId, $sourceDomain);
        }

        $embedHtml = $this->buildWixEmbedContent($affiliateLink);
        file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'wix-embed.html', $embedHtml);

        $readme = $this->buildWixReadme($affiliateLink);
        file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'LEIA-ME-WIX.txt', $readme);

        $htmlHostedPath = $tmpDir . DIRECTORY_SEPARATOR . 'index.html';
        file_put_contents($htmlHostedPath, $html);

        $zip = new ZipArchive();
        $zipPath = $tmpDir . '.zip';
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($htmlHostedPath, 'landingpage/index.html');
        $zip->addFile($tmpDir . DIRECTORY_SEPARATOR . 'wix-embed.html', 'wix-embed.html');
        $zip->addFile($tmpDir . DIRECTORY_SEPARATOR . 'LEIA-ME-WIX.txt', 'LEIA-ME-WIX.txt');
        $this->addMediaToZip($zip, 'landingpage/');
        $zip->close();

        if ($this->downloader !== null) {
            $this->downloader->cleanup();
            $this->downloader = null;
        }

        return $zipPath;
    }

    public function buildHostingerPackage(string $html, string $jobId, array $metadata = []): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Extensão ZipArchive não está disponível no PHP');
        }

        $sourceDomain = $metadata['source_domain'] ?? '';
        if ($sourceDomain !== '') {
            $html = $this->ensureMediaDownloaded($html, $jobId, $sourceDomain);
        }

        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hostinger_' . $jobId;
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $htmlPath = $tmpDir . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'index.html';
        if (!is_dir(dirname($htmlPath))) {
            mkdir(dirname($htmlPath), 0777, true);
        }
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
        $this->addMediaToZip($zip, 'public_html/');
        $zip->close();

        if ($this->downloader !== null) {
            $this->downloader->cleanup();
            $this->downloader = null;
        }

        return $zipPath;
    }

    private function buildReadme(array $metadata): string
    {
        $ctas = $metadata['ctas'] ?? [];
        $link = $metadata['affiliate_link'] ?? '(não definido)';
        $createdAt = $metadata['created_at'] ?? date('Y-m-d H:i:s');

        $ctaList = '';
        foreach ($ctas as $i => $cta) {
            $n = $i + 1;
            $ctaList .= "  {$n}. [{$cta['type']}] {$cta['text']}\n";
            $ctaList .= "     De: {$cta['old_href']}\n";
            $ctaList .= "     Para: {$cta['new_href']}\n\n";
        }

        $mediaCount = $metadata['media_count'] ?? 0;
        $mediaNote = $mediaCount > 0
            ? "- {$mediaCount} arquivos de mídia (imagens, CSS, fontes) incluídos na pasta media/"
            : '- As imagens e CSS continuam sendo carregados dos servidores originais';

        return <<<TXT
========================================
CLONE DE LANDING PAGE - AFILIADO
========================================

Gerado em: {$createdAt}
Link do afiliado aplicado: {$link}

CTAS DETECTADOS E SUBSTITUÍDOS ({$this->count($ctas)}):
{$ctaList}

COMO USAR:
----------
1. Faça upload de TODOS os arquivos para sua hospedagem
2. Mantenha a estrutura de pastas (se houver pasta media/)
3. Pode ser hospedagem comum (Hostinger, Locaweb, etc) ou estática (Vercel, Netlify)
4. Os links de CTA já estão apontando para seu link de afiliado

OBSERVAÇÕES:
------------
{$mediaNote}

Suporte: Este é um MVP gratuito, sem suporte oficial.

========================================
TXT;
    }

    private function buildWixEmbedContent(string $affiliateLink): string
    {
        $affiliateLinkEscaped = htmlspecialchars($affiliateLink, ENT_QUOTES, 'UTF-8');

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
  <!--
    IMPORTANTE: Substitua "SUA_URL_AQUI" pela URL onde você hospedou o arquivo
    landingpage/index.html. Pode ser:
      - GitHub Pages
      - Vercel
      - Netlify
      - Sua hospedagem comum
  -->
  <iframe
    src="SUA_URL_AQUI/landingpage/index.html"
    width="100%"
    height="100%"
    frameborder="0"
    allowfullscreen>
  </iframe>
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

Este pacote contém 2 itens:
  1. landingpage/index.html  -> O clone da página com seus CTAs
  2. wix-embed.html          -> Página HTML que faz embed via iframe

PASSO A PASSO:
--------------

OPÇÃO A — Hospedar a landing page fora do Wix e fazer embed:

  1. Pegue o arquivo landingpage/index.html
  2. Suba para um host gratuito:
     - Vercel: https://vercel.com (recomendado, rápido)
     - Netlify: https://netlify.com
     - GitHub Pages
  3. Abra o arquivo wix-embed.html e troque "SUA_URL_AQUI"
     pela URL pública onde ficou hospedado o index.html
  4. No painel do Wix:
     - Adicione um elemento "HTML iframe" ou "Incorporar"
     - Cole o conteúdo do wix-embed.html
     - Ou use o código direto do iframe apontando para sua URL

OPÇÃO B — Upload direto no Wix (limitado):

  O Wix não permite upload de HTML puro diretamente. Você precisa
  usar um elemento "HTML Code" dentro do Editor Wix e colar o
  iframe manualmente.

Seu link de afiliado configurado:
  {$link}

========================================
TXT;
    }

    private function buildHostingerInstructions(array $metadata): string
    {
        $link = $metadata['affiliate_link'] ?? '(não definido)';

        return <<<TXT
========================================
COMO SUBIR NO HOSTINGER
========================================

Este pacote contém a estrutura pronta para upload:
  - public_html/index.html
  - public_html/.htaccess

MÉTODO 1 — File Manager (Mais fácil):
--------------------------------------
  1. Acesse hpanel.hostinger.com
  2. Vá em "Gerenciador de Arquivos" (File Manager)
  3. Navegue até a pasta public_html do seu domínio
  4. Faça upload de TODOS os arquivos da pasta public_html deste ZIP
  5. Pronto! Acesse seu domínio no navegador

MÉTODO 2 — FTP (Mais avançado):
--------------------------------
  1. Pegue as credenciais FTP no painel da Hostinger
     (Avançado > Acesso FTP)
  2. Use um cliente FTP como FileZilla
  3. Conecte-se e navegue até public_html
  4. Faça upload da pasta public_html deste ZIP

MÉTODO 3 — Subdomínio (recomendado para teste):
------------------------------------------------
  1. Crie um subdomínio (ex: lp.seusite.com)
  2. Aponte para uma pasta separada
  3. Suba os arquivos lá
  4. Teste antes de colocar no domínio principal

Seu link de afiliado configurado:
  {$link}

IMPORTANTE: O arquivo .htaccess já vem com configurações
básicas de cache e segurança. Pode customizar se precisar.

========================================
TXT;
    }

    private function buildHtaccess(): string
    {
        return <<<HTACCESS
# Basic cache and compression
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json
</IfModule>

<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 1 month"
  ExpiresByType application/javascript "access plus 1 month"
  ExpiresByType image/png "access plus 1 year"
  ExpiresByType image/jpg "access plus 1 year"
  ExpiresByType image/jpeg "access plus 1 year"
  ExpiresByType image/webp "access plus 1 year"
  ExpiresByType image/svg+xml "access plus 1 year"
</IfModule>

# Charset
AddDefaultCharset UTF-8

# Disable directory listing
Options -Indexes

HTACCESS;
    }

    private function count(array $arr): int
    {
        return count($arr);
    }
}
