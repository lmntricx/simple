<?php

namespace Simple\Storage;

use InvalidArgumentException;

/**
 *   Storage::disk('public')->url($path);   Storage::disk()->get('reports/q3.csv');
 *
 * Disks are configured in config/filesystems.php. In tests, Storage::fake('public')
 * swaps a disk for an empty temporary directory.
 */
final class Storage
{
    /** @var array<string, Disk> */
    private static array $disks = [];
    /** @var string[] temp directories created by fake() */
    private static array $fakeRoots = [];

    public static function disk(?string $name = null): Disk
    {
        $name ??= config('filesystems.default', 'local');

        if (isset(self::$disks[$name])) {
            return self::$disks[$name];
        }

        $config = config("filesystems.disks.$name");
        if (!is_array($config) || !isset($config['root'])) {
            throw new InvalidArgumentException("Disk [$name] is not configured in config/filesystems.php.");
        }

        return self::$disks[$name] = new Disk($config['root'], $config['url'] ?? null);
    }

    /**
     * Replace a disk with an empty temporary directory (tests).
     */
    public static function fake(?string $name = null): Disk
    {
        $name ??= config('filesystems.default', 'local');
        $root = sys_get_temp_dir() . '/simple_disk_' . bin2hex(random_bytes(6));
        mkdir($root, 0775, true);
        self::$fakeRoots[] = $root;

        return self::$disks[$name] = new Disk($root, config("filesystems.disks.$name.url"));
    }

    /**
     * Forget resolved disks and delete fake directories.
     */
    public static function reset(): void
    {
        foreach (self::$fakeRoots as $root) {
            self::removeDirectory($root);
        }
        self::$fakeRoots = [];
        self::$disks = [];
    }

    private static function removeDirectory(string $dir): void
    {
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
