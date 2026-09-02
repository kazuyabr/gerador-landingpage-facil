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
        return self::getRootDir() . '/public';
    }

    public static function getJobsDir(): string
    {
        if (getenv('VERCEL') !== false) {
            return '/tmp/jobs';
        }
        return self::getRootDir() . '/jobs';
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
