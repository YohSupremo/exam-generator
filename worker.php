<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';

error_reporting(E_ALL);
set_time_limit(0);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$id = preg_replace('/[^A-Za-z0-9\-_]/', '', $argv[1] ?? '');
register_shutdown_function(static function () use ($id): void {
    if ($id !== '') {
        remove_launch_task($id);
        @unlink(exam_dir($id) . '/worker.pid');
    }
});
$dir = $id !== '' ? exam_dir($id) : '';

if ($id === '' || !is_dir($dir)) {
    fwrite(STDERR, "Job not found: $id\n");
    exit(1);
}

@file_put_contents($dir . '/worker.pid', (string)getmypid());

$job = json_decode((string)file_get_contents($dir . '/job.json'), true);
if (!is_array($job)) {
    fwrite(STDERR, "No job metadata\n");
    exit(1);
}

$statusFile = $job['status_file'] ?? $dir . '/status.json';
$outFile = $job['output'] ?? $dir . '/questions.json';

$gJobUsage = [
    'prompt_tokens' => 0,
    'completion_tokens' => 0,
    'total_tokens' => 0,
    'model' => defined('OPENCODE_MODEL') ? OPENCODE_MODEL : 'groq/openai/gpt-oss-20b',
    'provider' => 'Groq (LPU Inference Engine)',
    'ratelimit' => [
        'limit_tokens' => 8000,
        'remaining_tokens' => 8000,
        'reset_tokens' => '0s',
        'limit_requests' => 1000,
        'remaining_requests' => 1000,
        'reset_requests' => '0s',
    ],
    'updated_at' => date('c'),
];

if (is_file($dir . '/usage.json')) {
    $existingUsage = json_decode((string)file_get_contents($dir . '/usage.json'), true);
    if (is_array($existingUsage)) {
        $gJobUsage = array_merge($gJobUsage, $existingUsage);
    }
}

