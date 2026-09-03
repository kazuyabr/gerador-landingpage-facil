<?php

class Cloner
{
    private array $checkoutDomains = [
        'hotmart',
        'kiwify',
        'eduzz',
        'braip',
        'monetizze',
        'cartpanda',
        'payt.com.br',
        'ticto',
        'nutror',
        'perfectpay',
        'lastlink',
        'mindminers',
        'vendd',
        'klickmembers',
        'membertoo',
    ];

    private array $ctaClasses = [
        'elementor-button',
        'cta',
        'btn-primary',
        'btn-cta',
        'buy-button',
        'checkout-btn',
    ];

    private string $sourceOrigin = '';
    private string $sourceBaseUrl = '';

    public function process(string $html, string $affiliateLink, string $mode = 'paste'): array
    {
        $originalSize = strlen($html);

        $this->sourceOrigin = $this->extractOrigin($html);
        $this->sourceBaseUrl = $this->extractBaseUrl($html);

        $hasDoctype = stripos($html, '<!doctype') !== false || stripos($html, '<!DOCTYPE') !== false;
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loadSource = $html;
        if (!$hasDoctype) {
            $loadSource = '<?xml encoding="UTF-8">' . $html;
        }
        $dom->loadHTML($loadSource, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $ctasFound = $this->detectAndReplace($xpath, $affiliateLink);

        $this->fixLazyLoadCss($dom);
        $this->removeCspHeaders($dom);
        $this->localizeAllResources($dom);

        $processedHtml = $dom->saveHTML();
        $processedHtml = preg_replace('/^<\?xml[^>]*\?>\s*/i', '', $processedHtml);
        $processedHtml = preg_replace('/^<!doctype[^>]*>/i', '<!DOCTYPE html>', $processedHtml);
        $processedHtml = preg_replace('/[\x{FEFF}]/u', '', $processedHtml);

        return [
            'html' => $processedHtml,
            'original_size' => $originalSize,
            'processed_size' => strlen($processedHtml),
            'ctas' => $ctasFound,
            'mode' => $mode,
            'affiliate_link' => $affiliateLink,
            'source_domain' => $this->extractSourceDomain($html),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function detectAndReplace(DOMXPath $xpath, string $affiliateLink): array
    {
        $ctas = [];
        $processedNodes = [];

        $allAnchors = $xpath->query('//a[@href]');
        if ($allAnchors === false) {
            return $ctas;
        }

        foreach ($allAnchors as $node) {
            $href = $node->getAttribute('href');
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent));

            if (empty($href) || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            $isExternalCheckout = $this->isCheckoutLink($href);
            $isCtaStyle = $this->looksLikeCta($node);
            $isAnchorOnly = str_starts_with($href, '#');

            if (!$isExternalCheckout && !$isCtaStyle) {
                continue;
            }

            if ($isAnchorOnly && !$isCtaStyle) {
                continue;
            }

            $nodeId = spl_object_id($node);
            if (in_array($nodeId, $processedNodes, true)) {
                continue;
            }

            $ctas[] = [
                'old_href' => $href,
                'new_href' => $affiliateLink,
                'text' => $text !== '' ? $text : '(sem texto)',
                'class' => $node->getAttribute('class'),
                'type' => $this->isCheckoutLink($href) ? 'checkout' : 'button',
            ];

            $node->setAttribute('href', $affiliateLink);
            $node->setAttribute('data-cloned-cta', 'true');
            $node->setAttribute('data-original-href', $href);

            $processedNodes[] = $nodeId;
        }

        $allButtons = $xpath->query('//button');
        if ($allButtons !== false) {
            foreach ($allButtons as $node) {
                if (!$this->looksLikeCta($node)) {
                    continue;
                }
                $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
                $class = $node->getAttribute('class');
                $ctas[] = [
                    'old_href' => '(button)',
                    'new_href' => $affiliateLink,
                    'text' => $text !== '' ? $text : '(sem texto)',
                    'class' => $class,
                    'type' => 'button-no-href',
                ];
                $node->setAttribute('onclick', "window.location.href='" . addslashes($affiliateLink) . "'");
                $node->setAttribute('data-cloned-cta', 'true');
            }
        }

        return $ctas;
    }

    private function isCheckoutLink(string $href): bool
    {
        $hrefLower = strtolower($href);
        foreach ($this->checkoutDomains as $domain) {
            if (str_contains($hrefLower, $domain)) {
                return true;
            }
        }
        return false;
    }

    private function looksLikeCta(DOMNode $node): bool
    {
        $class = strtolower($node->getAttribute('class') ?? '');
        foreach ($this->ctaClasses as $ctaClass) {
            if (str_contains($class, $ctaClass)) {
                return true;
            }
        }

        $ctaKeywords = [
            'comprar', 'quero', 'adquirir', 'garantir', 'entrar',
            'iniciar', 'começar', 'acesso', 'aproveitar', 'resgatar',
            'buy', 'get', 'start', 'join', 'access', 'claim',
        ];
        $text = strtolower(trim($node->textContent));
        foreach ($ctaKeywords as $kw) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function fixLazyLoadCss(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);

        $styles = $xpath->query('//style');
        if ($styles !== false) {
            foreach ($styles as $style) {
                $content = $style->textContent ?? '';
                if (preg_match('/e-con\.e-parent.*background-image\s*:\s*none/is', $content)) {
                    $style->parentNode->removeChild($style);
                }
            }
        }

        $heads = $xpath->query('//head');
        if ($heads === false || $heads->length === 0) {
            return;
        }
        $head = $heads->item(0);

        $fix = $dom->createElement('style');
        $fix->setAttribute('data-cloned-fix', 'true');
        $fix->appendChild($dom->createTextNode(<<<'CSS'
.e-con.e-parent, .e-con.e-parent * { background-image: revert !important; }
.e-con { background-image: revert !important; }
.e-con * { background-image: revert !important; }
CSS
        ));
        $head->appendChild($fix);

        $lazyImgs = $xpath->query('//img[@loading="lazy"]');
        if ($lazyImgs !== false) {
            foreach ($lazyImgs as $img) {
                $img->setAttribute('loading', 'eager');
            }
        }

        $dataSrcImgs = $xpath->query('//img[@data-src]');
        if ($dataSrcImgs !== false) {
            foreach ($dataSrcImgs as $img) {
                $src = $img->getAttribute('data-src');
                if ($src !== '') {
                    $img->setAttribute('src', $src);
                }
                $img->removeAttribute('data-src');
                $img->removeAttribute('data-srcset');
                $img->removeAttribute('data-sizes');
                $img->removeAttribute('loading');
            }
        }

        $allElements = $xpath->query('//*[@style]');
        if ($allElements !== false) {
            foreach ($allElements as $el) {
                $style = $el->getAttribute('style');
                if (preg_match('/background-image\s*:\s*none/i', $style)) {
                    $newStyle = preg_replace('/background-image\s*:\s*none\s*(!important)?\s*;?\s*/i', '', $style);
                    if (trim($newStyle) === '') {
                        $el->removeAttribute('style');
                    } else {
                        $el->setAttribute('style', trim($newStyle));
                    }
                }
            }
        }

        $scripts = $xpath->query('//script');
        if ($scripts !== false) {
            foreach ($scripts as $script) {
                $src = $script->getAttribute('src');
                $content = $script->textContent ?? '';
                if (str_contains($src, 'lazyload') || str_contains($src, 'lazy-load') ||
                    str_contains($content, 'lazyloadRunObserver') || str_contains($content, 'e-lazyloaded')) {
                    $script->parentNode->removeChild($script);
                }
            }
        }
    }

    private function removeCspHeaders(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);

        $metas = $xpath->query('//meta[@http-equiv]');
        if ($metas !== false) {
            foreach ($metas as $meta) {
                $equiv = strtolower($meta->getAttribute('http-equiv'));
                if (in_array($equiv, ['content-security-policy', 'x-frame-options'])) {
                    $meta->parentNode->removeChild($meta);
                }
            }
        }

        $referrers = $xpath->query('//meta[@name="referrer"]');
        if ($referrers !== false) {
            foreach ($referrers as $meta) {
                $meta->parentNode->removeChild($meta);
            }
        }

        $heads = $xpath->query('//head');
        if ($heads !== false && $heads->length > 0) {
            $head = $heads->item(0);
            $referrerMeta = $dom->createElement('meta');
            $referrerMeta->setAttribute('name', 'referrer');
            $referrerMeta->setAttribute('content', 'no-referrer-when-downgrade');
            $head->insertBefore($referrerMeta, $head->firstChild);
        }

        $scripts = $xpath->query('//script');
        if ($scripts !== false) {
            foreach ($scripts as $script) {
                $content = $script->textContent ?? '';
                if (str_contains($content, 'document.referrer') || str_contains($content, 'navigator.sendBeacon')) {
                    $script->parentNode->removeChild($script);
                }
            }
        }
    }

    private function localizeAllResources(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);

        $this->localizeCssLinks($dom, $xpath);
        $this->localizeImages($dom, $xpath);
        $this->localizeFonts($dom, $xpath);
        $this->localizeInlineStyles($dom, $xpath);
        $this->localizeStyleBlocks($dom, $xpath);
        $this->localizeScripts($dom, $xpath);
        $this->localizeVideoAudio($dom, $xpath);
    }

