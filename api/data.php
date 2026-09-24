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

$raw = (string)file_get_contents($file);
$bank = json_decode($raw, true);

if (is_array($bank)) {
    if (ensure_exam_encyclopedia($bank)) {
        @file_put_contents($file, json_encode($bank, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        echo json_encode($bank, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

echo $raw;
exit;