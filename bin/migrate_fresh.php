<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\PdoFactory;

$pdo = PdoFactory::make();

echo "Running migrate:fresh...\n";

$tables = [
    "lead_notes",
    "leads",
    "users",
    "migrations"
];

foreach ($tables as $table) {
    echo "Dropping {$table}...\n";
    $pdo->exec("DROP TABLE IF EXISTS {$table} CASCADE");
}

echo "Running migrations...\n";
require __DIR__ . '/migrate.php';

echo "Fresh migration completed.\n";