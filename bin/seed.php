<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\PdoFactory;

$pdo = PdoFactory::make();

echo "Running seed...\n";

$email = 'admin@crm.local';
$password = 'admin123';
$role = 'admin';

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute(['email' => $email]);
$exists = $stmt->fetch();

if ($exists) {
    echo "Admin already exists. Skip.\n";
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO users (email, password_hash, role)
    VALUES (:email, :password_hash, :role)
");

$stmt->execute([
    'email' => $email,
    'password_hash' => $passwordHash,
    'role' => $role
]);

echo "Admin user created:\n";
echo "Email: {$email}\n";
echo "Password: {$password}\n";