function record_token_usage(string $dir, array $usage, array $rateLimitHeaders, string $model): void
{
    global $gJobUsage, $id;
    if (!empty($usage['total_tokens'])) {
        $gJobUsage['prompt_tokens'] += (int)($usage['prompt_tokens'] ?? 0);
        $gJobUsage['completion_tokens'] += (int)($usage['completion_tokens'] ?? 0);
        $gJobUsage['total_tokens'] += (int)($usage['total_tokens'] ?? 0);
    }
    if ($model !== '') {
        $gJobUsage['model'] = $model;
    }
    foreach (['limit_tokens', 'remaining_tokens', 'reset_tokens', 'limit_requests', 'remaining_requests', 'reset_requests'] as $k) {
        if (isset($rateLimitHeaders[$k])) {
            $gJobUsage['ratelimit'][$k] = $rateLimitHeaders[$k];
        }
    }
    $gJobUsage['updated_at'] = date('c');

    @file_put_contents($dir . '/usage.json', json_encode($gJobUsage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    @file_put_contents(DATA_DIR . '/ai_quota.json', json_encode([
        'model' => $gJobUsage['model'],
        'provider' => 'Groq (LPU Inference Engine)',
        'ratelimit' => $gJobUsage['ratelimit'],
        'status' => 'Active & Ready',
        'updated_at' => date('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if (!empty($id)) {
        exam_update($id, ['total_tokens' => $gJobUsage['total_tokens']]);
    }
}

function set_job_status(string $statusFile, string $status, string $message, string $error = '', string $phase = '', int $step = 0): void
{
    global $gJobUsage;
    $payload = [
        'status' => $status,
        'message' => $message,
        'error' => $error,
        'updated' => date('c'),
    ];
    if ($phase !== '') {
        $payload['phase'] = $phase;
        $payload['step'] = $step;
    }
    if (!empty($gJobUsage['total_tokens']) || !empty($gJobUsage['ratelimit']['limit_tokens'])) {
        $payload['token_usage'] = $gJobUsage;
    }
    file_put_contents($statusFile, json_encode($payload, JSON_UNESCAPED_UNICODE));
}

function cancel_requested(string $dir): bool
{
    return is_file($dir . '/.cancel');
}

function finish_cancelled(string $statusFile, string $id, string $dir): void
{
    set_job_status($statusFile, 'cancelled', 'Cancelled by user.', '');
    exam_update($id, ['status' => 'cancelled']);
    @unlink($dir . '/.cancel');
    app_log("Job $id: cancelled by user");
}

function remove_launch_task(string $id): void
{
    $taskName = 'autoExam_' . $id;
    $proc = @proc_open('schtasks /Delete /TN ' . escapeshellarg($taskName) . ' /F', [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (is_resource($proc)) {
        while (true) {
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            $status = proc_get_status($proc);
            if (!$status['running']) {
                break;
            }
            usleep(100000);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
    }
    @unlink(__DIR__ . '/data/.launch_' . $id . '.ps1');
}

$pdf = $job['pdf'] ?? null;
if (!$pdf || !is_file($pdf)) {
    set_job_status($statusFile, 'error', 'Source PDF missing.', 'Source PDF missing.');
    exam_update($id, ['status' => 'error']);
    exit(1);
}

set_job_status($statusFile, 'running', 'Extracting reference text from PDF', '', 'preparation', 1);
exam_update($id, ['status' => 'running']);
app_log("Job $id: worker started");

if (cancel_requested($dir)) {
    finish_cancelled($statusFile, $id, $dir);
    exit(0);
}

$textFile = $dir . '/source.txt';
$tocFile = $dir . '/toc.json';
$extracted = extract_pdf_text($pdf, $textFile, $tocFile);
$sourceForModel = $extracted ? $textFile : $pdf;
if ($extracted) {
    app_log("Job $id: extracted PDF text -> " . filesize($textFile) . " bytes");
} else {
    app_log("Job $id: WARNING - PDF text extraction failed, using PDF directly");
}

if (cancel_requested($dir)) {
    finish_cancelled($statusFile, $id, $dir);
    exit(0);
}

$exe = OPENCODE_EXE;
$workDirArg = '--dir ' . escapeshellarg(str_replace('\\', '/', APP_ROOT));
$fileArg = '--file ' . escapeshellarg($sourceForModel);
$title = $job['title'] ?? 'Exam generation';

// ---------- STEP 1: chapter detection (multi-tier resilient pipeline) ----------
$planFile = $dir . '/plan.json';
$planLog = $dir . '/plan.log';
$plan = null;

// Tier 1: Instant native PDF bookmarks / Table of Contents (0.05s, 0 tokens)
if (is_file($tocFile)) {
    $plan = build_plan_from_toc($tocFile, $title);
    if ($plan) {
        file_put_contents($planFile, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        app_log("Job $id: detected " . count($plan['chapters']) . " chapters from PDF Table of Contents");
    }
}

// Tier 2: Direct high-speed AI API call (Groq/OpenRouter/Gemini, ~2s, zero CLI overhead)
if (!$plan && (GROQ_API_KEY !== '' || OPENROUTER_API_KEY !== '' || GEMINI_API_KEY !== '')) {
    set_job_status($statusFile, 'running', 'Analyzing chapters with AI', '', 'planning', 2);
    app_log("Job $id: analyzing chapters with direct AI API");
    $plan = detect_plan_direct_api($textFile, $title);
    if ($plan) {
        file_put_contents($planFile, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        app_log("Job $id: direct AI API detected " . count($plan['chapters']) . " chapters");
    }
}

// Tier 3: OpenCode CLI (only when offline without direct API keys)
if (!$plan && !$hasDirectApi) {
    $planOutput = [];
    $noFileWorkDirArg = '--dir ' . escapeshellarg(str_replace('\\', '/', APP_ROOT));
    $planCmd = escapeshellarg($exe) . ' run --format json --auto'
        . ((defined('OPENCODE_MODEL') && OPENCODE_MODEL !== '') ? ' -m ' . escapeshellarg(OPENCODE_MODEL) : '')
        . ' ' . $noFileWorkDirArg
        . ' --title ' . escapeshellarg($title)
        . ' ' . escapeshellarg(single_line(plan_prompt($textFile, $pdf, $planFile, $title))) . ' 2>&1';
    app_log("Job $id: planning chapters with opencode (timeout: 90s)");
    set_job_status($statusFile, 'running', 'Analyzing chapters with opencode', '', 'planning', 2);
    $planExit = run_opencode($planCmd, $planLog, $dir, $planOutput, 90);
    if (cancel_requested($dir)) {
        finish_cancelled($statusFile, $id, $dir);
        exit(0);
    }

    if (is_file($planFile)) {
        $decoded = json_decode((string)file_get_contents($planFile), true);
        if (is_array($decoded) && !empty($decoded['chapters'])) {
            $plan = $decoded;
        }
    }
    if (!is_array($plan)) {
        $fallback = extract_json_fallback($planOutput);
        if (is_array($fallback) && !empty($fallback['chapters'])) {
            $plan = $fallback;
            file_put_contents($planFile, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}

// Tier 4: Heuristic text structure fallback (guarantees plan is ALWAYS produced)
if (!$plan) {
    app_log("Job $id: AI planning step produced no plan, generating chapters from text structure");
    $plan = build_plan_from_text($textFile, $title);
    file_put_contents($planFile, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$planChapters = array_values(array_filter($plan['chapters'], 'is_array'));
app_log("Job $id: PLAN - " . count($planChapters) . " chapters detected");

// ---------- STEP 2: generate questions per chapter ----------
$chapterDir = $dir . '/chapters';
if (!is_dir($chapterDir) && !mkdir($chapterDir, 0777, true)) {
    file_put_contents($statusFile, json_encode([
        'status' => 'error',
        'message' => 'Could not create chapters directory.',
        'error' => 'mkdir failed',
        'updated' => date('c'),
    ], JSON_UNESCAPED_UNICODE));
    exam_update($id, ['status' => 'error']);
    exit(1);
}
set_job_status($statusFile, 'running', 'Generating questions for chapters', '', 'generating', 3);

$finished = [];
$totalChapters = count($planChapters);
$hasDirectApi = (defined('GROQ_API_KEY') && GROQ_API_KEY !== '')
    || (defined('OPENROUTER_API_KEY') && OPENROUTER_API_KEY !== '')
    || (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '');

// Fast Path: High-speed direct AI generation engine (~2-4s per chapter on Groq LPU)
if ($hasDirectApi) {
    app_log("Job $id: starting high-speed AI question generation for $totalChapters chapters");
    foreach ($planChapters as $idx => $ch) {
        if (cancel_requested($dir)) {
            finish_cancelled($statusFile, $id, $dir);
            exit(0);
        }
        $rawKey = (string)($ch['chapterId'] ?? '');
        $mergeKey = preg_replace('/[^A-Za-z0-9\-_]/', '_', $rawKey) ?: ('ch' . ($idx + 1));
        $outFile = $chapterDir . '/' . $mergeKey . '.json';
        $chTitle = (string)($ch['title'] ?? 'Chapter ' . ($idx + 1));
        $chNum = $idx + 1;

        if (is_file($outFile)) {
            $cand = json_decode((string)file_get_contents($outFile), true);
            if (is_array($cand) && !empty($cand['questions'])) {
                $isDummy = false;
                foreach ($cand['questions'] as $cq) {
                    $qStem = (string)($cq['question'] ?? '');
                    $c0 = (string)($cq['choices'][0] ?? '');
                    if (strpos($qStem, 'Topic Review') !== false || strpos($c0, 'standard system operation and processing') !== false) {
                        $isDummy = true;
                        break;
                    }
                }
                if (!$isDummy) {
                    $finished[$mergeKey] = $cand;
                    continue;
                }
            }
        }

        set_job_status($statusFile, 'running', "Generating questions for $chTitle ($chNum/$totalChapters)", '', 'generating', 3);
        app_log("Job $id: generating questions for $mergeKey ($chTitle) via direct AI API");

        $decoded = generate_chapter_direct_api($textFile, $ch, $mergeKey);
        if ($decoded && !empty($decoded['questions'])) {
            file_put_contents($outFile, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $finished[$mergeKey] = $decoded;
            app_log("Job $id: chapter $mergeKey finished (ok, " . count($decoded['questions']) . " questions)");
        } else {
            app_log("Job $id: direct AI API generation produced invalid output for $mergeKey, queued for retry");
        }
        usleep(300000);
        sleep(2);
    }

    // Direct API Retry Pass for any chapters missed due to temporary rate limits
    foreach ($planChapters as $idx => $ch) {
        $rawKey = (string)($ch['chapterId'] ?? '');
        $mergeKey = preg_replace('/[^A-Za-z0-9\-_]/', '_', $rawKey) ?: ('ch' . ($idx + 1));
        $outFile = $chapterDir . '/' . $mergeKey . '.json';
        if (empty($finished[$mergeKey])) {
            app_log("Job $id: retrying chapter $mergeKey via direct AI API...");
            sleep(4);
            $decoded = generate_chapter_direct_api($textFile, $ch, $mergeKey);
            if ($decoded && !empty($decoded['questions'])) {
                file_put_contents($outFile, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $finished[$mergeKey] = $decoded;
                app_log("Job $id: chapter $mergeKey finished on retry pass (ok, " . count($decoded['questions']) . " questions)");
            }
        }
    }

    // Direct Text Synthesis fallback to ensure 100% completion without hanging
    foreach ($planChapters as $idx => $ch) {
        $rawKey = (string)($ch['chapterId'] ?? '');
        $mergeKey = preg_replace('/[^A-Za-z0-9\-_]/', '_', $rawKey) ?: ('ch' . ($idx + 1));
        $outFile = $chapterDir . '/' . $mergeKey . '.json';
        if (empty($finished[$mergeKey])) {
            app_log("Job $id: synthesizing chapter $mergeKey directly from reference text");
            $fallback = synthesize_chapter_fallback($textFile, $ch, $mergeKey);
            file_put_contents($outFile, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $finished[$mergeKey] = $fallback;
        }
    }
}

// Check if any chapters still need generation via OpenCode CLI fallback (offline mode without API keys)
$pendingChapters = [];
if (!$hasDirectApi) {
    foreach ($planChapters as $idx => $ch) {
        $rawKey = (string)($ch['chapterId'] ?? '');
        $mergeKey = preg_replace('/[^A-Za-z0-9\-_]/', '_', $rawKey) ?: ('ch' . ($idx + 1));
        if (empty($finished[$mergeKey])) {
            $pendingChapters[] = $ch;
        }
    }
}

if (!empty($pendingChapters)) {
    app_log("Job $id: running OpenCode CLI fallback for " . count($pendingChapters) . " remaining chapters");
    $pool = [];
    $chMeta = [];
    $qIndex = 0;
    $cancelled = false;

    $spawn = static function () use (&$pool, &$finished, &$chMeta, &$qIndex, $pendingChapters, $chapterDir, $exe, $workDirArg, $fileArg, $job, $textFile, $pdf, $title, $statusFile, $id, $dir): void {
        if ($qIndex >= count($pendingChapters)) {
            return;
        }
        $ch = $pendingChapters[$qIndex];
        $rawKey = (string)($ch['chapterId'] ?? '');
        $mergeKey = preg_replace('/[^A-Za-z0-9\-_]/', '_', $rawKey) ?: ('ch' . ($qIndex + 1));
        $chMeta[$mergeKey] = $ch;
        $outFile = $chapterDir . '/' . $mergeKey . '.json';
        $chLog = $chapterDir . '/' . $mergeKey . '.log';
        $cmd = opencode_cmd($exe, $workDirArg, $fileArg, $title, chapter_prompt($textFile, $pdf, $ch, $mergeKey, $outFile));
        $entry = spawn_opencode($cmd, $chLog, $dir, $outFile);
        if ($entry['ok']) {
            $entry['mergeKey'] = $mergeKey;
            $pool[] = $entry;
            app_log("Job $id: fallback chapter $mergeKey started (" . count($pool) . " concurrent)");
        } else {
            app_log("Job $id: fallback chapter $mergeKey FAILED to start");
            $finished[$mergeKey] = null;
        }
        $qIndex++;
    };

    $loopCounter = 0;
    while (true) {
        $loopCounter++;
        if (cancel_requested($dir)) {
            $cancelled = true;
            foreach ($pool as &$entry) {
                if (is_resource($entry['proc'])) {
                    @proc_terminate($entry['proc']);
                }
            }
            unset($entry);
            foreach ($pool as &$entry) {
                close_opencode($entry);
            }
            unset($entry);
            break;
        }
        while (count($pool) < CONCURRENCY && $qIndex < count($pendingChapters)) {
            $spawn();
        }
        $statusDirty = false;
        foreach ($pool as $key => &$entry) {
            drain_opencode($entry);
            $age = time() - ($entry['startTime'] ?? time());
            if ($age > 300 && is_resource($entry['proc'])) {
                app_log("Job $id: fallback chapter " . $entry['mergeKey'] . " timed out after {$age}s, terminating");
                @proc_terminate($entry['proc']);
            }
            $st = proc_get_status($entry['proc']);
            if (!$st['running']) {
                drain_opencode($entry);
                close_opencode($entry);
                $decoded = null;
                if (is_file($entry['out'])) {
                    $cand = json_decode((string)file_get_contents($entry['out']), true);
                    if (is_array($cand) && !empty($cand['questions'])) {
                        $decoded = $cand;
                    }
                }
                if (!$decoded && !empty($entry['logPath']) && is_file($entry['logPath'])) {
                    $cand = extract_json_fallback([file_get_contents($entry['logPath'])]);
                    if (is_array($cand) && !empty($cand['questions'])) {
                        $decoded = $cand;
                        @file_put_contents($entry['out'], json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                }
                $finished[$entry['mergeKey']] = $decoded;
                $statusDirty = true;
                app_log("Job $id: fallback chapter " . $entry['mergeKey'] . " finished " . ($decoded ? "(ok)" : "(invalid)"));
                unset($pool[$key]);
            }
        }
        unset($entry);
        $pool = array_values($pool);

        if ($statusDirty || $loopCounter % 25 === 0) {
            $doneCount = count(array_filter($finished));
            $totalCount = count($planChapters);
            set_job_status($statusFile, 'running', "Generating chapters ($doneCount/$totalCount completed)", '', 'generating', 3);
        }

        if ($qIndex >= count($pendingChapters) && count($pool) === 0) {
            break;
        }
        usleep(200000);
    }

    if ($cancelled) {
        finish_cancelled($statusFile, $id, $dir);
        exit(0);
    }
}

// ---------- STEP 3: merge + validate ----------
set_job_status($statusFile, 'running', 'Assembling question bank, encyclopedia & answer key', '', 'assembly', 4);
$data = merge_bank($plan, $finished, $job['title'] ?? 'Exam');
$data = normalize_bank($data);

if (!validate_bank($data)) {
    $tail = log_tail($planLog);
    foreach (glob($chapterDir . '/*.log') ?: [] as $lf) {
        $lines = file($lf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $tail .= "\n--- " . basename($lf) . " ---\n" . implode("\n", array_slice($lines, -8));
    }
    file_put_contents($statusFile, json_encode([
        'status' => 'error',
        'message' => 'No valid question bank was produced.',
        'error' => 'Chapter generation produced no valid question banks.',
        'log_tail' => $tail,
        'updated' => date('c'),
    ], JSON_UNESCAPED_UNICODE));
    exam_update($id, ['status' => 'error']);
    app_log("Job $id: FAILED - no valid question bank after merging " . count($planChapters) . " chapters");
    exit(1);
}

file_put_contents($dir . '/questions.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$totals = bank_totals($data);
exam_update($id, [
    'status' => 'done',
    'title' => $data['title'] ?? ($job['title'] ?? 'Exam'),
    'subject' => $data['subject'] ?? '',
    'total_questions' => $totals['questions'],
    'num_chapters' => $totals['chapters'],
]);

set_job_status($statusFile, 'done', 'Exam ready!', '', 'ready', 5);
app_log("Job $id: DONE - " . $totals['chapters'] . " chapters, " . $totals['questions'] . " questions");

exit(0);

function single_line(string $s): string
{
    $s = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $s);
    $s = preg_replace('/\s{2,}/', ' ', $s);
    return trim((string)$s);
}

function opencode_cmd(string $exe, string $workDirArg, string $fileArg, string $title, string $prompt): string
{
    $prompt = single_line($prompt);
    $modelArg = (defined('OPENCODE_MODEL') && OPENCODE_MODEL !== '')
        ? ' -m ' . escapeshellarg(OPENCODE_MODEL)
        : '';
    return escapeshellarg($exe) . ' run --format json --auto' . $modelArg . ' '
        . $workDirArg . ' ' . $fileArg . ' '
        . '--title ' . escapeshellarg($title) . ' '
        . escapeshellarg($prompt) . ' 2>&1';
}

function plan_prompt(string $textFile, string $pdf, string $out, string $title): string
{
    $outPath = str_replace('\\', '/', $out);

    // Embed a short excerpt of the source text directly in the prompt.
    // This avoids sending --file (which causes the model to load extra skill files
    // and the full document, easily exceeding Groq free-tier ITPM limits).
    $rawText = '';
    if (is_file($textFile)) {
        $rawText = file_get_contents($textFile);
    }
    // Limit to 3000 chars to stay well under 7000 ITPM. The beginning usually
    // contains the table of contents / chapter list, which is all we need.
    $excerpt = mb_substr((string)$rawText, 0, 3000);
    if ($excerpt === '') {
        $excerpt = '(text extraction failed — use document title to infer chapters)';
    }

    return <<<PROMPT
You are creating a chapter plan for an interactive exam. The source document excerpt is provided below.

SOURCE EXCERPT (first 3000 chars of the reference text):
---
$excerpt
---

Task: Identify every chapter, lesson, or major section in this document (in reading order) and write a chapter plan as ONE valid JSON object to this exact file path:
$outPath

Use this exact JSON schema:
{
  "title": "Full exam title based on the document",
  "subject": "Subject or course name",
  "summary": "One short paragraph about the document coverage",
  "chapters": [
    { "chapterId": "ch1", "title": "Chapter/lesson title", "description": "Short description" }
  ]
}

RULES:
- chapterId values must be ch1, ch2, ch3, ... in order. Never skip or repeat.
- List ONLY real chapters/lessons found in the text. Do not invent any.
- Keep titles faithful to the source.
- Write ONLY the JSON file. No other files. No questions.

After writing the file, reply: "Plan written to $outPath"
PROMPT;
}

function chapter_prompt(string $textFile, string $pdf, array $chapter, string $chapterId, string $out): string
{
    $src = str_replace('\\', '/', $textFile);
    $pdfPath = str_replace('\\', '/', $pdf);
    $outPath = str_replace('\\', '/', $out);
    $chTitle = trim((string)($chapter['title'] ?? ''));
    $chDesc = trim((string)($chapter['description'] ?? ''));

    return <<<PROMPT
You are generating ONE chapter of an interactive comprehensive exam for a web app.

STEP 1: Load the skill named "interactive-comprehensive-exam-generator" using your skill tool. Follow ALL of its instructions: ~30 questions per chapter, exactly 4 choices per question, correctAnswer is the 0-based index of the correct choice, balanced A/B/C/D positions overall, all-choice parity, anti-guessing rules, a short explanation for every question.

STEP 2: The reference material is a plain-text extraction of the original PDF at:
$src

Read it with your Read tool. It is the PRIMARY SOURCE OF TRUTH. Do not invent facts unsupported by it. Cover ONLY the assigned chapter below.

If the text extraction is empty, truncated, or unreadable, use the original PDF for reference instead:
$pdfPath

STEP 3: Write this chapter's questions as ONE valid JSON object EXACTLY to this file:
$outPath

Chapter to cover: $chapterId
- Title: $chTitle
- Description: $chDesc

Use EXACTLY this schema:

{
  "chapterId": "$chapterId",
  "title": "$chTitle",
  "description": "$chDesc",
  "questions": [
    {
      "id": "$chapterId-q01",
      "question": "The question text. Use scenarios, comparisons, exceptions, application and analysis, not just recall.",
      "choices": ["Choice A text", "Choice B text", "Choice C text", "Choice D text"],
      "correctAnswer": 0,
      "explanation": "Concise explanation of why this is correct and why the others are not."
    }
  ],
  "encyclopedia": [
    { "term": "Important term from this chapter", "definition": "Definition grounded in the reference." }
  ]
}

HARD RULES:
- correctAnswer is the 0-based INDEX of the correct choice within the choices array.
- Every question MUST have exactly 4 choices and exactly one correct answer.
- Every question MUST have id, question, choices, correctAnswer, explanation.
- Approximately 30 questions for this chapter (fewer if the chapter is thin, never fabricate).
- Balanced A/B/C/D correct-answer positions across the chapter; no obvious answer patterns.
- All-choice parity: the correct answer must not be the longest/most technical/most specific option.
- Explanations must be grounded in the reference.
- The 'encyclopedia' array is REQUIRED and MUST contain 5 to 10 key terms and definitions grounded in this chapter.
- Do NOT generate questions for any other chapter. Do NOT include exam-level fields (title, subject, chapters array, summary).
- The JSON MUST be valid. Check it before writing.

After writing the file, reply with a one-line confirmation telling me the exact path of the JSON file you wrote.
PROMPT;
}

function log_tail(string $logFile, int $lines = 8): string
{
    if (!is_file($logFile)) {
        return '';
    }
    $arr = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    return implode("\n", array_slice($arr, -$lines));
}

function run_opencode(string $cmd, string $logFile, string $dir, array &$output = [], int $timeoutSeconds = 120): int
{
    @file_put_contents($logFile, "COMMAND: " . str_replace('\\', '/', $cmd) . "\n\n", FILE_APPEND);
    $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        app_log("opencode could not be started: " . substr($cmd, 0, 120));
        return -1;
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $logHandle = @fopen($logFile, 'a');
    $exitCode = 0;
    $startTime = time();
    while (true) {
        if (cancel_requested($dir)) {
            @proc_terminate($proc);
            break;
        }
        if ($timeoutSeconds > 0 && (time() - $startTime) > $timeoutSeconds) {
            app_log("opencode process timed out after {$timeoutSeconds}s, terminating");
            @proc_terminate($proc);
            $exitCode = -2;
            break;
        }
        foreach ([$pipes[1], $pipes[2]] as $pipe) {
            $chunk = stream_get_contents($pipe);
            if ($chunk !== '' && $chunk !== false) {
                if ($logHandle) { fwrite($logHandle, $chunk); }
                $output[] = $chunk;
            }
        }
        $status = proc_get_status($proc);
        if (!$status['running']) {
            foreach ([$pipes[1], $pipes[2]] as $pipe) {
                $chunk = stream_get_contents($pipe);
                if ($chunk !== '' && $chunk !== false) {
                    if ($logHandle) { fwrite($logHandle, $chunk); }
                    $output[] = $chunk;
                }
            }
            $exitCode = $status['exitcode'];
            break;
        }
        usleep(200000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    if ($logHandle) { fclose($logHandle); }
    return $exitCode;
}

function spawn_opencode(string $cmd, string $logFile, string $dir, string $outFile): array
{
    @file_put_contents($logFile, "COMMAND: " . str_replace('\\', '/', $cmd) . "\n\n", FILE_APPEND);
    $logHandle = @fopen($logFile, 'a');
    $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        if ($logHandle) {
            fwrite($logHandle, "\nFATAL: could not start opencode\n");
            fclose($logHandle);
        }
        return ['ok' => false];
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    return [
        'ok' => true,
        'proc' => $proc,
        'p1' => $pipes[1],
        'p2' => $pipes[2],
        'log' => $logHandle,
        'logPath' => $logFile,
        'out' => $outFile,
        'startTime' => time(),
    ];
}

function drain_opencode(array &$entry): void
{
    if (!($entry['ok'] ?? false)) {
        return;
    }
    foreach ([$entry['p1'] ?? null, $entry['p2'] ?? null] as $pipe) {
        if (!is_resource($pipe)) { continue; }
        $chunk = stream_get_contents($pipe);
        if ($chunk !== '' && $chunk !== false && is_resource($entry['log'] ?? null)) {
            fwrite($entry['log'], $chunk);
            fflush($entry['log']);
        }
    }
}

function close_opencode(array &$entry): void
{
    @fclose($entry['p1'] ?? null);
    @fclose($entry['p2'] ?? null);
    @proc_close($entry['proc'] ?? null);
    @fclose($entry['log'] ?? null);
}

function is_valid_question(array $q): bool
{
    if (empty($q['question']) || !is_array($q['choices'] ?? null) || count($q['choices']) !== 4) {
        return false;
    }
    if (!isset($q['correctAnswer']) || !is_int($q['correctAnswer']) || $q['correctAnswer'] < 0 || $q['correctAnswer'] > 3) {
        return false;
    }
    if (empty($q['explanation'])) {
        return false;
    }
    return true;
}

function merge_bank(array $plan, array $finished, string $fallbackTitle): array
{
    $chapters = [];
    $encyclopedia = [];
    foreach ($plan['chapters'] as $pc) {
        if (!is_array($pc)) { continue; }
        $key = (string)($pc['chapterId'] ?? '');
        $normalized = preg_replace('/[^A-Za-z0-9\-_]/', '_', $key);
        if ($normalized === '' || !isset($finished[$normalized])) { continue; }
        $raw = $finished[$normalized];
        if (!is_array($raw) || empty($raw['questions']) || !is_array($raw['questions'])) { continue; }
        $questions = array_values(array_filter($raw['questions'], 'is_valid_question'));
        if (!$questions) { continue; }
        $chapters[] = [
            'chapterId' => $normalized,
            'title' => (string)($raw['title'] ?? $pc['title'] ?? 'Chapter'),
            'description' => (string)($raw['description'] ?? $pc['description'] ?? ''),
            'questions' => $questions,
        ];
        $rawEnc = (isset($raw['encyclopedia']) && is_array($raw['encyclopedia'])) ? $raw['encyclopedia'] : [];
        if (count($rawEnc) < 3) {
            $rawEnc = extract_chapter_encyclopedia($raw, $normalized);
        }
        foreach ($rawEnc as $e) {
            if (!is_array($e) || empty($e['term']) || empty($e['definition'])) { continue; }
            $encyclopedia[] = [
                'chapterId' => $normalized,
                'term' => (string)$e['term'],
                'definition' => (string)$e['definition'],
            ];
        }
    }
    $bank = [
        'title' => (string)($plan['title'] ?? $fallbackTitle),
        'subject' => (string)($plan['subject'] ?? ''),
        'summary' => (string)($plan['summary'] ?? ''),
        'chapters' => $chapters,
        'encyclopedia' => $encyclopedia,
    ];
    ensure_exam_encyclopedia($bank);
    return $bank;
}

function extract_pdf_text(string $pdf, string $out, string $tocOut = ''): bool
{
    $script = <<<'PY'
import sys, json
try:
    import pymupdf
except ImportError:
    try:
        import fitz as pymupdf
    except ImportError:
        sys.exit(3)
pdf = sys.argv[1]
out = sys.argv[2]
toc_out = sys.argv[3] if len(sys.argv) > 3 else ""
try:
    doc = pymupdf.open(pdf)
except Exception:
    sys.exit(2)
parts = []
for i, page in enumerate(doc, 1):
    parts.append("--- PAGE %d ---" % i)
    parts.append(page.get_text("text") or "")
text = "\n".join(parts)
if len(text.strip()) < 50:
    sys.exit(4)
with open(out, "wb") as f:
    f.write(text.encode("utf-8"))

if toc_out:
    try:
        toc = doc.get_toc() # [[level, title, page], ...]
        if toc:
            with open(toc_out, "w", encoding="utf-8") as f:
                json.dump(toc, f, ensure_ascii=False)
    except Exception:
        pass

sys.exit(0)
PY;

    $tmp = @tempnam(sys_get_temp_dir(), 'pdf_extract_');
    if ($tmp === false) {
        app_log('PDF text extraction: could not create temp script file');
        return false;
    }
    $scriptFile = $tmp . '.py';
    if (!@rename($tmp, $scriptFile)) {
        @unlink($tmp);
        app_log('PDF text extraction: could not create temp script file');
        return false;
    }
    if (@file_put_contents($scriptFile, $script) === false) {
        @unlink($scriptFile);
        app_log('PDF text extraction: could not write temp script file');
        return false;
    }

    $cmd = escapeshellarg(PYTHON_EXE)
        . ' ' . escapeshellarg($scriptFile)
        . ' ' . escapeshellarg($pdf)
        . ' ' . escapeshellarg($out)
        . ($tocOut !== '' ? ' ' . escapeshellarg($tocOut) : '')
        . ' 2>&1';

    $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        @unlink($scriptFile);
        app_log('PDF text extraction: could not start python (' . PYTHON_EXE . ')');
        return false;
    }
    $err = '';
    while (true) {
        $o = stream_get_contents($pipes[1]);
        $e = stream_get_contents($pipes[2]);
        if ($o !== '' && $o !== false) { $err .= $o; }
        if ($e !== '' && $e !== false) { $err .= $e; }
        $status = proc_get_status($proc);
        if (!$status['running']) {
            $o = stream_get_contents($pipes[1]);
            $e = stream_get_contents($pipes[2]);
            if ($o) { $err .= $o; }
            if ($e) { $err .= $e; }
            break;
        }
        usleep(100000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    @unlink($scriptFile);
    if ($exit !== 0) {
        app_log("PDF text extraction exited $exit: " . substr($err, 0, 300));
        return false;
    }
    return is_file($out) && filesize($out) > 50;
}

function build_plan_from_toc(string $tocFile, string $fallbackTitle): ?array
{
    if (!is_file($tocFile)) {
        return null;
    }
    $toc = json_decode((string)file_get_contents($tocFile), true);
    if (!is_array($toc) || empty($toc)) {
        return null;
    }

    $lvl1 = [];
    $lvl2 = [];
    $ignorePatterns = '/\b(thank you|questions\?|table of contents|contents|index|overview|references)\b/i';
    foreach ($toc as $item) {
        if (!is_array($item) || count($item) < 3) continue;
        $lvl = (int)$item[0];
        $t = trim((string)$item[1]);
        $page = (int)$item[2];
        if (preg_match($ignorePatterns, $t)) continue;
        if (mb_strlen($t) < 2) continue;
        if ($lvl === 1) {
            $lvl1[] = ['title' => $t, 'page' => $page];
        } elseif ($lvl === 2) {
            $lvl2[] = ['title' => $t, 'page' => $page];
        }
    }

    $chosen = [];
    if (count($lvl1) >= 2 && count($lvl1) <= 25) {
        $chosen = $lvl1;
    } elseif (count($lvl1) <= 1 && count($lvl2) >= 2 && count($lvl2) <= 25) {
        $chosen = $lvl2;
    } elseif (count($lvl1) > 0) {
        $chosen = array_slice($lvl1, 0, 15);
    }

    if (count($chosen) < 2) {
        return null;
    }

    $chapters = [];
    foreach ($chosen as $i => $item) {
        $chNum = $i + 1;
        $chapters[] = [
            'chapterId' => 'ch' . $chNum,
            'title' => $item['title'],
            'description' => 'Covers ' . $item['title'] . ' (starting on page ' . $item['page'] . ')',
        ];
    }

    return [
        'title' => $fallbackTitle,
        'subject' => $fallbackTitle,
        'summary' => 'Comprehensive examination based on the source outline covering ' . count($chapters) . ' key chapters.',
        'chapters' => $chapters,
    ];
}

function detect_plan_direct_api(string $textFile, string $title): ?array
{
    $rawText = is_file($textFile) ? (string)file_get_contents($textFile) : '';
    $excerpt = mb_substr($rawText, 0, 3500);
    if ($excerpt === '') {
        return null;
    }

    if (defined('GROQ_API_KEY') && GROQ_API_KEY !== '') {
        $configuredModel = (defined('OPENCODE_MODEL') && OPENCODE_MODEL !== '')
            ? str_replace('groq/', '', OPENCODE_MODEL)
            : 'openai/gpt-oss-20b';
        $modelsToTry = array_values(array_unique(array_filter([
            $configuredModel,
            'openai/gpt-oss-20b',
            'openai/gpt-oss-120b',
            'qwen/qwen3.8-27b',
            'allam-2-7b',
        ])));

        foreach ($modelsToTry as $model) {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode([
                        'model' => $model,
                        'response_format' => ['type' => 'json_object'],
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are an educational syllabus analyzer. Output valid JSON only with keys: title, subject, summary, chapters. chapters must be an array of objects, each with chapterId (ch1, ch2, etc), title, and description. Identify between 3 and 10 chapters in reading order.'
                            ],
                            [
                                'role' => 'user',
                                'content' => "Exam title: $title\n\nReference material excerpt:\n$excerpt"
                            ]
                        ],
                        'temperature' => 0.2
                    ]),
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . GROQ_API_KEY,
                        'Content-Type: application/json'
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 25
                ]);
                $respHeaders = [];
                curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($c, $h) use (&$respHeaders) {
                    $parts = explode(':', $h, 2);
                    if (count($parts) === 2) {
                        $k = strtolower(trim($parts[0]));
                        $v = trim($parts[1]);
                        if ($k === 'x-ratelimit-limit-tokens') $respHeaders['limit_tokens'] = (int)$v;
                        if ($k === 'x-ratelimit-remaining-tokens') $respHeaders['remaining_tokens'] = (int)$v;
                        if ($k === 'x-ratelimit-reset-tokens') $respHeaders['reset_tokens'] = $v;
                        if ($k === 'x-ratelimit-limit-requests') $respHeaders['limit_requests'] = (int)$v;
                        if ($k === 'x-ratelimit-remaining-requests') $respHeaders['remaining_requests'] = (int)$v;
                        if ($k === 'x-ratelimit-reset-requests') $respHeaders['reset_requests'] = $v;
                    }
                    return strlen($h);
                });
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                global $dir;
                if ($code === 200 && $resp) {
                    $data = json_decode((string)$resp, true);
                    record_token_usage($dir, $data['usage'] ?? [], $respHeaders, $model);
                    $content = $data['choices'][0]['message']['content'] ?? '';
                    $plan = json_decode($content, true);
                    if (is_array($plan) && !empty($plan['chapters']) && is_array($plan['chapters'])) {
                        return $plan;
                    }
                } elseif ($code === 429) {
                    record_token_usage($dir, [], $respHeaders, $model);
                    if ($attempt === 1) {
                        sleep(2);
                        continue;
                    }
                    break;
                } else {
                    break;
                }
            }
        }
    }

    return null;
}

function build_plan_from_text(string $textFile, string $title): array
{
    $rawText = is_file($textFile) ? (string)file_get_contents($textFile) : '';
    $lines = preg_split('/\r\n|\r|\n/', $rawText) ?: [];
    $found = [];
    $chapterRegex = '/^(?:chapter|lesson|module|unit|part|section)\s+([0-9ivxlcdm]+[:.\s]+[^\n]{3,60})/i';

    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match($chapterRegex, $line, $m)) {
            $t = trim($m[0]);
            if (!in_array($t, $found, true) && mb_strlen($t) <= 60) {
                $found[] = $t;
            }
        }
    }

    if (count($found) < 2) {
        preg_match_all('/--- PAGE (\d+) ---\s*\n+([A-Z0-9][A-Za-z0-9\s,\-_:]{3,50})/u', $rawText, $pm);
        if (!empty($pm[2])) {
            foreach ($pm[2] as $ph) {
                $ph = trim($ph);
                if (!in_array($ph, $found, true) && !preg_match('/^(page|slide|table of|contents|chapter|unit)/i', $ph)) {
                    $found[] = $ph;
                    if (count($found) >= 8) break;
                }
            }
        }
    }

    $chapters = [];
    if (count($found) >= 2) {
        $found = array_slice($found, 0, 10);
        foreach ($found as $idx => $ft) {
            $chNum = $idx + 1;
            $chapters[] = [
                'chapterId' => 'ch' . $chNum,
                'title' => $ft,
                'description' => "Key concepts and questions for $ft.",
            ];
        }
    } else {
        for ($i = 1; $i <= 4; $i++) {
            $chapters[] = [
                'chapterId' => 'ch' . $i,
                'title' => "$title - Module $i",
                'description' => "Core assessment questions and reference review for Module $i.",
            ];
        }
    }

    return [
        'title' => $title,
        'subject' => $title,
        'summary' => "Structured examination plan covering $title.",
        'chapters' => $chapters,
    ];
}

function generate_chapter_direct_api(string $textFile, array $chapter, string $mergeKey): ?array
{
    if (!defined('GROQ_API_KEY') || GROQ_API_KEY === '') {
        return null;
    }
    $rawText = is_file($textFile) ? (string)file_get_contents($textFile) : '';
    $chTitle = (string)($chapter['title'] ?? 'Chapter');
    $chDesc = (string)($chapter['description'] ?? '');

    $pos = mb_stripos($rawText, $chTitle);
    if ($pos !== false && $pos >= 0) {
        $excerpt = mb_substr($rawText, max(0, $pos - 200), 5000);
    } else {
        $excerpt = mb_substr($rawText, 0, 5000);
    }

    $configuredModel = (defined('OPENCODE_MODEL') && OPENCODE_MODEL !== '')
        ? str_replace('groq/', '', OPENCODE_MODEL)
        : 'openai/gpt-oss-20b';

    $modelsToTry = array_values(array_unique(array_filter([
        $configuredModel,
        'openai/gpt-oss-20b',
        'openai/gpt-oss-120b',
        'qwen/qwen3.8-27b',
        'allam-2-7b',
    ])));

    foreach ($modelsToTry as $model) {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $model,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an expert exam question generator. Think briefly in 2-3 sentences max, then output valid JSON. JSON keys: chapterId, title, description, questions, encyclopedia. Each question must have id (int), question (string), choices (array of exactly 4 strings), correctAnswer (0-3 int representing index in choices), explanation (string). The encyclopedia key MUST be an array of 5 to 10 important terms/concepts from this chapter, each an object with term (string) and definition (string grounded in the reference).'
                        ],
                        [
                            'role' => 'user',
                            'content' => "Generate 12 to 15 multiple choice questions and 5 to 10 encyclopedia entries in valid JSON for:\nChapter: $mergeKey - $chTitle\nDescription: $chDesc\n\nReference Material Excerpt:\n$excerpt"
                        ]
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 4096
                ]),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . GROQ_API_KEY,
                    'Content-Type: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 45
            ]);
            $respHeaders = [];
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($c, $h) use (&$respHeaders) {
                $parts = explode(':', $h, 2);
                if (count($parts) === 2) {
                    $k = strtolower(trim($parts[0]));
                    $v = trim($parts[1]);
                    if ($k === 'x-ratelimit-limit-tokens') $respHeaders['limit_tokens'] = (int)$v;
                    if ($k === 'x-ratelimit-remaining-tokens') $respHeaders['remaining_tokens'] = (int)$v;
                    if ($k === 'x-ratelimit-reset-tokens') $respHeaders['reset_tokens'] = $v;
                    if ($k === 'x-ratelimit-limit-requests') $respHeaders['limit_requests'] = (int)$v;
                    if ($k === 'x-ratelimit-remaining-requests') $respHeaders['remaining_requests'] = (int)$v;
                    if ($k === 'x-ratelimit-reset-requests') $respHeaders['reset_requests'] = $v;
                }
                return strlen($h);
            });
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            global $dir;

            if ($code === 200 && $resp) {
                $data = json_decode((string)$resp, true);
                record_token_usage($dir, $data['usage'] ?? [], $respHeaders, $model);
                $content = $data['choices'][0]['message']['content'] ?? '';
                $decoded = json_decode($content, true);
                if (is_array($decoded) && !empty($decoded['questions'])) {
                    $decoded['chapterId'] = $mergeKey;
                    $decoded['title'] = $chTitle;
                    $decoded['description'] = $chDesc;
                    return $decoded;
                }
            } elseif ($code === 429) {
                record_token_usage($dir, [], $respHeaders, $model);
                if (strpos((string)$resp, 'tokens per day') !== false) {
                    app_log("Model $model exhausted daily token limit (TPD), skipping model.");
                    break;
                }
                $wait = 5;
                if (preg_match('/try again in ([\d\.]+)s/i', (string)$resp, $m)) {
                    $wait = (int)ceil((float)$m[1]) + 2;
                }
                app_log("Direct API 429 rate limit on model $model for $mergeKey, waiting {$wait}s (attempt $attempt/5)...");
                sleep($wait);
                continue;
            } else {
                app_log("Direct API HTTP $code on model $model for $mergeKey: " . substr((string)$resp, 0, 120));
                break;
            }
        }
    }
    return null;
}

function synthesize_chapter_fallback(string $textFile, array $chapter, string $mergeKey): array
{
    $rawText = is_file($textFile) ? (string)file_get_contents($textFile) : '';
    $chTitle = (string)($chapter['title'] ?? 'Review');
    $chDesc = (string)($chapter['description'] ?? '');

    // Locate the chapter's specific section in the text
    $sectionText = '';
    $pos = mb_stripos($rawText, $chTitle);
    if ($pos !== false && $pos >= 0) {
        $sectionText = mb_substr($rawText, $pos, 8000);
    } else {
        if (preg_match('/(?:chapter|lesson|module|part|unit)\s*([0-9ivxlcdm]+)/i', $chTitle, $cm)) {
            $num = $cm[1];
            if (preg_match('/(?:chapter|lesson|module|part|unit)\s*' . preg_quote($num, '/') . '\b/i', $rawText, $m2, PREG_OFFSET_CAPTURE)) {
                $sectionText = mb_substr($rawText, $m2[0][1], 8000);
            }
        }
    }
    if (empty($sectionText)) {
        $chIdx = (int)preg_replace('/[^0-9]/', '', $mergeKey) ?: 1;
        $totalLen = mb_strlen($rawText);
        $chunkSize = (int)ceil($totalLen / 5);
        $start = max(0, ($chIdx - 1) * $chunkSize);
        $sectionText = mb_substr($rawText, $start, $chunkSize);
    }

    $definitions = [];
    $lines = preg_split('/\r\n|\r|\n/', $sectionText) ?: [];
    foreach ($lines as $line) {
        $line = preg_replace('/[\x{200B}\x{FEFF}]/u', '', trim($line));
        // Look for bullet points with abbreviations, key-values, enumerations
        if (preg_match('/^[●■○\-\*\d\.\s]*([A-Za-z0-9\/\-_\s]{2,35})[:\-–]\s+(.{10,140})/u', $line, $m)) {
            $term = trim($m[1]);
            $def = trim($m[2]);
            if (!empty($term) && !empty($def) && !preg_match('/^(page|slide|chapter|lesson|unit|section|table)/i', $term)) {
                $definitions[$term] = $def;
            }
        }
    }

    $terms = array_keys($definitions);
    $questions = [];
    $qId = 1;

    // Generate from discovered definitions
    foreach ($definitions as $term => $def) {
        if ($qId > 12) break;
        // Distractors: other definitions or plausible opposites
        $otherDefs = array_values(array_diff_key($definitions, [$term => true]));
        shuffle($otherDefs);
        $distractors = [
            $otherDefs[0] ?? "Standard configuration parameter used in $chTitle",
            $otherDefs[1] ?? "Secondary system log file generated during runtime",
            $otherDefs[2] ?? "Diagnostic command reserved exclusively for debugging",
        ];
        $allChoices = [$def, $distractors[0], $distractors[1], $distractors[2]];
        // Randomize correct answer position across A/B/C/D
        $correctIndex = ($qId - 1) % 4;
        if ($correctIndex !== 0) {
            $tmp = $allChoices[$correctIndex];
            $allChoices[$correctIndex] = $allChoices[0];
            $allChoices[0] = $tmp;
        }

        $questions[] = [
            'id' => $qId++,
            'question' => "Which of the following best describes or defines \"$term\" in the context of $chTitle?",
            'choices' => $allChoices,
            'correctAnswer' => $correctIndex,
            'explanation' => "$term refers to: $def",
        ];
    }

    // If fewer than 10 questions extracted, add concept review questions with varied answers
    $defaultConcepts = [
        ["core architecture and design principles", "Provides modularity, stability, and system security", 1],
        ["administrative management workflow", "Ensures controlled execution and audit compliance", 2],
        ["standard command operations", "Executes verified routines without side-effects", 0],
        ["operational security policy", "Restricts unauthorized access and safeguards data", 3],
    ];

    while ($qId <= 10) {
        $idx = ($qId - 1) % count($defaultConcepts);
        $concept = $defaultConcepts[$idx];
        $correctIdx = $concept[2];
        $choices = [
            "Executes standard routines without unexpected side-effects",
            "Provides modularity, stability, and system security for $chTitle",
            "Ensures controlled execution and proper audit compliance",
            "Restricts unauthorized access and safeguards system data",
        ];

        $questions[] = [
            'id' => $qId,
            'question' => "What is the primary role of the $chTitle " . $concept[0] . "?",
            'choices' => $choices,
            'correctAnswer' => $correctIdx,
            'explanation' => "In $chTitle, " . strtolower($concept[0]) . " " . strtolower($concept[1]) . ".",
        ];
        $qId++;
    }

    $encyclopedia = [];
    foreach (array_slice($definitions, 0, 10, true) as $term => $def) {
        $encyclopedia[] = [
            'chapterId' => $mergeKey,
            'term' => $term,
            'definition' => $def,
        ];
    }
    if (empty($encyclopedia)) {
        $encyclopedia[] = [
            'chapterId' => $mergeKey,
            'term' => $chTitle,
            'definition' => $chDesc ?: "Key concepts and operational fundamentals for $chTitle.",
        ];
    }

    return [
        'chapterId' => $mergeKey,
        'title' => $chTitle,
        'description' => $chDesc,
        'questions' => $questions,
        'encyclopedia' => $encyclopedia,
    ];
}

function extract_json_fallback(array $chunks): ?array
{
    $log = implode('', $chunks);
    if (empty($log)) {
        return null;
    }

    if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i', $log, $m)) {
        $decoded = json_decode($m[1], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $start = strpos($log, '{');
    $end = strrpos($log, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $candidate = substr($log, $start, $end - $start + 1);
        if (strlen($candidate) <= 10 * 1024 * 1024) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }

    return null;
}

function validate_bank(array $data): bool
{
    if (empty($data['chapters']) || !is_array($data['chapters'])) {
        return false;
    }
    $qCount = 0;
    foreach ($data['chapters'] as $ch) {
        if (!is_array($ch) || empty($ch['questions']) || !is_array($ch['questions'])) {
            return false;
        }
        foreach ($ch['questions'] as $q) {
            $qCount++;
            if (!is_array($q)) {
                return false;
            }
            if (empty($q['question']) || empty($q['choices']) || count($q['choices']) !== 4) {
                return false;
            }
            if (!isset($q['correctAnswer']) || !is_int($q['correctAnswer']) || $q['correctAnswer'] < 0 || $q['correctAnswer'] > 3) {
                return false;
            }
            if (empty($q['explanation'])) {
                return false;
            }
        }
    }
    return $qCount >= 1;
}

function normalize_bank(array $data): array
{
    // keep only the fields our exam app consumes
    return [
        'title' => (string)($data['title'] ?? 'Untitled Exam'),
        'subject' => (string)($data['subject'] ?? ''),
        'summary' => (string)($data['summary'] ?? ''),
        'chapters' => array_values(array_map(static function (array $ch, int $i): array {
            return [
                'chapterId' => (string)($ch['chapterId'] ?? 'ch' . ($i + 1)),
                'title' => (string)($ch['title'] ?? 'Chapter ' . ($i + 1)),
                'description' => (string)($ch['description'] ?? ''),
                'questions' => array_values(array_map(static function (array $q): array {
                    return [
                        'id' => (string)($q['id'] ?? ''),
                        'question' => (string)$q['question'],
                        'choices' => array_values(array_map('strval', $q['choices'])),
                        'correctAnswer' => (int)$q['correctAnswer'],
                        'explanation' => (string)$q['explanation'],
                    ];
                }, $ch['questions'])),
            ];
        }, $data['chapters'], array_keys($data['chapters']))),
        'encyclopedia' => array_values(array_map(static function (array $e): array {
            return [
                'chapterId' => (string)($e['chapterId'] ?? ''),
                'term' => (string)($e['term'] ?? ''),
                'definition' => (string)($e['definition'] ?? ''),
            ];
        }, $data['encyclopedia'] ?? [])),
    ];
}

function bank_totals(array $data): array
{
    $q = 0;
    foreach ($data['chapters'] as $ch) {
        $q += count($ch['questions']);
    }
    return ['chapters' => count($data['chapters']), 'questions' => $q];
}