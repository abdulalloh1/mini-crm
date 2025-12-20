<?php declare(strict_types=1);

namespace App\Support;


final class Env
{
    public static function get(string $key, ?string $default = null): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        if($value === false || $value === null || $value === '') {
            if($default !== null) return $default;

            throw new \RuntimeException("Missing env: {$key}");
        }

        return (string) $value;
    }
}