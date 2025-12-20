<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\PdoFactory;

header('Content-Type: application/json; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/health') {
    echo json_encode([
        'ok' => true,
        'time' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/db-check') {
    try {
        $pdo = PdoFactory::make();
        $dbName = $pdo->query("SELECT current_database()")->fetchColumn();

        echo json_encode([
            'ok' => true,
            'db' => $dbName
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

http_response_code(404);
echo json_encode(['error' => 'Not Found'], JSON_UNESCAPED_UNICODE);
