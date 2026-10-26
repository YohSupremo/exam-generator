<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');

function fail(string $message): never
{
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function normalize_html_answers(array $data): array
{
    if (isset($data['questions']) && is_array($data['questions'])) {
        $answers = [];
        $questions = [];
        foreach ($data['questions'] as $q) {
            if (!is_array($q)) {
                continue;
            }
            $qid = (string)($q['id'] ?? '');
            if ($qid === '') {
                $qid = 'q' . count($questions);
            }
            $answer = 0;
            $found = false;
            foreach (['answer', 'correctAnswer', 'correct', 'correct_index', 'correctIndex', 'expectedAnswer'] as $k) {
                if (array_key_exists($k, $q) && $q[$k] !== null) {
                    $answer = (int)$q[$k];
                    $found = true;
                    break;
                }
            }
            if (!$found && isset($q['choices']) && is_array($q['choices']) && array_key_exists('answer', $q)) {
                $ansText = trim((string)$q['answer']);
                foreach ($q['choices'] as $ci => $c) {
                    if (is_string($c) && strcasecmp(trim($c), $ansText) === 0) {
                        $answer = (int)$ci;
                        $found = true;
                        break;
                    }
                }
            }
            $questions[] = ['id' => $qid, 'answer' => $answer];
            $answers[] = $answer;
        }
        return ['answers' => array_values($answers), 'questions' => $questions];
    }

    foreach (['answers', 'correctAnswers', 'correct_answers', 'answerKeys', 'correct'] as $k) {
        if (isset($data[$k]) && is_array($data[$k])) {
            $answers = [];
            foreach ($data[$k] as $v) {
                $answers[] = (int)$v;
            }
            $questions = [];
            foreach ($answers as $i => $a) {
                $questions[] = ['id' => 'q' . $i, 'answer' => (int)$a];
            }
            return ['answers' => array_values($answers), 'questions' => $questions];
        }
    }

    return ['answers' => [], 'questions' => []];
}

function extract_html_answers(string $html): array
{
    $patterns = [
        '/<script[^>]*id=["\']exam-data["\'][^>]*>([\s\S]*?)<\/script>/i',
        '/(?:window\.)?(?:var|let|const)\s+EXAM_DATA\s*=\s*(\{[\s\S]*?\});/i',
        '/(?:window\.)?(?:var|let|const)\s+EXAM\s*=\s*(\{[\s\S]*?\});/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $m)) {
            $data = json_decode($m[1], true);
            if (is_array($data)) {
                return normalize_html_answers($data);
            }
        }
    }
    return ['answers' => [], 'questions' => []];
}

function get_exam_answers(string $examId): array
{
    $examRow = html_exam_get($examId);
    if (!$examRow) {
        return ['answers' => [], 'questions' => []];
    }
    $answersData = json_decode((string)($examRow['answers_json'] ?? '{}'), true);
    return is_array($answersData) ? $answersData : ['answers' => [], 'questions' => []];
}

function grade_html_submission(array $answersData, array $givenAnswers): array
{
    $correctAnswers = (isset($answersData['answers']) && is_array($answersData['answers'])) ? array_values(array_map('intval', $answersData['answers'])) : [];
    $questions = (isset($answersData['questions']) && is_array($answersData['questions'])) ? $answersData['questions'] : [];
    $given = [];
    if (array_is_list($givenAnswers)) {
        foreach ($givenAnswers as $v) {
            $given[] = (int)$v;
        }
    } else {
        foreach ($questions as $i => $q) {
            $key = (string)($q['id'] ?? '');
            if (array_key_exists($key, $givenAnswers)) {
                $given[$i] = (int)$givenAnswers[$key];
            }
        }
    }
    $total = count($correctAnswers);
    $earned = 0;
    $results = [];
    for ($i = 0; $i < $total; $i++) {
        $ok = isset($given[$i]) && (int)$given[$i] === (int)$correctAnswers[$i];
        if ($ok) {
            $earned++;
        }
        $results[] = ['index' => $i, 'correct' => $ok];
    }
    return [
        'score' => $total > 0 ? round($earned / $total, 4) : 0.0,
        'correct' => (int)$earned,
        'total' => $total,
        'results' => $results,
    ];
}

