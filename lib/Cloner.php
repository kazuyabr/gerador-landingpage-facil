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

    public function process(string $html, string $affiliateLink, string $mode = 'paste'): array
    {
        $originalSize = strlen($html);

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
                $class = $node->getAttribute('class');
                if (!$this->looksLikeCta($node)) {
                    continue;
                }
                $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
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

        $allElements = $xpath->query('//*[@style]');
        if ($allElements !== false) {
            foreach ($allElements as $el) {
                $style = $el->getAttribute('style');
                if (preg_match('/background-image\s*:\s*none/i', $style)) {
                    $newStyle = preg_replace('/background-image\s*:\s*none\s*;?/i', '', $style);
                    if (trim($newStyle) === '') {
                        $el->removeAttribute('style');
                    } else {
                        $el->setAttribute('style', trim($newStyle));
                    }
                }
            }
        }
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
                'error' => 'URL inválida',
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
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
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
