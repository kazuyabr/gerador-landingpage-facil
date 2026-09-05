<?php

class AssetProcessor
{
    private string $sourceDomain;
    private string $baseUrl;
    private array $downloaded = [];
    private array $cssMap = [];
    private int $timeout = 15;
    private int $maxTotalTime = 120;

    public function __construct(string $sourceDomain)
    {
        $this->sourceDomain = $sourceDomain;
        $this->baseUrl = "https://{$sourceDomain}";
    }

    public function processHtml(string $html): string
    {
        $html = $this->fixLazyLoading($html);
        $html = $this->downloadAndInlineCss($html);
        $html = $this->downloadAndInlineScripts($html);
        $html = $this->rewriteImageUrls($html);
        $html = $this->fixBackgroundImages($html);
        $html = $this->addFontAwesomeCdn($html);
        $html = $this->addGoogleFontsCdn($html);
        return $html;
    }

    private function fixLazyLoading(string $html): string
    {
        $patterns = [
            '/data-original-src="([^"]+)"/i',
            '/data-lazy-src="([^"]+)"/i',
            '/data-src="([^"]+)"/i',
            '/data-srcset="([^"]+)"/i',
        ];

        foreach ($patterns as $pattern) {
            $html = preg_replace_callback($pattern, function($m) {
                $url = $this->resolveUrl($m[1]);
                $attr = str_replace('-src', 'src', $m[0]);
                $attr = str_replace('data-', '', $attr);
                return "{$attr}=\"{$url}\"";
            }, $html);
        }

        $html = preg_replace('/loading="lazy"/i', 'loading="eager"', $html);

        $html = preg_replace('/<style[^>]*>[^<]*e-con\.e-parent[^<]*background-image\s*:\s*none[^<]*<\/style>/is', '', $html);

        return $html;
    }

    private function downloadAndInlineCss(string $html): string
    {
        preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);

        if (empty($matches[1])) return $html;

        $combinedCss = '';
        $seen = [];

        foreach ($matches[1] as $i => $cssUrl) {
            $cssUrl = $this->resolveUrl($cssUrl);
            if (isset($seen[$cssUrl])) continue;
            $seen[$cssUrl] = true;

            $cssContent = $this->fetchUrl($cssUrl);
            if ($cssContent === null) continue;

            $cssDir = dirname(parse_url($cssUrl, PHP_URL_PATH));
            $cssContent = $this->rewriteCssUrls($cssContent, $cssDir);
            $combinedCss .= "\n/* {$cssUrl} */\n{$cssContent}\n";
        }

        if (empty($combinedCss)) return $html;

        $combinedCss = $this->minifyCss($combinedCss);

        $html = preg_replace('/<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\'][^"\']+["\'][^>]*>\s*/i', '', $html);

        $html = preg_replace('/<\/head>/i', "<style data-cloned=\"true\">\n{$combinedCss}\n</style>\n</head>", $html, 1);

