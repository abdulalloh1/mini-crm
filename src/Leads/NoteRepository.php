<?php
declare(strict_types=1);

namespace App\Leads;

use App\Database\PdoFactory;
use PDO;

final class NoteRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = PdoFactory::make();
    }

    public function listByLead(int $leadId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.id, n.text, n.created_at, u.email AS author_email
            FROM lead_notes n
            JOIN users u ON u.id = n.user_id
            WHERE n.lead_id = :lead_id
            ORDER BY n.id DESC
            LIMIT 50
        ");
        $stmt->execute(['lead_id' => $leadId]);

        return $stmt->fetchAll();
    }

    public function create(int $leadId, int $userId, string $text): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lead_notes (lead_id, user_id, text)
            VALUES (:lead_id, :user_id, :text)
            RETURNING id
        ");
        $stmt->execute([
            'lead_id' => $leadId,
            'user_id' => $userId,
            'text' => $text,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function countByLead(int $leadId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM lead_notes WHERE lead_id = :lead_id");
        $stmt->execute(['lead_id' => $leadId]);
        return (int) $stmt->fetchColumn();
    }

    public function lastNoteByLead(int $leadId): ?array
    {
        $stmt = $this->pdo->prepare("
        SELECT n.text, n.created_at, u.email AS author_email
        FROM lead_notes n
        JOIN users u ON u.id = n.user_id
        WHERE n.lead_id = :lead_id
        ORDER BY n.id DESC
        LIMIT 1
    ");
        $stmt->execute(['lead_id' => $leadId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}