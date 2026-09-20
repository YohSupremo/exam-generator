<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

$id = preg_replace('/[^A-Za-z0-9\-_]/', '', $_GET['id'] ?? '');
$row = $id !== '' ? exam_get($id) : null;

if (!$row || $row['status'] !== 'done') {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Not found or not ready']);
    exit;
}

$file = exam_dir($id) . '/questions.json';
if (!is_file($file)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Question bank missing']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo (string)file_get_contents($file);
exit;