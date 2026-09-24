<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . '/data');
define('EXAMS_DIR', DATA_DIR . '/exams');
define('DB_PATH', DATA_DIR . '/exam.db');
define('SKILL_NAME', 'interactive-comprehensive-exam-generator');
define('SKILL_DIR', APP_ROOT . '/.opencode/skills/' . SKILL_NAME);

define('MAX_UPLOAD_BYTES', 50 * 1024 * 1024);

// ETA estimation + parallel generation (used by api/status.php and worker.php)
define('CONCURRENCY', 3);                 // max simultaneous per-chapter opencode runs
define('PLAN_EST_SECONDS', 5);            // baseline time for the chapter-detection step
define('DEFAULT_EST_CHAPTERS', 7);        // fallback chapter count when the PDF can't be sized
define('CHAPTERS_PER_PAGE_EST', 0.7);     // rough pages -> chapters for sizing a new job
define('EST_CHAPTERS_MIN', 3);
define('EST_CHAPTERS_MAX', 14);
define('DEFAULT_SECONDS_PER_CHAPTER', 6); // high-speed direct AI per-chapter generation time

// Load .env if present
if (is_file(APP_ROOT . '/.env')) {
    foreach (file(APP_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($k, $v) = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

// AI Provider Credentials & Model Configuration
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');
define('OPENROUTER_API_KEY', getenv('OPENROUTER_API_KEY') ?: '');
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');

// Auto-select recommended model based on available API key if not overridden
$defaultModel = '';
if (GROQ_API_KEY !== '') {
    putenv('GROQ_API_KEY=' . GROQ_API_KEY);
    $defaultModel = 'groq/openai/gpt-oss-20b';
} elseif (OPENROUTER_API_KEY !== '') {
    putenv('OPENROUTER_API_KEY=' . OPENROUTER_API_KEY);
    $defaultModel = 'openrouter/meta-llama/llama-3.3-70b-instruct:free';
} elseif (GEMINI_API_KEY !== '') {
    putenv('GEMINI_API_KEY=' . GEMINI_API_KEY);
    $defaultModel = 'google/gemini-2.0-flash';
}

define('OPENCODE_MODEL', getenv('OPENCODE_MODEL') ?: $defaultModel);

define(
    'OPENCODE_EXE',
    getenv('OPENCODE_EXE') ?: 'C:\Users\kelly\AppData\Roaming\npm\node_modules\opencode-ai\bin\opencode.exe'
);

define(
    'PHP_EXE',
    getenv('PHP_EXE') ?: 'C:\xampp\php\php.exe'
);

define(
    'PYTHON_EXE',
    getenv('PYTHON_EXE') ?: 'C:\Users\kelly\AppData\Local\Programs\Python\Python311\python.exe'
);

function app_log(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents(APP_ROOT . '/data/app.log', $line, FILE_APPEND);
    if (PHP_SAPI === 'cli') {
        echo $line;
    }
}

function exam_dir(string $id): string
{
    return EXAMS_DIR . '/' . preg_replace('/[^A-Za-z0-9\-_]/', '', $id);
}