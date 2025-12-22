<?php
declare(strict_types=1);

namespace App\Auth;

use App\Database\PdoFactory;

final class LoginService
{
    public static function attempt(string $email, string $password): ?array
    {
        $pdo = PdoFactory::make();

        $stmt = $pdo->prepare("SELECT id, email, password_hash, role FROM users WHERE email = :email");
        $stmt->execute([
            'email' => $email
        ]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $user;
    }
}