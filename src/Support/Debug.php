<?php
declare(strict_types=1);

namespace App\Support;

final class Debug
{
    public static function dd(mixed ...$vars): never
    {
        foreach ($vars as $v) {
            var_dump($v);
        }
        exit(1);
    }
}