<?php
/**
 * Cron job para limpar jobs expirados
 * Configurar no crontab: 0 * * * * php /app/jobs/cleanup.php
 * Ou rodar manualmente: php jobs/cleanup.php
 */

$jobsDir = __DIR__;
$now = time();
$cleaned = 0;
$errors = 0;

$files = glob($jobsDir . '/*.json');
foreach ($files as $file) {
    $content = @file_get_contents($file);
    if ($content === false) {
        $errors++;
        continue;
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        @unlink($file);
        $cleaned++;
        continue;
    }

    if (isset($data['expires_at'])) {
        $expiresAt = strtotime($data['expires_at']);
        if ($expiresAt !== false && $expiresAt < $now) {
            if (@unlink($file)) {
                $cleaned++;
            } else {
                $errors++;
            }
        }
    }
}

$tmpDirs = glob(sys_get_temp_dir() . '/clone_*') ?: [];
$tmpDirs = array_merge($tmpDirs, glob(sys_get_temp_dir() . '/wix_*') ?: []);
$tmpDirs = array_merge($tmpDirs, glob(sys_get_temp_dir() . '/hostinger_*') ?: []);

foreach ($tmpDirs as $dir) {
    $mtime = filemtime($dir);
    if ($mtime !== false && ($now - $mtime) > 3600) {
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files2 = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files2 as $fileinfo) {
            if ($fileinfo->isDir()) {
                @rmdir($fileinfo->getRealPath());
            } else {
                @unlink($fileinfo->getRealPath());
            }
        }
        @rmdir($dir);
        @unlink($dir . '.zip');
    }
}

echo "Limpeza concluída: {$cleaned} jobs removidos, {$errors} erros\n";
