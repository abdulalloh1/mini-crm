<?php declare(strict_types=1);

namespace App\Database;

use App\Support\Env;
use PDO;

final class PdoFactory
{
    public static function make(): PDO
    {
        $host = Env::get('DB_HOST');
        $port = Env::get('DB_PORT', '5432');
        $db = Env::get('DB_NAME');
        $user = Env::get('DB_USER');
        $pass = Env::get('DB_PASS');

        $dsn = "pgsql:host={$host};port={$port};dbname={$db}";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        return $pdo;
    }
}
