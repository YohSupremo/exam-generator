<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

$id = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)($_GET['id'] ?? ''));

if (isset($_GET['op']) && (string)$_GET['op'] === 'list') {
    $out = [];
    foreach (html_exam_list() as $row) {
        $out[] = [
            'id' => (string)$row['id'],
            'title' => (string)$row['title'],
            'status' => (string)$row['status'],
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
        ];
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => true, 'exams' => $out], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['op']) && (string)$_GET['op'] === 'attempt') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'POST required']);
        exit;
    }
    $payload = json_decode((string)file_get_contents('php://input'), true);
    $examId = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)($payload['exam_id'] ?? ''));
    if ($examId === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Missing exam ID']);
        exit;
    }
    $attempt = html_attempt_get_latest(get_or_create_user_token(), $examId);
    if (!$attempt) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Attempt not found']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => true,
        'exam_id' => $examId,
        'attempt' => [
            'id' => (string)$attempt['id'],
            'attempt_no' => (int)$attempt['attempt_no'],
            'status' => (string)$attempt['status'],
            'score' => $attempt['score'] === null ? null : (float)$attempt['score'],
            'correct' => (int)($attempt['correct'] ?? 0),
            'total' => (int)($attempt['total'] ?? 0),
            'started_at' => (string)$attempt['started_at'],
            'submitted_at' => $attempt['submitted_at'] === null ? null : (string)$attempt['submitted_at'],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($id === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Missing exam ID']);
    exit;
}

$row = html_exam_get($id);
if (!$row) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Exam not found']);
    exit;
}

if (isset($_GET['raw']) && (string)$_GET['raw'] === '1') {
    $file = HTML_EXAMS_DIR . '/' . $id . '/exam.html';
    if (!is_file($file)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'HTML exam file missing']);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo (string)file_get_contents($file);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'ok' => true,
    'id' => $id,
    'title' => (string)$row['title'],
    'status' => (string)$row['status'],
    'created_at' => (string)$row['created_at'],
    'updated_at' => (string)$row['updated_at'],
], JSON_UNESCAPED_UNICODE);
exit;
