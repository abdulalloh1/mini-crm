<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\PdoFactory;

$pdo = PdoFactory::make();

echo "Running seed...\n";

$email = 'admin@crm.local';
$password = 'admin123';
$role = 'admin';