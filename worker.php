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
    }
});
$dir = $id !== '' ? exam_dir($id) : '';

if ($id === '' || !is_dir($dir)) {
    fwrite(STDERR, "Job not found: $id\n");
    exit(1);
}

$job = json_decode((string)file_get_contents($dir . '/job.json'), true);
if (!is_array($job)) {
    fwrite(STDERR, "No job metadata\n");
    exit(1);
}

$statusFile = $job['status_file'] ?? $dir . '/status.json';
$logFile = $job['log'] ?? $dir . '/opencode.log';
$outFile = $job['output'] ?? $dir . '/questions.json';

function set_job_status(string $statusFile, string $status, string $message, string $error = ''): void
{
    file_put_contents($statusFile, json_encode([
        'status' => $status,
        'message' => $message,
        'error' => $error,
        'updated' => date('c'),
    ], JSON_UNESCAPED_UNICODE));
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

set_job_status($statusFile, 'running', 'Generating exam');
exam_update($id, ['status' => 'running']);
app_log("Job $id: worker started");

$textFile = $dir . '/source.txt';
$extracted = extract_pdf_text($pdf, $textFile);
$sourceForModel = $extracted ? $textFile : $pdf;
if ($extracted) {
    app_log("Job $id: extracted PDF text -> " . filesize($textFile) . " bytes");
} else {
    app_log("Job $id: WARNING - PDF text extraction failed, using PDF directly");
}

$prompt = build_prompt($job, $textFile, $pdf);

$exe = OPENCODE_EXE;
$workDirArg = '--dir ' . escapeshellarg(str_replace('\\', '/', APP_ROOT));
$fileArg = '--file ' . escapeshellarg($sourceForModel);
$title = $job['title'] ?? 'Exam generation';
$titleArg = '--title ' . escapeshellarg($title);
$promptArg = escapeshellarg($prompt);

$cmd = escapeshellarg($exe) . ' run --format json --auto '
    . $workDirArg . ' ' . $fileArg . ' ' . $titleArg . ' ' . $promptArg . ' 2>&1';

app_log("Job $id: running opencode command");
$cmdRender = str_replace('\\', '/', $cmd);
file_put_contents($logFile, "COMMAND: $cmdRender\n\n", FILE_APPEND);

$logHandle = fopen($logFile, 'a');
if ($logHandle === false) {
    $logHandle = tmpfile();
}

$output = [];
$exitCode = 0;
$isRunning = true;

if (!is_null($logHandle)) {
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (is_resource($proc)) {
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        while ($isRunning) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            if ($out !== '' && $out !== false) {
                fwrite($logHandle, $out);
                $output[] = $out;
            }
            if ($err !== '' && $err !== false) {
                fwrite($logHandle, $err);
                $output[] = $err;
            }
            $status = proc_get_status($proc);
            if (!$status['running']) {
                $isRunning = false;
                // drain any remaining output
                $out = stream_get_contents($pipes[1]);
                $err = stream_get_contents($pipes[2]);
                if ($out) { fwrite($logHandle, $out); $output[] = $out; }
                if ($err) { fwrite($logHandle, $err); $output[] = $err; }
                $exitCode = $status['exitcode'];
            } else {
                usleep(200000);
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
    } else {
        fwrite($logHandle, "FATAL: could not start opencode\n");
        fclose($logHandle);
        set_job_status($statusFile, 'error', 'Could not start opencode.', 'Could not start opencode process.');
        exam_update($id, ['status' => 'error']);
        exit(1);
    }
    fclose($logHandle);
}

app_log("Job $id: opencode exited with code $exitCode");

$data = null;
if (is_file($outFile)) {
    $data = json_decode((string)file_get_contents($outFile), true);
    if (!is_array($data)) {
        app_log("Job $id: questions.json is not valid JSON, trying log fallback");
        $data = extract_json_fallback($output);
    }
}

if (!is_array($data) || !validate_bank($data)) {
    $tail = '';
    if (is_file($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $tail = implode("\n", array_slice($lines, -30));
    }
    file_put_contents(
        $dir . '/status.json',
        json_encode([
            'status' => 'error',
            'message' => 'No valid question bank was produced.',
            'error' => 'opencode exited but produced no valid question bank JSON.',
            'log_tail' => $tail,
            'updated' => date('c'),
        ], JSON_UNESCAPED_UNICODE)
    );
    exam_update($id, ['status' => 'error']);
    app_log("Job $id: FAILED - no valid question bank");
    exit(1);
}

$data = normalize_bank($data);
file_put_contents($outFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$totals = bank_totals($data);
exam_update($id, [
    'status' => 'done',
    'title' => $data['title'] ?? ($job['title'] ?? 'Exam'),
    'subject' => $data['subject'] ?? '',
    'total_questions' => $totals['questions'],
    'num_chapters' => $totals['chapters'],
]);

set_job_status($statusFile, 'done', 'Exam ready.', '');
app_log("Job $id: DONE - " . $totals['chapters'] . " chapters, " . $totals['questions'] . " questions");

exit(0);

function build_prompt(array $job, string $textFile, string $pdf): string
{
    $src = str_replace('\\', '/', $textFile);
    $pdfPath = str_replace('\\', '/', $pdf);
    $out = str_replace('\\', '/', $job['output']);

    return <<<PROMPT
You are generating an interactive comprehensive exam system for a web app.

STEP 1: Load the skill named "interactive-comprehensive-exam-generator" using your skill tool. Follow ALL of its instructions exactly (chapter organization of ~30 questions/chapter, timed/untimed modes, overall exam, answer key, encyclopedia, anti-guessing rules, balanced answers, stable question IDs, explanation for every question).

STEP 2: The reference material is a plain-text extraction of the original PDF, located at:
$src

Read it with your Read tool. It is the PRIMARY SOURCE OF TRUTH. Do not invent facts unsupported by it. Identify its chapters/lessons and build a coverage plan, then generate the question bank.

If the text extraction is empty, truncated, or unreadable, use the original PDF for reference instead:
$pdfPath

STEP 3: Write the question bank as ONE valid JSON object EXACTLY to this file:
$out

Do NOT create any HTML, CSS, JS, markdown, or other files. Do NOT print the JSON in your final reply; just write the file.

Use EXACTLY this JSON schema:

{
  "title": "Full exam title",
  "subject": "Subject or course name",
  "summary": "One short paragraph about coverage",
  "chapters": [
    {
      "chapterId": "ch1",
      "title": "Name of chapter/lesson 1",
      "description": "Short description of this chapter",
      "questions": [
        {
          "id": "ch1-q01",
          "question": "The question text. Use scenarios, comparisons, exceptions, application and analysis, not just recall.",
          "choices": ["Choice A text", "Choice B text", "Choice C text", "Choice D text"],
          "correctAnswer": 0,
          "explanation": "Concise explanation of why this is correct and why the others are not."
        }
      ]
    }
  ],
  "encyclopedia": [
    { "chapterId": "ch1", "term": "Important term", "definition": "Definition grounded in the reference." }
  ]
}

HARD RULES for the JSON:
- correctAnswer is the 0-based INDEX of the correct choice within the choices array.
- Every question MUST have exactly 4 choices and exactly one correct answer.
- Every question MUST have id, question, choices, correctAnswer, explanation.
- Every chapter MUST have chapterId, title, description, and at least 1 question.
- Approximately 30 questions per chapter (fewer if the chapter is thin, never fabricate).
- Balanced A/B/C/D correct-answer positions overall; no obvious answer patterns.
- All-choice parity: correct answer must not be the longest/most technical/most specific option.
- Explanations must be grounded in the reference.
- encyclopedia MUST contain the important terms from the reference, at least 10 entries unless the reference is very small.
- The JSON MUST be valid. Check it before writing.

After writing the file, reply with a one-line confirmation telling me the exact path of the JSON file you wrote.
PROMPT;
}

function extract_pdf_text(string $pdf, string $out): bool
{
    $script = <<<'PY'
import sys
try:
    import pymupdf
except ImportError:
    try:
        import fitz as pymupdf
    except ImportError:
        sys.exit(3)
pdf = sys.argv[1]
out = sys.argv[2].encode('utf-8')
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
with open(sys.argv[2], "wb") as f:
    f.write(out + b"\n" + text.encode("utf-8"))
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

function extract_json_fallback(array $chunks): ?array
{
    $log = implode('', $chunks);
    // find the last balanced { ... } block that looks like a question bank
    $start = strrpos($log, '{');
    if ($start === false) {
        return null;
    }
    // naive brace matching from the last opening brace
    $depth = 0;
    $inStr = false;
    $esc = false;
    $end = null;
    $len = strlen($log);
    for ($i = $start; $i < $len; $i++) {
        $c = $log[$i];
        if ($inStr) {
            if ($esc) {
                $esc = false;
            } elseif ($c === '\\') {
                $esc = true;
            } elseif ($c === '"') {
                $inStr = false;
            }
            continue;
        }
        if ($c === '"') {
            $inStr = true;
        } elseif ($c === '{') {
            $depth++;
        } elseif ($c === '}') {
            $depth--;
            if ($depth === 0) {
                $end = $i + 1;
                break;
            }
        }
    }
    if ($end === null) {
        return null;
    }
    $candidate = substr($log, $start, $end - $start);
    if (strlen($candidate) > 5 * 1024 * 1024) {
        return null;
    }
    $decoded = json_decode($candidate, true);
    return is_array($decoded) ? $decoded : null;
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