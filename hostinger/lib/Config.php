<?php

class Config
{
    private static ?string $rootDir = null;

    public static function getRootDir(): string
    {
        if (self::$rootDir !== null) {
            return self::$rootDir;
        }

        if (getenv('VERCEL') !== false) {
            self::$rootDir = dirname(__DIR__, 2);
        } elseif (getenv('DOCKER') !== false || is_dir('/app/public')) {
            self::$rootDir = '/app';
        } else {
            self::$rootDir = dirname(__DIR__);
        }

        return self::$rootDir;
    }

    public static function getPublicDir(): string
    {
        $root = self::getRootDir();
        if (is_dir($root . '/public')) {
            return $root . '/public';
        }
        return $root;
    }

    public static function getJobsDir(): string
    {
        if (getenv('VERCEL') !== false) {
            return '/tmp/jobs';
        }
        $root = self::getRootDir();
        $jobsDir = $root . '/jobs';
        if (!is_dir($jobsDir)) {
            mkdir($jobsDir, 0777, true);
        }
        return $jobsDir;
    }

    public static function getLibDir(): string
    {
        return self::getRootDir() . '/lib';
    }

    public static function isVercel(): bool
    {
        return getenv('VERCEL') !== false;
    }
}
