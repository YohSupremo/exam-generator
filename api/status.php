<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');

if (isset($_GET['ping'])) {
    echo json_encode([
        'ok' => true,
        'php' => is_file(PHP_EXE),
        'opencode' => is_file(OPENCODE_EXE),
        'opencode_version' => '',
    ]);
    exit;
}

$id = preg_replace('/[^A-Za-z0-9\-_]/', '', $_GET['id'] ?? '');
$row = $id !== '' ? exam_get($id) : null;

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'Exam not found']);
    exit;
}

$dir = exam_dir($id);
$status = ['status' => $row['status'], 'message' => '', 'log_tail' => ''];

if (is_file($dir . '/status.json')) {
    $s = json_decode((string)file_get_contents($dir . '/status.json'), true);
    if (is_array($s)) {
        $status = array_merge($status, $s);
    }
}

if (in_array($status['status'], ['queued', 'running'], true)) {
    $created = strtotime($row['created_at']);
    if ($created && (time() - $created) > 1800) {
        $status['status'] = 'error';
        $status['error'] = 'Generation timed out (stale job).';
        exam_update($id, ['status' => 'error']);
    }
}

if (in_array($status['status'], ['running', 'error'], true) && is_file($dir . '/opencode.log')) {
    $lines = file($dir . '/opencode.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $status['log_tail'] = implode("\n", array_slice($lines, -8));
}

$status['ok'] = $status['status'] !== 'error';
$status['title'] = $row['title'];
$status['total_questions'] = (int)$row['total_questions'];
$status['num_chapters'] = (int)$row['num_chapters'];

echo json_encode($status, JSON_UNESCAPED_UNICODE);
exit;