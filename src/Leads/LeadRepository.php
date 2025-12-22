<?php
declare(strict_types=1);

namespace App\Leads;

use App\Database\PdoFactory;
use PDO;

final class LeadRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = PdoFactory::make();
    }

    public function create(string $name, ?string $phone, string $status, int $createdBy): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO leads (name, phone, status, created_by)
            VALUES (:name, :phone, :status, :created_by)
            RETURNING id
        ");

        $stmt->execute([
            'name' => $name,
            'phone' => $phone,
            'status' => $status,
            'created_by' => $createdBy
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function listLatest(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, phone, status, created_at
            FROM leads
            ORDER BY id DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}