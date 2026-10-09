<?php

namespace Simple\Storage;

use InvalidArgumentException;
use RuntimeException;
use Simple\Http\FileResponse;
use Simple\Http\UploadedFile;

/**
 * A directory files are stored in. Paths are relative to the disk's root
 * and can't escape it ("../" is rejected).
 */
class Disk
{
    public function __construct(private string $root, private ?string $url = null)
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
    }

    /**
     * Absolute path on the server.
     */
    public function path(string $path = ''): string
    {
        $path = self::normalize($path);
        return $path === '' ? $this->root : $this->root . '/' . $path;
    }

    /**
     * Public URL (only for disks with a "url", like "public").
     */
    public function url(string $path): string
    {
        if ($this->url === null) {
            throw new RuntimeException('This disk is private and has no URL. Serve its files through a controller with download().');
        }

        return rtrim($this->url, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', self::normalize($path))));
    }

    public function exists(string $path): bool
    {
        return is_file($this->path($path));
    }

    public function get(string $path): ?string
    {
        return $this->exists($path) ? (string) file_get_contents($this->path($path)) : null;
    }

    public function put(string $path, string $contents): void
    {
        $target = $this->path($path);
        $this->ensureDirectory(dirname($target));

        if (file_put_contents($target, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Could not write [$path].");
        }
    }

    /**
     * Store an upload under a random name; the extension comes from the detected
     * content type (".bin" if unknown). Returns the relative path.
     */
    public function putFile(string $directory, UploadedFile $file): string
    {
        $name = bin2hex(random_bytes(20)) . '.' . ($file->extension() ?? 'bin');
        return $this->putFileAs($directory, $file, $name);
    }

    public function putFileAs(string $directory, UploadedFile $file, string $name): string
    {
        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || $name === '.' || $name === '..') {
            throw new InvalidArgumentException("Invalid file name [$name].");
        }

        $relative = ltrim(self::normalize($directory) . '/' . $name, '/');
        $target = $this->path($relative);
        $this->ensureDirectory(dirname($target));
        $file->moveTo($target);

        return $relative;
    }

    public function delete(string $path): bool
    {
        return $this->exists($path) && unlink($this->path($path));
    }

    public function size(string $path): int
    {
        return $this->exists($path) ? (int) filesize($this->path($path)) : 0;
    }

    /**
     * @return string[] relative paths of files directly in $directory
     */
    public function files(string $directory = ''): array
    {
        $base = $this->path($directory);
        $prefix = self::normalize($directory);

        return array_values(array_map(
            fn ($f) => ltrim($prefix . '/' . basename($f), '/'),
            array_filter(glob($base . '/*') ?: [], 'is_file'),
        ));
    }

    /**
     * Send a file as a download.
     */
    public function download(string $path, ?string $name = null): FileResponse
    {
        return new FileResponse($this->path($path), $name ?? basename($path));
    }

    /**
     * Send a file to display in the browser (e.g. an image or PDF).
     */
    public function response(string $path): FileResponse
    {
        return new FileResponse($this->path($path), inline: true);
    }

    /**
     * "a/./b//c" => "a/b/c"; rejects "..", NUL bytes and drive letters.
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if (str_contains($path, "\0") || preg_match('#^[A-Za-z]:#', $path)) {
            throw new InvalidArgumentException("Invalid path [$path].");
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new InvalidArgumentException("Path [$path] may not contain \"..\".");
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Could not create directory [$dir].");
        }
    }
}
