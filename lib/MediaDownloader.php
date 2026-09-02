<?php

class MediaDownloader
{
    private string $tmpDir;
    private string $mediaDir;
    private array $mapping = [];
    private int $timeout = 10;
    private int $maxTotalTime = 60;
    private int $maxSize = 50 * 1024 * 1024;

    public function __construct(string $jobId)
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'media_' . $jobId;
        $this->mediaDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'media';
        if (!is_dir($this->mediaDir)) {
            mkdir($this->mediaDir, 0777, true);
        }
        if (!is_dir($this->mediaDir . DIRECTORY_SEPARATOR . 'img')) {
            mkdir($this->mediaDir . DIRECTORY_SEPARATOR . 'img', 0777, true);
        }
        if (!is_dir($this->mediaDir . DIRECTORY_SEPARATOR . 'css')) {
            mkdir($this->mediaDir . DIRECTORY_SEPARATOR . 'css', 0777, true);
        }
        if (!is_dir($this->mediaDir . DIRECTORY_SEPARATOR . 'fonts')) {
            mkdir($this->mediaDir . DIRECTORY_SEPARATOR . 'fonts', 0777, true);
        }
    }

    public function downloadAndMap(string $html, string $sourceDomain): array
    {
        $urls = $this->extractUrls($html);
        $uniqueUrls = array_unique($urls);
        $filtered = array_filter($uniqueUrls, function ($url) use ($sourceDomain) {
            if (empty($url) || preg_match('#^javascript:#i', $url) || preg_match('#^data:#i', $url)) {
                return false;
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return false;
            }
            if ($sourceDomain !== '' && !str_contains($url, $sourceDomain)) {
                return false;
            }
            return true;
        });

        $toDownload = [];
        foreach ($filtered as $url) {
            $category = $this->categorize($url);
            $localPath = $this->urlToLocal($url, $category);
            $toDownload[$url] = $localPath;
        }

        $this->parallelDownload($toDownload);

        foreach ($toDownload as $url => $localPath) {
            $fullPath = $this->mediaDir . DIRECTORY_SEPARATOR . $localPath;
            if (file_exists($fullPath) && filesize($fullPath) > 0) {
                $this->mapping[$url] = 'media/' . $localPath;
            } else {
                $this->mapping[$url] = $url;
            }
        }

        return $this->mapping;
    }

    public function updateHtml(string $html): string
    {
        foreach ($this->mapping as $original => $local) {
            if ($original === $local) {
                continue;
            }
            $escaped = preg_quote($original, '/');
            $html = preg_replace('#' . $escaped . '#', $local, $html);
        }
        return $html;
    }

    public function getMediaDir(): string
    {
        return $this->mediaDir;
    }

    public function getMapping(): array
    {
        return $this->mapping;
    }

    private function extractUrls(string $html): array
    {
        $urls = [];

        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            $urls = array_merge($urls, $m[1]);
        }

        if (preg_match_all('/srcset=["\']([^"\']+)["\']/i', $html, $m)) {
            foreach ($m[1] as $srcset) {
                $parts = preg_split('/\s*,\s*/', $srcset);
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (preg_match('/^(\S+)/', $part, $sm)) {
                        $urls[] = $sm[1];
                    }
                }
            }
        }

        if (preg_match_all('/<link[^>]+href=["\']([^"\']+\.(?:css|woff2?|ttf|eot)(?:\?[^"\']*)?)["\']/i', $html, $m)) {
            $urls = array_merge($urls, $m[1]);
        }

        if (preg_match_all('/<script[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            foreach ($m[1] as $src) {
                if (str_contains($src, 'csjdigital.com.br') || str_contains($src, 'wp-content') || str_contains($src, 'wp-includes')) {
                    $urls[] = $src;
                }
            }
        }

        if (preg_match_all('/<link[^>]+rel=["\'](?:icon|shortcut icon|apple-touch-icon)["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $m)) {
            $urls = array_merge($urls, $m[1]);
        }

        if (preg_match_all('/<meta[^>]+content=["\']([^"\']+\.(?:png|jpg|jpeg|gif|webp|ico)(?:\?[^"\']*)?)["\']/i', $html, $m)) {
            foreach ($m[1] as $metaUrl) {
                if (preg_match('#^https?://#i', $metaUrl)) {
                    $urls[] = $metaUrl;
                }
            }
        }

        if (preg_match_all('/url\(["\']?([^"\')\s]+)["\']?\)/i', $html, $m)) {
            foreach ($m[1] as $cssUrl) {
                if (preg_match('#^https?://#i', $cssUrl)) {
                    $urls[] = $cssUrl;
                }
            }
        }

        if (preg_match_all('/background(?:-image)?\s*:\s*url\(["\']?([^"\')\s]+)["\']?\)/i', $html, $m)) {
            foreach ($m[1] as $bgUrl) {
                if (preg_match('#^https?://#i', $bgUrl)) {
                    $urls[] = $bgUrl;
                }
            }
        }

        if (preg_match_all('/<source[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            $urls = array_merge($urls, $m[1]);
        }

        return $urls;
    }

    private function categorize(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, ['css'])) {
            return 'css';
        }
        if (in_array($ext, ['woff', 'woff2', 'ttf', 'eot', 'otf'])) {
            return 'fonts';
        }
        return 'img';
    }

    private function urlToLocal(string $url, string $category): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null) {
            $path = '/' . md5($url);
        }

        $basename = basename($path);
        if ($basename === '' || $basename === '/') {
            $basename = md5($url) . '.bin';
        }

        $basename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $basename);

        $dir = $category;
        $counter = 0;
        $candidate = $basename;
        $fullDir = $this->mediaDir . DIRECTORY_SEPARATOR . $dir;

        while (file_exists($fullDir . DIRECTORY_SEPARATOR . $candidate)) {
            $counter++;
            $name = pathinfo($basename, PATHINFO_FILENAME);
            $ext = pathinfo($basename, PATHINFO_EXTENSION);
            $candidate = $name . '_' . $counter . ($ext !== '' ? '.' . $ext : '');
        }

        return $dir . DIRECTORY_SEPARATOR . $candidate;
    }

    private function parallelDownload(array $urlToLocal): void
    {
        if (empty($urlToLocal)) {
            return;
        }

        $mh = curl_multi_init();
        $handles = [];
        $startTime = time();

        foreach ($urlToLocal as $url => $localPath) {
            if ((time() - $startTime) > $this->maxTotalTime) {
                break;
            }

            $fullPath = $this->mediaDir . DIRECTORY_SEPARATOR . $localPath;
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_ENCODING => '',
                CURLOPT_FILE => fopen($fullPath, 'w'),
            ]);

            $handles[$url] = $ch;
            curl_multi_add_handle($mh, $ch);
        }

        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh, 1);
            }
        } while ($active && $status === CURLM_OK);

        foreach ($handles as $url => $ch) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);

            $localPath = $urlToLocal[$url];
            $fullPath = $this->mediaDir . DIRECTORY_SEPARATOR . $localPath;

            if ($httpCode < 200 || $httpCode >= 400 || $error !== '' || $size > $this->maxSize || $size <= 0) {
                @unlink($fullPath);
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
    }

    public function getTmpDir(): string
    {
        return $this->tmpDir;
    }

    public function cleanup(): void
    {
        if (is_dir($this->tmpDir)) {
            $it = new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $fileinfo) {
                if ($fileinfo->isDir()) {
                    @rmdir($fileinfo->getRealPath());
                } else {
                    @unlink($fileinfo->getRealPath());
                }
            }
            @rmdir($this->tmpDir);
        }
    }
}
