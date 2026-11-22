<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Gate every HTML Exam Delivery ("streamer") entry point.
 *
 * While HTML_EXAM_LOCKED is true, the request stops here with a 503 response and
 * no upload / read / attempt / submit / delete logic is reached.
 *
 * Call at the top of html_exam.php, api/html_get.php and api/html_submit.php.
 */
function html_exam_lock_guard(bool $api = false): void
{
    if (!defined('HTML_EXAM_LOCKED') || !HTML_EXAM_LOCKED) {
        return;
    }

    http_response_code(503);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if ($api) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'HTML Exam Delivery is temporarily unavailable.',
            'locked' => true,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>HTML Exam Delivery — Temporarily Unavailable</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#09090b;color:#e4e4e7;'
            . 'font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
            . '.box{max-width:28rem;padding:32px;text-align:center;border:1px solid #27272a;border-radius:16px;background:#111113}'
            . 'h1{font-size:20px;margin:0 0 10px}p{color:#a1a1aa;font-size:14px;line-height:1.6;margin:0}'
            . 'a{color:#818cf8}</style></head><body>'
            . '<div class="box"><h1>HTML Exam Delivery is locked</h1>'
            . '<p>This service is temporarily unavailable. No exams can be uploaded, opened, streamed, or submitted right now.'
            . '<br><br><a href="index.php">Return to SynthExam</a></p></div></body></html>';
    }
    exit;
}