function finalize_html_attempt(string $attemptId, array $answersData, array $givenAnswers): array
{
    $grade = grade_html_submission($answersData, $givenAnswers);
    $now = date('c');
    html_attempt_update($attemptId, [
        'status' => 'submitted',
        'answers_json' => (string)json_encode($answersData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'score' => $grade['score'],
        'correct' => $grade['correct'],
        'total' => $grade['total'],
        'submitted_at' => $now,
    ]);
    return [
        'ok' => true,
        'attempt' => null,
        'status' => 'submitted',
        'score' => $grade['score'],
        'correct' => $grade['correct'],
        'total' => $grade['total'],
        'results' => $grade['results'],
    ];
}

$op = (string)($_GET['op'] ?? 'create');

if ($op === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        fail('POST required');
    }
    $id = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)($_GET['id'] ?? ''));
    if ($id === '') {
        fail('Missing exam ID');
    }
    $dir = HTML_EXAMS_DIR . '/' . $id;
    if (is_dir($dir)) {
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
    db()->prepare('DELETE FROM html_exams WHERE id = :id')->execute([':id' => $id]);
    db()->prepare('DELETE FROM html_exam_attempts WHERE exam_id = :exam_id')->execute([':exam_id' => $id]);
    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

if ($op === 'start') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        fail('POST required');
    }
    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload) || !isset($payload['exam_id']) || !is_string($payload['exam_id'])) {
        fail('Invalid request. Expected JSON body with "exam_id".');
    }
    $examId = preg_replace('/[^A-Za-z0-9\-_]/', '', $payload['exam_id']);
    if ($examId === '') {
        fail('Missing exam ID');
    }
    $examRow = html_exam_get($examId);
    if (!$examRow) {
        fail('Exam not found');
    }
    $userToken = get_or_create_user_token();
    $attempt = html_attempt_start($userToken, $examId);
    echo json_encode([
        'ok' => true,
        'id' => $examId,
        'attempt_id' => (string)$attempt['id'],
        'attempt_no' => (int)$attempt['attempt_no'],
        'status' => (string)$attempt['status'],
    ]);
    exit;
}

if ($op === 'heartbeat') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        fail('POST required');
    }
    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload) || !isset($payload['exam_id']) || !isset($payload['attempt'])) {
        fail('Invalid heartbeat. Expected JSON body with "exam_id" and "attempt".');
    }
    $examId = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)$payload['exam_id']);
    if ($examId === '') {
        fail('Missing exam ID');
    }
    $attemptId = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)$payload['attempt']);
    if ($attemptId === '') {
        fail('Missing attempt ID');
    }
    $attempt = html_attempt_get_by_id($attemptId);
    if (!$attempt || $attempt['exam_id'] !== $examId) {
        fail('Attempt not found');
    }
    if (($attempt['status'] ?? '') === 'submitted') {
        echo json_encode(['ok' => true, 'exam_id' => $examId, 'attempt' => (int)$attempt['attempt_no'], 'status' => 'submitted']);
        exit;
    }
    $idleSeconds = HTML_EXAM_IDLE_SECONDS * 60;
    $lastActivity = strtotime((string)($attempt['last_activity_at'] ?? ''));
    $idleExpired = ($lastActivity !== false && (time() - $lastActivity) > $idleSeconds);
    if ($idleExpired) {
        // Idle auto-submit: grade whatever answers the client last reported.
        $givenAnswers = [];
        if (isset($payload['answers']) && is_array($payload['answers'])) {
            $givenAnswers = $payload['answers'];
        }
        $result = finalize_html_attempt($attempt['id'], get_exam_answers($examId), $givenAnswers);
        $result['attempt'] = (int)$attempt['attempt_no'];
        $result['auto_submitted'] = true;
        echo json_encode($result);
        exit;
    }
    html_attempt_update($attempt['id'], ['last_activity_at' => date('c')]);
    echo json_encode(['ok' => true, 'exam_id' => $examId, 'attempt' => (int)$attempt['attempt_no'], 'status' => 'in_progress']);
    exit;
}

