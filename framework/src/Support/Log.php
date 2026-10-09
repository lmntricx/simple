<?php

namespace Simple\Support;

use Throwable;

/**
 * Appends lines to storage/logs/simple.log.
 */
final class Log
{
    private static ?string $file = null;

    public static function useFile(string $file): void
    {
        self::$file = $file;
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function exception(Throwable $e): void
    {
        self::write('ERROR', sprintf(
            "%s: %s in %s:%d\n%s",
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        ));
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        if (self::$file === null) {
            error_log("[$level] $message");
            return;
        }

        $dir = dirname(self::$file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '',
        );

        @file_put_contents(self::$file, $line, FILE_APPEND | LOCK_EX);
    }
}
