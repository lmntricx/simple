<?php

namespace Simple\Support;

/**
 * Fixed-window counter stored in small files (storage/framework/rate-limits),
 * so it works without Redis or a database.
 */
final class RateLimiter
{
    /** Override in tests to keep counters out of storage/. */
    public static ?string $directory = null;

    /**
     * Count one attempt for $key.
     *
     * @return array{count: int, reset_at: int}
     */
    public static function hit(string $key, int $decaySeconds): array
    {
        $file = self::file($key);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $handle = fopen($file, 'c+');
        flock($handle, LOCK_EX);

        try {
            $data = json_decode(stream_get_contents($handle) ?: '', true);
            $now = time();

            if (!is_array($data) || ($data['reset_at'] ?? 0) <= $now) {
                $data = ['count' => 0, 'reset_at' => $now + $decaySeconds];
            }

            $data['count']++;

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data));
            fflush($handle);

            return $data;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public static function clear(string $key): void
    {
        @unlink(self::file($key));
    }

    private static function file(string $key): string
    {
        return (self::$directory ?? storage_path('framework/rate-limits')) . '/' . sha1($key);
    }
}
