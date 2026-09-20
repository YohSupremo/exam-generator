<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . '/data');
define('EXAMS_DIR', DATA_DIR . '/exams');
define('DB_PATH', DATA_DIR . '/exam.db');
define('SKILL_NAME', 'interactive-comprehensive-exam-generator');
define('SKILL_DIR', APP_ROOT . '/.opencode/skills/' . SKILL_NAME);

define('MAX_UPLOAD_BYTES', 50 * 1024 * 1024);

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
}

function exam_dir(string $id): string
{
    return EXAMS_DIR . '/' . preg_replace('/[^A-Za-z0-9\-_]/', '', $id);
}