<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (isset($_GET['ping'])) {
        $quota = [];
        if (is_file(DATA_DIR . '/ai_quota.json')) {
            $quota = json_decode((string)@file_get_contents(DATA_DIR . '/ai_quota.json'), true) ?: [];
        }
        echo json_encode([
            'ok' => true,
            'php' => is_file(PHP_EXE),
            'opencode' => is_file(OPENCODE_EXE),
            'opencode_version' => '',
            'model' => defined('OPENCODE_MODEL') ? OPENCODE_MODEL : 'groq/openai/gpt-oss-20b',
            'has_api_key' => defined('GROQ_API_KEY') && GROQ_API_KEY !== '',
            'quota' => $quota,
            'lifetime_tokens' => exam_lifetime_tokens(),
        ], JSON_UNESCAPED_UNICODE);
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
        $s = json_decode((string)@file_get_contents($dir . '/status.json'), true);
        if (is_array($s)) {
            $status = array_merge($status, $s);
        }
    }
    if (is_file($dir . '/usage.json')) {
        $u = json_decode((string)@file_get_contents($dir . '/usage.json'), true);
        if (is_array($u)) {
            $status['token_usage'] = $u;
        }
    }

    if (in_array($status['status'], ['queued', 'running'], true)) {
        $created = strtotime($row['created_at']);
        $isAlive = is_worker_running($dir);
        if (!$isAlive) {
            $lastActivity = strtotime($status['updated'] ?? $row['updated_at'] ?? $row['created_at']);
            // If worker is dead and no activity for > 120s, mark error
            if ($lastActivity && (time() - $lastActivity) > 120) {
                $status['status'] = 'error';
                $status['error'] = 'Worker process is no longer running.';
                exam_update($id, ['status' => 'error']);
            }
        } else {
            // Worker is active; only time out after 3 hours hard limit
            if ($created && (time() - $created) > 10800) {
                $status['status'] = 'error';
                $status['error'] = 'Generation exceeded maximum time limit (3 hours).';
                exam_update($id, ['status' => 'error']);
            }
        }
    }

    if (in_array($status['status'], ['queued', 'running'], true)) {
        $start = strtotime($row['created_at'] ?? 'now');
        $elapsed = max(0, time() - ($start ?: time()));
        $status['elapsed_seconds'] = $elapsed;
        $status['eta_seconds'] = estimate_eta($id, $elapsed);
    }

    $phaseData = resolve_phase($dir, $status['status'], $status);
    $status['phase'] = $phaseData['id'];
    $status['step'] = $phaseData['step'];
    $status['step_label'] = $phaseData['label'];
    $status['step_sub'] = $phaseData['sub'];
    $status['percent'] = $phaseData['percent'];
    $status['done_chapters'] = $phaseData['done_chapters'];
    $status['total_chapters'] = $phaseData['total_chapters'];
    $status['phases'] = [
        ['step' => 1, 'id' => 'preparation', 'label' => 'Preparation', 'sub' => 'Text extraction & source check'],
        ['step' => 2, 'id' => 'planning', 'label' => 'Outline', 'sub' => 'Chapter analysis & syllabus'],
        ['step' => 3, 'id' => 'generating', 'label' => 'Questions', 'sub' => 'Chapter question generation'],
        ['step' => 4, 'id' => 'assembly', 'label' => 'Assembly', 'sub' => 'Reviewer, key & glossary'],
        ['step' => 5, 'id' => 'ready', 'label' => 'Ready', 'sub' => 'Interactive exam complete'],
    ];

    if (in_array($status['status'], ['running', 'error'], true)) {
        $tail = '';
        foreach ([$dir . '/plan.log', $dir . '/opencode.log'] as $lf) {
            if (!is_file($lf)) { continue; }
            $lines = file($lf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            if ($lines) {
                $tail .= ($tail !== '' ? "\n" : '') . implode("\n", array_slice($lines, -8));
            }
        }
        foreach (glob($dir . '/chapters/*.log') ?: [] as $lf) {
            $lines = file($lf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            if ($lines) {
                $tail .= "\n--- " . basename($lf) . " ---\n" . implode("\n", array_slice($lines, -6));
            }
        }
        $status['log_tail'] = $tail;
    }

    $status['ok'] = $status['status'] !== 'error';
    $status['title'] = $row['title'];
    $status['total_questions'] = (int)$row['total_questions'];
    $status['num_chapters'] = (int)$row['num_chapters'];
    $status['total_tokens'] = (int)($row['total_tokens'] ?? ($status['token_usage']['total_tokens'] ?? 0));
    if (is_file(DATA_DIR . '/ai_quota.json')) {
        $status['quota'] = json_decode((string)@file_get_contents(DATA_DIR . '/ai_quota.json'), true) ?: [];
    }
    $status['lifetime_tokens'] = exam_lifetime_tokens();

    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($status, JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    echo json_encode([
        'ok' => false,
        'status' => 'error',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function is_worker_running(string $dir): bool
{
    $pidFile = $dir . '/worker.pid';
    if (!is_file($pidFile)) {
        return false;
    }
    $pid = (int)trim((string)@file_get_contents($pidFile));
    if ($pid <= 0) {
        return false;
    }
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $out = [];
        @exec("tasklist /FI \"PID eq $pid\" /NH 2>NUL", $out);
        $outputStr = implode("\n", $out);
        return stripos($outputStr, (string)$pid) !== false;
    }
    return function_exists('posix_kill') && @posix_kill($pid, 0);
}

function estimate_eta(string $id, int $elapsed): int
{
    $dir = exam_dir($id);
    $totalChapters = 0;

    $planFile = $dir . '/plan.json';
    if (is_file($planFile)) {
        $plan = json_decode((string)@file_get_contents($planFile), true);
        if (is_array($plan) && !empty($plan['chapters']) && is_array($plan['chapters'])) {
            $totalChapters = count($plan['chapters']);
        }
    }
    if ($totalChapters <= 0) {
        $job = json_decode((string)@file_get_contents($dir . '/job.json'), true);
        $totalChapters = (int)($job['est_chapters'] ?? 0);
        if ($totalChapters <= 0) {
            $totalChapters = DEFAULT_EST_CHAPTERS;
        }
    }

    $chapterFiles = glob($dir . '/chapters/*.json') ?: [];
    $finishedChapters = count($chapterFiles);
    $remainingChapters = max(0, $totalChapters - $finishedChapters);

    if ($remainingChapters === 0) {
        return 15;
    }

    $row = exam_get($id);
    $jobStartTime = $row ? strtotime($row['created_at']) : time();

    $baselineRoundSecs = 0;
    $currentRoundElapsed = $elapsed;

    if ($finishedChapters > 0 && !empty($chapterFiles)) {
        $lastFinishedTime = max(array_map('filemtime', $chapterFiles));
        $firstRoundDuration = max(60, $lastFinishedTime - $jobStartTime);
        $roundsDone = max(1, (int)ceil($finishedChapters / (float)CONCURRENCY));
        $baselineRoundSecs = (int)round($firstRoundDuration / $roundsDone);
        $currentRoundElapsed = max(0, time() - $lastFinishedTime);
    } else {
        $hist = historical_seconds_per_chapter();
        if ($hist <= 0.0) {
            $hist = (float)DEFAULT_SECONDS_PER_CHAPTER;
        }
        $baselineRoundSecs = (int)round($hist);
    }

    $roundsRemaining = (int)ceil($remainingChapters / (float)CONCURRENCY);
    $currentRoundRemaining = max(20, $baselineRoundSecs - $currentRoundElapsed);
    $subsequentRounds = max(0, $roundsRemaining - 1) * $baselineRoundSecs;

    $eta = $currentRoundRemaining + $subsequentRounds;
    if ($finishedChapters === 0 && !is_file($planFile)) {
        $eta += max(0, PLAN_EST_SECONDS - $elapsed);
    }

    $eta = max(15, min(7200, $eta));
    return (int)$eta;
}

function historical_seconds_per_chapter(): float
{
    try {
        $rows = db()->query(
            "SELECT num_chapters, created_at, updated_at
             FROM exams
             WHERE status = 'done' AND num_chapters > 0"
        )->fetchAll();
    } catch (Throwable $e) {
        return 0.0;
    }
    $totalChapters = 0;
    $secs = 0;
    foreach ($rows as $r) {
        $d = strtotime($r['updated_at']) - strtotime($r['created_at']);
        if ($d >= 60) {
            $totalChapters += (int)$r['num_chapters'];
            $secs += $d;
        }
    }
    return $totalChapters > 0 ? (float)($secs / $totalChapters) : 0.0;
}

function resolve_phase(string $dir, string $jobStatus, array $status): array
{
    $phases = [
        1 => ['id' => 'preparation', 'label' => 'Preparation', 'sub' => 'Text extraction & source check'],
        2 => ['id' => 'planning', 'label' => 'Outline', 'sub' => 'Chapter analysis & syllabus'],
        3 => ['id' => 'generating', 'label' => 'Questions', 'sub' => 'Generating questions per chapter'],
        4 => ['id' => 'assembly', 'label' => 'Assembly', 'sub' => 'Reviewer, key & glossary'],
        5 => ['id' => 'ready', 'label' => 'Ready', 'sub' => 'Interactive exam complete'],
    ];

    if ($jobStatus === 'done') {
        return array_merge($phases[5], [
            'step' => 5,
            'percent' => 100,
            'done_chapters' => 0,
            'total_chapters' => 0,
        ]);
    }
    if ($jobStatus === 'cancelled' || $jobStatus === 'error') {
        return [
            'id' => $jobStatus,
            'step' => 0,
            'label' => ucfirst($jobStatus),
            'sub' => $status['error'] ?? '',
            'percent' => 0,
            'done_chapters' => 0,
            'total_chapters' => 0,
        ];
    }
    if ($jobStatus === 'queued') {
        return array_merge($phases[1], [
            'step' => 1,
            'percent' => 5,
            'done_chapters' => 0,
            'total_chapters' => 0,
        ]);
    }

    $explicitStep = (int)($status['step'] ?? 0);

    $planFile = $dir . '/plan.json';
    $totalChapters = 0;
    if (is_file($planFile)) {
        $plan = json_decode((string)@file_get_contents($planFile), true);
        if (is_array($plan) && !empty($plan['chapters']) && is_array($plan['chapters'])) {
            $totalChapters = count($plan['chapters']);
        }
    }
    $doneChapters = count(glob($dir . '/chapters/*.json') ?: []);

    $step = 1;
    if ($explicitStep >= 1 && $explicitStep <= 5) {
        $step = $explicitStep;
    } else {
        if (is_file($dir . '/questions.json')) {
            $step = 4;
        } elseif ($totalChapters > 0 && $doneChapters >= $totalChapters) {
            $step = 4;
        } elseif ($totalChapters > 0 || is_file($planFile)) {
            $step = 3;
        } elseif (is_file($dir . '/source.txt')) {
            $step = 2;
        } else {
            $step = 1;
        }
    }

    $percent = 10;
    if ($step === 1) {
        $percent = 10;
    } elseif ($step === 2) {
        $percent = 25;
    } elseif ($step === 3) {
        if ($totalChapters > 0) {
            $progressFraction = min(1.0, $doneChapters / $totalChapters);
            $percent = 25 + (int)round($progressFraction * 60);
        } else {
            $percent = 35;
        }
    } elseif ($step === 4) {
        $percent = 90;
    } elseif ($step === 5) {
        $percent = 100;
    }

    $base = $phases[$step] ?? $phases[1];
    if ($step === 3 && $totalChapters > 0) {
        $base['sub'] = "$doneChapters of $totalChapters chapters completed";
    }

    return array_merge($base, [
        'step' => $step,
        'percent' => $percent,
        'done_chapters' => $doneChapters,
        'total_chapters' => $totalChapters,
    ]);
}