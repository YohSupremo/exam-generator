<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $rows = db()->query(
        "SELECT status, COUNT(*) as cnt
         FROM exams
         WHERE status IN ('queued', 'running')
         GROUP BY status"
    )->fetchAll();

    $queued  = 0;
    $running = 0;
    foreach ($rows as $r) {
        if ($r['status'] === 'queued')  $queued  = (int)$r['cnt'];
        if ($r['status'] === 'running') $running = (int)$r['cnt'];
    }

    $total = $queued + $running;

    if (ob_get_level() > 0) ob_end_clean();

    echo json_encode([
        'ok'      => true,
        'total'   => $total,
        'queued'  => $queued,
        'running' => $running,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'ok'      => false,
        'total'   => 0,
        'queued'  => 0,
        'running' => 0,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