        return $html;
    }

    private function rewriteCssUrls(string $css, string $cssDir): string
    {
        $css = preg_replace_callback('/url\(\s*[\'"]?([^\'")\s]+)[\'"]?\s*\)/i', function($m) use ($cssDir) {
            $url = $m[1];
            if (strpos($url, 'data:') === 0) return $m[0];

            if (strpos($url, '//') === 0) {
                $url = 'https:' . $url;
            } elseif (strpos($url, '/') === 0) {
                $url = $this->baseUrl . $url;
            } elseif (strpos($url, 'http') !== 0) {
                $url = $this->baseUrl . $cssDir . '/' . $url;
            }

            $localPath = $this->downloadAsset($url);
            if ($localPath) {
                return "url('{$localPath}')";
            }
            return $m[0];
        }, $css);

        $css = preg_replace_callback('/@import\s+[\'"]([^\'"]+)[\'"]/i', function($m) use ($cssDir) {
            $url = $this->resolveUrl($m[1]);
            $content = $this->fetchUrl($url);
            if ($content) {
                $importDir = dirname(parse_url($url, PHP_URL_PATH));
                $content = $this->rewriteCssUrls($content, $importDir);
                return $content;
            }
            return $m[0];
        }, $css);

        return $css;
    }

    private function downloadAndInlineScripts(string $html): string
    {
        preg_match_all('/<script[^>]+src=["\']([^"\']+)["\'][^>]*>\s*<\/script>/i', $html, $matches);

        if (empty($matches[0])) return $html;

        $skipDomains = [
            'google-analytics.com', 'googletagmanager.com', 'google.com', 'googleapis.com',
            'facebook.net', 'facebook.com', 'doubleclick.net',
            'clarity.ms', 'analytics.tiktok.com', 'tiktok.com',
            'cloudflareinsights.com', 'hotjar.com',
        ];

        foreach ($matches[0] as $i => $fullTag) {
            $src = $matches[1][$i];
            $srcUrl = $this->resolveUrl($src);
            $host = parse_url($srcUrl, PHP_URL_HOST) ?? '';

            $skip = false;
            foreach ($skipDomains as $domain) {
                if (str_contains($host, $domain)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $content = $this->fetchUrl($srcUrl);
            if ($content && strlen($content) < 500000) {
                $inline = "<script data-cloned=\"true\">\n{$content}\n</script>";
                $html = str_replace($fullTag, $inline, $html);
            }
        }

        return $html;
    }

    private function rewriteImageUrls(string $html): string
    {
        $html = preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\']/i', function($m) {
            $url = $m[1];
            if (strpos($url, 'data:') === 0 || strpos($url, '#') === 0) return $m[0];
            $resolved = $this->resolveUrl($url);
            $local = $this->downloadAsset($resolved);
            if ($local) {
                return str_replace($m[1], $local, $m[0]);
            }
            return $m[0];
        }, $html);

        $html = preg_replace_callback('/srcset=["\']([^"\']+)["\']/i', function($m) {
            $srcset = $m[1];
            $parts = preg_split('/\s*,\s*/', $srcset);
            $newParts = [];
            foreach ($parts as $part) {
                $part = trim($part);
                if (preg_match('/^(\S+)(\s+\S+)?$/', $part, $sm)) {
                    $url = $this->resolveUrl($sm[1]);
                    $local = $this->downloadAsset($url);
                    if ($local) {
                        $newParts[] = $local . (isset($sm[2]) ? $sm[2] : '');
                    } else {
                        $newParts[] = $part;
                    }
                }
            }
            return 'srcset="' . implode(', ', $newParts) . '"';
        }, $html);

        return $html;
    }

    private function fixBackgroundImages(string $html): string
    {
        $html = preg_replace_callback('/style=["\']([^"\']*)background-image\s*:\s*url\(\s*[\'"]?([^\'")\s]+)[\'"]?\s*\)/i', function($m) {
            $url = $this->resolveUrl($m[2]);
            $local = $this->downloadAsset($url);
            if ($local) {
                return str_replace($m[2], $local, $m[0]);
            }
            return $m[0];
        }, $html);

        return $html;
    }

    private function addFontAwesomeCdn(string $html): string
    {
        $html = preg_replace('#<link[^>]+href=["\'][^"\']*font-awesome[^"\']*["\'][^>]*/?>#i', '', $html);
        $html = preg_replace('#<link[^>]+href=["\'][^"\']*fontawesome[^"\']*["\'][^>]*/?>#i', '', $html);
        $html = preg_replace('#<link[^>]+href=["\'][^"\']*\/all\.min\.css[^"\']*["\'][^>]*/?>#i', '', $html);

        $cdn = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">';
        $html = preg_replace('/<\/head>/i', "{$cdn}\n</head>", $html, 1);

        return $html;
    }

    private function addGoogleFontsCdn(string $html): string
    {
        preg_match_all('/fonts\.googleapis\.com\/css[^"\']*/i', $html, $matches);
        if (!empty($matches[0])) {
            $fontsLink = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
            $html = preg_replace('/<\/head>/i', "{$fontsLink}\n</head>", $html, 1);
        }

        return $html;
    }

    private function resolveUrl(string $url): string
    {
        $url = trim($url);
        if (empty($url)) return $url;
        if (strpos($url, 'data:') === 0) return $url;
        if (strpos($url, '//') === 0) return 'https:' . $url;
        if (strpos($url, 'http') === 0) return $url;
        if (strpos($url, '/') === 0) return $this->baseUrl . $url;
        return $this->baseUrl . '/' . $url;
    }

    private function fetchUrl(string $url): ?string
    {
        if (isset($this->downloaded[$url])) {
            return $this->downloaded[$url];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($content === false || $httpCode >= 400) {
            return null;
        }

        $this->downloaded[$url] = $content;
        return $content;
    }

    private function downloadAsset(string $url): ?string
    {
        if (isset($this->cssMap[$url])) {
            return $this->cssMap[$url];
        }

        $content = $this->fetchUrl($url);
        if ($content === null) return null;

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $mimeMap = [
            'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject', 'otf' => 'font/otf',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
        ];

        $mime = $mimeMap[$ext] ?? 'application/octet-stream';
        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($content);

        $this->cssMap[$url] = $dataUri;
        return $dataUri;
    }

    private function minifyCss(string $css): string
    {
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
        $css = preg_replace('/;}/', '}', $css);
        return trim($css);
    }

    public function getDownloadedCount(): int
    {
        return count($this->downloaded);
    }

    public function getCssMap(): array
    {
        return $this->cssMap;
    }
}
