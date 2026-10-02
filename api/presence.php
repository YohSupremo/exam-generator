<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/../lib/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');

$presenceFile = DATA_DIR . '/presence.json';
$timeoutSeconds = 35; // Expire inactive sessions after 35 seconds
$now = time();

// Sanitize inputs
$clientId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($_REQUEST['clientId'] ?? ''));
$page = preg_replace('/[^A-Za-z0-9_\-\/]/', '', (string)($_REQUEST['page'] ?? ''));
$examId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($_REQUEST['examId'] ?? ''));
$action = (string)($_REQUEST['action'] ?? 'ping');

if ($clientId === '') {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'browser');
    $clientId = substr(md5($ip . '|' . $ua), 0, 16);
}

$entries = [];

// Atomic read-modify-write with file lock
$fp = @fopen($presenceFile, 'c+');
if ($fp) {
    if (flock($fp, LOCK_EX)) {
        $size = filesize($presenceFile);
        if ($size > 0) {
            $raw = fread($fp, $size);
            $decoded = json_decode((string)$raw, true);
            if (is_array($decoded)) {
                $entries = $decoded;
            }
        }

        // Purge expired sessions
        $activeEntries = [];
        foreach ($entries as $cid => $info) {
            if (is_array($info) && isset($info['ts']) && ($now - (int)$info['ts']) < $timeoutSeconds) {
                $activeEntries[$cid] = $info;
            }
        }

        if ($action === 'leave') {
            unset($activeEntries[$clientId]);
        } else {
            $activeEntries[$clientId] = [
                'ts' => $now,
                'page' => $page,
                'examId' => $examId,
            ];
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($activeEntries, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);

        $entries = $activeEntries;
    }
    fclose($fp);
}

$totalOnline = max(1, count($entries));
$examOnline = 0;
if ($examId !== '') {
    foreach ($entries as $info) {
        if (($info['examId'] ?? '') === $examId) {
            $examOnline++;
        }
    }
    $examOnline = max(1, $examOnline);
}

if (ob_get_level() > 0) {
    ob_end_clean();
}

echo json_encode([
    'ok' => true,
    'online' => $totalOnline,
    'examOnline' => $examOnline,
    'page' => $page,
    'examId' => $examId,
    'timestamp' => $now,
]);