if ($op === 'submit') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        fail('POST required');
    }
    $body = (string)file_get_contents('php://input');
    $payload = json_decode($body, true);
    if (!is_array($payload) || !isset($payload['exam_id']) || !isset($payload['answers']) || !is_array($payload['answers'])) {
        fail('Invalid submission. Expected JSON body with "exam_id" and "answers".');
    }
    $examId = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)$payload['exam_id']);
    $examRow = html_exam_get($examId);
    if (!$examRow) {
        fail('Exam not found');
    }
    $userToken = get_or_create_user_token();
    $attemptId = '';
    if (isset($payload['attempt_id']) && is_string($payload['attempt_id'])) {
        $attemptId = preg_replace('/[^A-Za-z0-9\-_]/', '', $payload['attempt_id']);
    }
    $attempt = $attemptId !== '' ? html_attempt_get_by_id($attemptId) : html_attempt_get_latest($userToken, $examId);
    if (!$attempt || $attempt['exam_id'] !== $examId) {
        fail('Attempt not found');
    }
    if (($attempt['status'] ?? '') === 'submitted') {
        // Already submitted — return the stored result.
        $result = finalize_html_attempt($attempt['id'], get_exam_answers($examId), $payload['answers']);
        $result['attempt'] = (int)$attempt['attempt_no'];
        echo json_encode($result);
        exit;
    }
    $result = finalize_html_attempt($attempt['id'], get_exam_answers($examId), $payload['answers']);
    $result['attempt'] = (int)$attempt['attempt_no'];
    echo json_encode($result);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    fail('POST required');
}

// ---------------- Create: upload or stream an HTML exam ----------------
$html = '';
$sourceName = 'Untitled HTML Exam';

if (isset($_FILES['html'])) {
    $upload = $_FILES['html'];
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        fail('No HTML file uploaded.');
    }
    if ($upload['size'] <= 0 || $upload['size'] > MAX_HTML_BYTES) {
        fail('HTML must be between 1 byte and ' . (int)round(MAX_HTML_BYTES / 1024 / 1024) . ' MB.');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = (string)finfo_file($finfo, $upload['tmp_name']);
    finfo_close($finfo);
    if ($mime !== 'text/html') {
        fail('File is not HTML (detected ' . $mime . ').');
    }
    $html = (string)file_get_contents($upload['tmp_name']);
    $sourceName = basename((string)$upload['name']);
} else {
    $rawBody = (string)file_get_contents('php://input');
    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded) || !isset($decoded['html']) || !is_string($decoded['html'])) {
            fail('JSON body must include an "html" string field.');
        }
        $html = $decoded['html'];
        if (isset($decoded['title']) && is_string($decoded['title'])) {
            $sourceName = $decoded['title'];
        }
    } else {
        $html = $rawBody;
    }
}

$html = trim($html);
if ($html === '') {
    fail('Empty HTML document.');
}
if (strlen($html) > MAX_HTML_BYTES) {
    fail('HTML document exceeds the 10 MB limit.');
}

$title = trim((string)($_POST['title'] ?? ''));
if ($title === '') {
    $title = basename($sourceName, '.html');
}
$title = (string)substr((string)preg_replace('/[^\w\s\-.,()]/u', '', $title), 0, 80);
if ($title === '') {
    $title = 'Untitled HTML Exam';
}

$id = strtolower((string)preg_replace('/[^A-Za-z0-9]/', '', base_convert((string)bin2hex(random_bytes(9)), 16, 36)));
$dir = HTML_EXAMS_DIR . '/' . $id;
if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
    fail('Could not create exam directory.');
}
if (file_put_contents($dir . '/exam.html', $html) === false) {
    fail('Could not save the HTML exam.');
}

$answers = extract_html_answers($html);
$html_exam_create($id, $title, $sourceName, (string)json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

app_log("HTML exam $id created (source: $sourceName, embedded answers: " . count($answers['answers']) . ")");
echo json_encode(['ok' => true, 'id' => $id, 'title' => $title, 'questions' => count($answers['answers'])]);
exit;
