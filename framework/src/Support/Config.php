<?php

namespace Simple\Support;

final class Config
{
    public function __construct(private array $items = [])
    {
    }

    /**
     * Load every config/*.php file, keyed by file name (config/app.php => "app").
     */
    public static function fromDirectory(string $dir): self
    {
        $items = [];

        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $items[basename($file, '.php')] = require $file;
        }

        return new self($items);
    }

    /**
     * Read a value using dot notation, e.g. "database.connections.mysql.host".
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $ref = &$this->items;

        foreach (explode('.', $key) as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref = $value;
    }
}
