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

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
        SELECT id, name, phone, status, created_by, created_at
        FROM leads
        WHERE id = :id
        LIMIT 1
    ");
        $stmt->execute(['id' => $id]);

        $lead = $stmt->fetch();

        return $lead ?: null;
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

    public function listWithStats(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
        SELECT
            l.id,
            l.name,
            l.phone,
            l.status,
            l.created_at,

            COALESCE(nc.notes_count, 0) AS notes_count,

            ln.text AS last_note_text,
            ln.created_at AS last_note_created_at,
            ln.author_email AS last_note_author_email

        FROM leads l

        LEFT JOIN (
            SELECT lead_id, COUNT(*) AS notes_count
            FROM lead_notes
            GROUP BY lead_id
        ) nc ON nc.lead_id = l.id

        LEFT JOIN (
            SELECT DISTINCT ON (n.lead_id)
                n.lead_id,
                n.text,
                n.created_at,
                u.email AS author_email
            FROM lead_notes n
            JOIN users u ON u.id = n.user_id
            ORDER BY n.lead_id, n.id DESC
        ) ln ON ln.lead_id = l.id

        ORDER BY l.id DESC
        LIMIT :limit
    ");

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function update(int $id, string $name, ?string $phone, string $status): void
    {
        $stmt = $this->pdo->prepare("
        UPDATE leads
        SET name = :name, phone = :phone, status = :status
        WHERE id = :id
    ");
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'phone' => $phone,
            'status' => $status,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM leads WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function countAll(?string $status = null): int
    {
        if ($status) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM leads WHERE status = :status");
            $stmt->execute(['status' => $status]);
            return (int)$stmt->fetchColumn();
        }

        return (int)$this->pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
    }

    public function listWithStatsPaginated(?string $status, int $limit, int $offset): array
    {
        $whereSql = '';
        $params = [];

        if ($status) {
            $whereSql = 'WHERE l.status = :status';
            $params['status'] = $status;
        }

        $sql = "
        SELECT
            l.id,
            l.name,
            l.phone,
            l.status,
            l.created_at,

            COALESCE(nc.notes_count, 0) AS notes_count,

            ln.text AS last_note_text,
            ln.created_at AS last_note_created_at,
            ln.author_email AS last_note_author_email

        FROM leads l

        LEFT JOIN (
            SELECT lead_id, COUNT(*) AS notes_count
            FROM lead_notes
            GROUP BY lead_id
        ) nc ON nc.lead_id = l.id

        LEFT JOIN (
            SELECT DISTINCT ON (n.lead_id)
                n.lead_id,
                n.text,
                n.created_at,
                u.email AS author_email
            FROM lead_notes n
            JOIN users u ON u.id = n.user_id
            ORDER BY n.lead_id, n.id DESC
        ) ln ON ln.lead_id = l.id

        {$whereSql}

        ORDER BY l.id DESC
        LIMIT :limit OFFSET :offset
    ";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }

        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }
}