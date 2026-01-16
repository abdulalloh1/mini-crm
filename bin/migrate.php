<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\PdoFactory;

$pdo = PdoFactory::make();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id BIGSERIAL PRIMARY KEY,
        name VARCHAR(255) UNIQUE NOT NULL,
        executed_at TIMESTAMP NOT NULL DEFAULT NOW()
    )
");

$migrations = [
    [
        'name' => '2025_12_20_000001_create_users',
        'sql' => "
            CREATE TABLE IF NOT EXISTS users (
                id BIGSERIAL PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'manager',
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            );
        "
    ],
    [
        'name' => '2025_12_20_000002_create_leads',
        'sql' => "
            CREATE TABLE IF NOT EXISTS leads (
                id BIGSERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                phone VARCHAR(50),
                status VARCHAR(50) NOT NULL DEFAULT 'new',
                created_by BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            );

            CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(status);
            CREATE INDEX IF NOT EXISTS idx_leads_created_by ON leads(created_by);
        "
    ],
    [
        'name' => '2025_12_20_000003_create_lead_notes',
        'sql' => "
            CREATE TABLE IF NOT EXISTS lead_notes (
                id BIGSERIAL PRIMARY KEY,
                lead_id BIGINT NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
                user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                text TEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            );

            CREATE INDEX IF NOT EXISTS idx_lead_notes_lead_id ON lead_notes(lead_id);
        "
    ],
    [
        'name' => '2026_01_16_000004_indexes',
        'sql' => "
            CREATE INDEX IF NOT EXISTS idx_leads_status_id ON leads (status, id DESC);
            CREATE INDEX IF NOT EXISTS idx_lead_notes_lead_id ON lead_notes (lead_id);
            CREATE INDEX IF NOT EXISTS idx_lead_notes_lead_id_id_desc ON lead_notes (lead_id, id DESC);
        "
    ]
];

$executed = $pdo->query("SELECT name FROM migrations")->fetchAll();
$executedNames = array_flip(array_map(fn($row) => $row['name'], $executed));

foreach ($migrations as $migration) {
    $name = $migration['name'];

    if (isset($executedNames[$name])) {
        echo "[SKIP] {$name}\n";
        continue;
    }

    echo "[RUN ] {$name}\n";

    $pdo->beginTransaction();
    try {
        $pdo->exec($migration['sql']);

        $stmt = $pdo->prepare("INSERT INTO migrations (name) VALUES (:name)");
        $stmt->execute(['name' => $name]);

        $pdo->commit();
        echo "[OK ] {$name}\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
        exit(1);
    }
}

echo "All migrations done.\n";