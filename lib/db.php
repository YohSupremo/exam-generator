<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        if (!is_dir(DATA_DIR)) {
            mkdir(DATA_DIR, 0777, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS exams (
                id TEXT PRIMARY KEY,
                title TEXT,
                subject TEXT,
                source_name TEXT,
                status TEXT NOT NULL,
                total_questions INTEGER DEFAULT 0,
                num_chapters INTEGER DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }
    return $pdo;
}

function exam_create(string $id, string $sourceName): void
{
    $now = date('c');
    $stmt = db()->prepare(
        'INSERT INTO exams (id, title, subject, source_name, status, created_at, updated_at)
         VALUES (:id, :title, :subject, :source_name, :status, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':id' => $id,
        ':title' => rtrim(pathinfo($sourceName, PATHINFO_FILENAME)),
        ':subject' => '',
        ':source_name' => $sourceName,
        ':status' => 'queued',
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}

function exam_get(string $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM exams WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function exam_list(): array
{
    return db()->query('SELECT * FROM exams ORDER BY created_at DESC')->fetchAll();
}

function exam_update(string $id, array $fields): void
{
    $set = [];
    $params = [];
    foreach ($fields as $col => $value) {
        $set[] = $col . ' = :' . $col;
        $params[':' . $col] = $value;
    }
    $set[] = 'updated_at = :updated_at';
    $params[':updated_at'] = date('c');
    $params[':id'] = $id;
    $sql = 'UPDATE exams SET ' . implode(', ', $set) . ' WHERE id = :id';
    db()->prepare($sql)->execute($params);
}