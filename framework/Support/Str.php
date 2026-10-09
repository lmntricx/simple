<?php

namespace Simple\Support;

final class Str
{
    public static function snake(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /**
     * Naive English pluralisation, good enough for table names.
     */
    public static function plural(string $value): string
    {
        if (preg_match('/[^aeiou]y$/', $value)) {
            return substr($value, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/', $value)) {
            return $value . 'es';
        }

        return $value . 's';
    }
}
