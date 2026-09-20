<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');

function fail(string $message): never
{
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function run_cmd(string $cmd): array
{
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        return ['ok' => false, 'output' => ''];
    }
    $out = '';
    while (true) {
        $o = stream_get_contents($pipes[1]);
        $e = stream_get_contents($pipes[2]);
        if ($o !== '' && $o !== false) { $out .= $o; }
        if ($e !== '' && $e !== false) { $out .= $e; }
        $status = proc_get_status($proc);
        if (!$status['running']) {
            $o = stream_get_contents($pipes[1]);
            $e = stream_get_contents($pipes[2]);
            if ($o) { $out .= $o; }
            if ($e) { $out .= $e; }
            break;
        }
        usleep(100000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    return ['ok' => $exit === 0, 'output' => $out];
}

$op = $_GET['op'] ?? null;

if ($op === 'delete') {
    $id = preg_replace('/[^A-Za-z0-9\-_]/', '', $_GET['id'] ?? '');
    if ($id === '' || !exam_get($id)) {
        fail('Exam not found');
    }
    $dir = exam_dir($id);
    if (is_dir($dir)) {
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
    db()->prepare('DELETE FROM exams WHERE id = :id')->execute([':id' => $id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    fail('POST required');
}

if (!is_file(PHP_EXE)) {
    fail('PHP CLI not found. Set PHP_EXE in lib/config.php.');
}
if (!is_file(OPENCODE_EXE)) {
    fail('opencode CLI not found. Set OPENCODE_EXE in lib/config.php.');
}
if (!is_file(SKILL_DIR . '/SKILL.md')) {
    fail('Exam-generator skill is not installed in .opencode/skills/.');
}

if (empty($_FILES['pdf']) || ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    fail('No PDF uploaded.');
}

$upload = $_FILES['pdf'];
if ($upload['size'] <= 0 || $upload['size'] > MAX_UPLOAD_BYTES) {
    fail('PDF must be between 1 byte and ' . (int)(MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $upload['tmp_name']);
finfo_close($finfo);
if ($mime !== 'application/pdf') {
    fail('File is not a PDF (detected ' . $mime . ').');
}

$id = strtolower(preg_replace('/[^A-Za-z0-9]/', '', base_convert((string)bin2hex(random_bytes(9)), 16, 36)));
$dir = exam_dir($id);
if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
    fail('Could not create job directory.');
}

$sourceName = basename((string)($_POST['title'] ?: $upload['name']), '.pdf');
if ($sourceName === '') {
    $sourceName = 'Untitled';
}
$sourceName = substr(preg_replace('/[^\w\s\-\.,\(\)]/u', '', $sourceName), 0, 80);

$pdfPath = $dir . '/source.pdf';
if (!move_uploaded_file($upload['tmp_name'], $pdfPath)) {
    fail('Could not save the uploaded PDF.');
}

exam_create($id, htmlspecialchars($sourceName));

$jobMeta = [
    'id' => $id,
    'title' => $sourceName,
    'pdf' => $pdfPath,
    'output' => $dir . '/questions.json',
    'status_file' => $dir . '/status.json',
    'log' => $dir . '/opencode.log',
    'created' => date('c'),
];
file_put_contents($dir . '/job.json', json_encode($jobMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($jobMeta['status_file'], json_encode(['status' => 'queued', 'message' => 'Queued'], JSON_UNESCAPED_UNICODE));

app_log("Job $id created (source: $sourceName)");

$phpExe = str_replace('\\', '/', PHP_EXE);
$workerScript = str_replace('\\', '/', __DIR__ . '/../worker.php');
$taskName = 'autoExam_' . $id;

$taskCmd = 'schtasks /Create /TN ' . $taskName
    . ' /TR "\"' . $phpExe . '\" \"' . $workerScript . '\" ' . $id . '"'
    . ' /SC ONCE /ST 00:00 /F';
$created = run_cmd($taskCmd);
if (!$created['ok']) {
    @unlink(__DIR__ . '/../data/.launch_' . $id . '.ps1');
    fail('Could not create generation task: ' . trim($created['output']));
}
$ran = run_cmd('schtasks /Run /TN ' . $taskName);
if (!$ran['ok']) {
    run_cmd('schtasks /Delete /TN ' . $taskName . ' /F');
    fail('Could not start generation task: ' . trim($ran['output']));
}

echo json_encode(['ok' => true, 'id' => $id]);
exit;