    private function localizeCssLinks(DOMDocument $dom, DOMXPath $xpath): void
    {
        $links = $xpath->query('//link[@rel="stylesheet" and @href]');
        if ($links === false || $links->length === 0) {
            return;
        }

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (!preg_match('#^https?://#i', $href)) {
                continue;
            }

            $cssContent = $this->httpGet($href, 'text/css,*/*;q=0.1');
            if ($cssContent === false) {
                continue;
            }

            $cssContent = $this->rewriteCssUrls($cssContent, $href);

            $style = $dom->createElement('style');
            $style->setAttribute('data-localized', 'true');
            $style->setAttribute('data-original-href', $href);
            $style->appendChild($dom->createTextNode($cssContent));
            $link->parentNode->insertBefore($style, $link);
            $link->parentNode->removeChild($link);
        }
    }

    private function rewriteCssUrls(string $css, string $cssUrl): string
    {
        $baseUrl = rtrim(preg_replace('#/[^/]*$#', '/', $cssUrl), '/') . '/';
        $cssOrigin = parse_url($cssUrl, PHP_URL_HOST);

        $css = preg_replace_callback(
            '/url\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i',
            function ($matches) use ($baseUrl, $cssOrigin) {
                $url = trim($matches[1]);

                if (preg_match('#^data:#i', $url)) {
                    return $matches[0];
                }

                if (!preg_match('#^https?://#i', $url)) {
                    if (str_starts_with($url, '//')) {
                        $url = 'https:' . $url;
                    } elseif (str_starts_with($url, '/')) {
                        $url = 'https://' . $cssOrigin . $url;
                    } else {
                        $url = $baseUrl . $url;
                    }
                }

                $path = parse_url($url, PHP_URL_PATH) ?? $url;
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                $mediaExts = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'avif'];
                $fontExts = ['woff2', 'woff', 'ttf', 'eot', 'otf'];

                if (in_array($ext, $fontExts)) {
                    $data = $this->httpGet($url);
                    if ($data !== false) {
                        $mime = $this->getFontMime($ext);
                        return "url(data:{$mime};base64," . base64_encode($data) . ")";
                    }
                } elseif (in_array($ext, $mediaExts)) {
                    $data = $this->httpGet($url);
                    if ($data !== false) {
                        $mime = $this->getImageMime($ext);
                        return "url(data:{$mime};base64," . base64_encode($data) . ")";
                    }
                }

                return $matches[0];
            },
            $css
        );

        return $css;
    }

    private function localizeImages(DOMDocument $dom, DOMXPath $xpath): void
    {
        $images = $xpath->query('//img');
        if ($images === false) {
            return;
        }

        foreach ($images as $img) {
            foreach (['src', 'data-src', 'data-lazy-src'] as $attr) {
                $val = $img->getAttribute($attr);
                if (empty($val) || preg_match('#^data:#i', $val)) {
                    continue;
                }
                $fullUrl = $this->resolveUrl($val);
                if ($fullUrl !== '') {
                    $data = $this->httpGet($fullUrl);
                    if ($data !== false) {
                        $ext = strtolower(pathinfo(parse_url($fullUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
                        $mime = $this->getImageMime($ext);
                        $img->setAttribute($attr, "data:{$mime};base64," . base64_encode($data));
                    }
                }
            }

            $srcset = $img->getAttribute('srcset') ?: $img->getAttribute('data-srcset');
            if ($srcset !== '') {
                $newSrcset = $this->localizeSrcset($srcset);
                $img->setAttribute('srcset', $newSrcset);
                $img->removeAttribute('data-srcset');
            }
        }
    }

    private function localizeSrcset(string $srcset): string
    {
        $parts = array_map('trim', explode(',', $srcset));
        $result = [];

        foreach ($parts as $part) {
            $pieces = preg_split('/\s+/', $part);
            if (count($pieces) >= 1) {
                $url = $pieces[0];
                if (!preg_match('#^data:#i', $url) && preg_match('#^https?://#i', $url)) {
                    $data = $this->httpGet($url);
                    if ($data !== false) {
                        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                        $mime = $this->getImageMime($ext);
                        $pieces[0] = "data:{$mime};base64," . base64_encode($data);
                    }
                }
            }
            $result[] = implode(' ', $pieces);
        }

        return implode(', ', $result);
    }

    private function localizeFonts(DOMDocument $dom, DOMXPath $xpath): void
    {
        $links = $xpath->query('//link[@rel="stylesheet" and @href]');
        if ($links === false || $links->length === 0) {
            return;
        }

        $fontExts = ['woff2', 'woff', 'ttf', 'eot', 'otf', 'svg'];
        $cssBlocks = [];

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (!preg_match('#^https?://#i', $href)) {
                continue;
            }

            $cssContent = $this->httpGet($href, 'text/css,*/*;q=0.1');
            if ($cssContent === false) {
                continue;
            }

            $baseUrl = rtrim(preg_replace('#/[^/]*$#', '/', $href), '/') . '/';
            $fontUrls = [];

            preg_match_all(
                '/url\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i',
                $cssContent,
                $urlMatches,
                PREG_SET_ORDER
            );

            foreach ($urlMatches as $m) {
                $url = trim($m[1]);
                if (preg_match('#^data:#i', $url)) {
                    continue;
                }
                $path = parse_url($url, PHP_URL_PATH) ?? $url;
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (!in_array($ext, $fontExts, true)) {
                    continue;
                }
                $fullUrl = preg_match('#^https?://#i', $url) ? $url : $baseUrl . $url;
                $fontUrls[$m[0]] = ['url' => $fullUrl, 'ext' => $ext];
            }

            if (!empty($fontUrls)) {
                $cssBlocks[] = [
                    'link' => $link,
                    'css' => $cssContent,
                    'fonts' => $fontUrls,
                ];
            }
        }

        if (empty($cssBlocks)) {
            return;
        }

        $allFontUrls = [];
        foreach ($cssBlocks as $block) {
            foreach ($block['fonts'] as $original => $info) {
                $allFontUrls[$info['url']] = $info['ext'];
            }
        }

        $fontData = $this->httpGetMulti($allFontUrls);

        foreach ($cssBlocks as $block) {
            $cssContent = $block['css'];
            foreach ($block['fonts'] as $original => $info) {
                $data = $fontData[$info['url']] ?? false;
                if ($data === false) {
                    continue;
                }
                $encoded = base64_encode($data);
                $mime = $this->getFontMime($info['ext']);
                $replacement = "url(data:{$mime};base64,{$encoded})";
                $cssContent = str_replace($original, $replacement, $cssContent);
            }

            $style = $dom->createElement('style');
            $style->setAttribute('data-fonts-localized', 'true');
            $style->appendChild($dom->createTextNode($cssContent));
            $block['link']->parentNode->insertBefore($style, $block['link']);
            $block['link']->parentNode->removeChild($block['link']);
        }
    }

    private function localizeInlineStyles(DOMDocument $dom, DOMXPath $xpath): void
    {
        $elements = $xpath->query('//*[@style]');
        if ($elements === false) {
            return;
        }

        foreach ($elements as $el) {
            $style = $el->getAttribute('style');
            $newStyle = $this->rewriteCssUrls($style, $this->sourceBaseUrl . '/');
            if ($newStyle !== $style) {
                $el->setAttribute('style', $newStyle);
            }
        }
    }

    private function localizeStyleBlocks(DOMDocument $dom, DOMXPath $xpath): void
    {
        $styles = $xpath->query('//style[not(@data-localized) and not(@data-fonts-localized) and not(@data-cloned-fix)]');
        if ($styles === false) {
            return;
        }

        foreach ($styles as $style) {
            $content = $style->textContent ?? '';
            if ($content !== '') {
                $newContent = $this->rewriteCssUrls($content, $this->sourceBaseUrl . '/');
                if ($newContent !== $content) {
                    $style->textContent = $newContent;
                }
            }
        }
    }

    private function localizeScripts(DOMDocument $dom, DOMXPath $xpath): void
    {
        $scripts = $xpath->query('//script[@src]');
        if ($scripts === false) {
            return;
        }

        foreach ($scripts as $script) {
            $src = $script->getAttribute('src');
            if (empty($src) || preg_match('#^data:#i', $src)) {
                continue;
            }
            $fullUrl = $this->resolveUrl($src);
            if ($fullUrl !== '') {
                $data = $this->httpGet($fullUrl, '*/*', 'application/javascript');
                if ($data !== false) {
                    $script->removeAttribute('src');
                    $script->textContent = $data;
                }
            }
        }
    }

    private function localizeVideoAudio(DOMDocument $dom, DOMXPath $xpath): void
    {
        $mediaEls = $xpath->query('//video | //audio | //source');
        if ($mediaEls === false) {
            return;
        }

        foreach ($mediaEls as $el) {
            foreach (['src', 'poster', 'data-src', 'data-lazy-src', 'data-poster'] as $attr) {
                $val = $el->getAttribute($attr);
                if (empty($val) || preg_match('#^data:#i', $val)) {
                    continue;
                }
                $fullUrl = $this->resolveUrl($val);
                if ($fullUrl !== '') {
                    $data = $this->httpGet($fullUrl);
                    if ($data !== false) {
                        $ext = strtolower(pathinfo(parse_url($fullUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
                        $mime = $this->getMediaMime($ext);
                        $el->setAttribute($attr, "data:{$mime};base64," . base64_encode($data));
                    }
                }
            }
        }
    }

    private function resolveUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if ($this->sourceOrigin !== '') {
            if (str_starts_with($url, '/')) {
                return 'https://' . $this->sourceOrigin . $url;
            }
            return $this->sourceBaseUrl . '/' . $url;
        }

        return '';
    }

    private function getFontMime(string $ext): string
    {
        return match ($ext) {
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'otf' => 'font/otf',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }

    private function getImageMime(string $ext): string
    {
        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'avif' => 'image/avif',
            default => 'application/octet-stream',
        };
    }

    private function getMediaMime(string $ext): string
    {
        return match ($ext) {
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    private function httpGetMulti(array $urlMap): array
    {
        if (empty($urlMap)) {
            return [];
        }

        $mh = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($urlMap as $url => $ext) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win6; x64) AppleWebKit/537.36',
                CURLOPT_HTTPHEADER => ['Accept: */*'],
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[(int) $ch] = ['url' => $url, 'handle' => $ch];
        }

        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh, 0.1);
            }
        } while ($active && $status === CURLM_OK);

        foreach ($handles as $info) {
            $data = curl_multi_getcontent($info['handle']);
            $code = curl_getinfo($info['handle'], CURLINFO_HTTP_CODE);
            $results[$info['url']] = ($data !== false && $code < 400 && strlen($data) > 0) ? $data : false;
            curl_multi_remove_handle($mh, $info['handle']);
            curl_close($info['handle']);
        }

        curl_multi_close($mh);
        return $results;
    }

    private function httpGet(string $url, string $accept = '*/*', string $expectedType = ''): string|false
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win6; x64) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER => [
                "Accept: {$accept}",
                'Accept-Encoding: identity',
            ],
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($data !== false && $code < 400 && strlen($data) > 0) ? $data : false;
    }

    private function extractOrigin(string $html): string
    {
        if (preg_match('#https?://([a-zA-Z0-9.-]+)#', $html, $m)) {
            return $m[1];
        }
        return '';
    }

    private function extractBaseUrl(string $html): string
    {
        if (preg_match('#(https?://[a-zA-Z0-9.-]+)#', $html, $m)) {
            return $m[1];
        }
        return '';
    }

    private function extractSourceDomain(string $html): string
    {
        if (preg_match('#https?://([a-zA-Z0-9.-]+)#', $html, $m)) {
            return $m[1];
        }
        return '';
    }

    public function fetchUrl(string $url): array
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'error' => 'URL invalida',
            ];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win6; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8',
            ],
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($html === false || $httpCode >= 400) {
            return [
                'success' => false,
                'error' => $error !== '' ? $error : "HTTP {$httpCode}",
            ];
        }

        return [
            'success' => true,
            'html' => $html,
            'http_code' => $httpCode,
        ];
    }
}
