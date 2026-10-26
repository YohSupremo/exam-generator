<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        if (!is_dir(DATA_DIR)) {
            mkdir(DATA_DIR, 0777, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA busy_timeout = 10000;');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS exams (
                id TEXT PRIMARY KEY,
                title TEXT,
                subject TEXT,
                source_name TEXT,
                status TEXT NOT NULL,
                total_questions INTEGER DEFAULT 0,
                num_chapters INTEGER DEFAULT 0,
                total_tokens INTEGER DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        try {
            $pdo->exec('ALTER TABLE exams ADD COLUMN total_tokens INTEGER DEFAULT 0;');
        } catch (Throwable $e) {
            // column already exists
        }
        try {
            $pdo->exec('ALTER TABLE exams ADD COLUMN user_token TEXT DEFAULT "";');
        } catch (Throwable $e) {
            // column already exists
        }
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS html_exams (
                id TEXT PRIMARY KEY,
                title TEXT,
                source_name TEXT,
                status TEXT NOT NULL,
                answers_json TEXT NOT NULL DEFAULT \'{}\',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS html_exam_attempts (
                id TEXT PRIMARY KEY,
                user_token TEXT NOT NULL,
                exam_id TEXT NOT NULL,
                attempt_no INTEGER NOT NULL,
                status TEXT NOT NULL,
                answers_json TEXT NOT NULL DEFAULT \'{}\',
                score REAL,
                correct INTEGER DEFAULT 0,
                total INTEGER DEFAULT 0,
                started_at TEXT NOT NULL,
                last_activity_at TEXT NOT NULL,
                submitted_at TEXT
            )'
        );
    }
    return $pdo;
}

function get_or_create_user_token(): string
{
    $token = $_COOKIE['exam_uid'] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
        $token = bin2hex(random_bytes(16));
        if (!headers_sent()) {
            setcookie('exam_uid', $token, [
                'expires' => time() + 31536000,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE['exam_uid'] = $token;
    }
    return $token;
}

function exam_lifetime_tokens(): int
{
    try {
        $stmt = db()->query('SELECT SUM(total_tokens) as sum_tokens FROM exams');
        $row = $stmt->fetch();
        return (int)($row['sum_tokens'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function exam_create(string $id, string $sourceName, string $userToken = ''): void
{
    $now = date('c');
    $stmt = db()->prepare(
        'INSERT INTO exams (id, title, subject, source_name, status, user_token, created_at, updated_at)
         VALUES (:id, :title, :subject, :source_name, :status, :user_token, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':id' => $id,
        ':title' => rtrim(pathinfo($sourceName, PATHINFO_FILENAME)),
        ':subject' => '',
        ':source_name' => $sourceName,
        ':status' => 'queued',
        ':user_token' => $userToken,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}

function exam_get(string $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM exams WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function exam_list(?string $userToken = null): array
{
    if ($userToken !== null && $userToken !== '') {
        // Auto-claim legacy unassigned exams on localhost
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($remoteAddr === '127.0.0.1' || $remoteAddr === '::1' || $remoteAddr === 'localhost') {
            try {
                db()->prepare('UPDATE exams SET user_token = :token WHERE user_token IS NULL OR user_token = ""')->execute([':token' => $userToken]);
            } catch (Throwable $e) {}
        }
        $stmt = db()->prepare('SELECT * FROM exams WHERE user_token = :token ORDER BY created_at DESC');
        $stmt->execute([':token' => $userToken]);
        return $stmt->fetchAll();
    }
    return db()->query('SELECT * FROM exams ORDER BY created_at DESC')->fetchAll();
}

function exam_update(string $id, array $fields): void
{
    $set = [];
    $params = [];
    foreach ($fields as $col => $value) {
        $set[] = $col . ' = :' . $col;
        $params[':' . $col] = $value;
    }
    $set[] = 'updated_at = :updated_at';
    $params[':updated_at'] = date('c');
    $params[':id'] = $id;
    $sql = 'UPDATE exams SET ' . implode(', ', $set) . ' WHERE id = :id';
    db()->prepare($sql)->execute($params);
}

function html_exam_create(string $id, string $title, string $sourceName, string $answersJson = '{}'): void
{
    $now = date('c');
    $stmt = db()->prepare(
        'INSERT INTO html_exams (id, title, source_name, status, answers_json, created_at, updated_at)
         VALUES (:id, :title, :source_name, :status, :answers_json, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':id' => $id,
        ':title' => $title,
        ':source_name' => $sourceName,
        ':status' => 'ready',
        ':answers_json' => $answersJson,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}

function html_exam_get(string $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM html_exams WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function html_exam_list(): array
{
    return db()->query('SELECT * FROM html_exams ORDER BY created_at DESC')->fetchAll();
}

function html_exam_update(string $id, array $fields): void
{
    $set = [];
    $params = [];
    foreach ($fields as $col => $value) {
        $set[] = $col . ' = :' . $col;
        $params[':' . $col] = $value;
    }
    $set[] = 'updated_at = :updated_at';
    $params[':updated_at'] = date('c');
    $params[':id'] = $id;
    $sql = 'UPDATE html_exams SET ' . implode(', ', $set) . ' WHERE id = :id';
    db()->prepare($sql)->execute($params);
}

function html_attempt_start(string $userToken, string $examId): array
{
    $now = date('c');
    $countRow = db()->prepare('SELECT COUNT(*) as cnt FROM html_exam_attempts WHERE user_token = :token AND exam_id = :exam_id')->fetch([':token' => $userToken, ':exam_id' => $examId]);
    $attemptNo = (int)($countRow['cnt'] ?? 0) + 1;
    $attemptId = strtolower((string)preg_replace('/[^A-Za-z0-9]/', '', base_convert((string)bin2hex(random_bytes(9)), 16, 36)));
    db()->prepare(
        'INSERT INTO html_exam_attempts (id, user_token, exam_id, attempt_no, status, answers_json, started_at, last_activity_at, submitted_at)
         VALUES (:id, :token, :exam_id, :attempt_no, :status, :answers_json, :started_at, :last_activity_at, :submitted_at)'
    )->execute([
        ':id' => $attemptId,
        ':token' => $userToken,
        ':exam_id' => $examId,
        ':attempt_no' => $attemptNo,
        ':status' => 'in_progress',
        ':answers_json' => '{}',
        ':started_at' => $now,
        ':last_activity_at' => $now,
        ':submitted_at' => null,
    ]);
    return [
        'id' => $attemptId,
        'user_token' => $userToken,
        'exam_id' => $examId,
        'attempt_no' => $attemptNo,
        'status' => 'in_progress',
        'answers_json' => '{}',
        'score' => null,
        'correct' => 0,
        'total' => 0,
        'started_at' => $now,
        'last_activity_at' => $now,
        'submitted_at' => null,
    ];
}

function html_attempt_get_latest(string $userToken, string $examId): ?array
{
    $stmt = db()->prepare('SELECT * FROM html_exam_attempts WHERE user_token = :token AND exam_id = :exam_id ORDER BY attempt_no DESC LIMIT 1');
    $stmt->execute([':token' => $userToken, ':exam_id' => $examId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function html_attempt_get_by_id(string $attemptId): ?array
{
    $stmt = db()->prepare('SELECT * FROM html_exam_attempts WHERE id = :id');
    $stmt->execute([':id' => $attemptId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function html_attempt_update(string $id, array $fields): void
{
    $set = [];
    $params = [];
    foreach ($fields as $col => $value) {
        if ($col === 'updated_at') { continue; }
        $set[] = $col . ' = :' . $col;
        $params[':' . $col] = $value;
    }
    if (!$set) { return; }
    $set[] = 'updated_at = :updated_at';
    $params[':updated_at'] = date('c');
    $params[':id'] = $id;
    db()->prepare('UPDATE html_exam_attempts SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
}

function extract_chapter_encyclopedia(array $ch, string $chId): array
{
    static $stopMap = null;
    if ($stopMap === null) {
        $stopTerms = [
            'which', 'what', 'how', 'why', 'when', 'where', 'who', 'none', 'all', 'both', 'neither',
            'true', 'false', 'this', 'there', 'here', 'it', 'each', 'some', 'one', 'step', 'the following',
            'option', 'makes', 'add', 'prompts', 'shows', 'provides', 'used', 'does', 'includes',
            'first step', 'second step', 'third step', 'correct answer', 'incorrect answer'
        ];
        $stopMap = array_fill_keys($stopTerms, true);
    }

    $enc = [];
    $seen = [];

    $add = function (string $rawTerm, string $rawDef) use (&$enc, &$seen, $chId, $stopMap): void {
        $term = trim(preg_replace('/^[\s`\'"“]+|[\s`\'"”.,:;!?]+$/u', '', $rawTerm));
        $term = trim(preg_replace('/^(the|a|an)\s+/i', '', $term));
        if (mb_strlen($term) < 2 || mb_strlen($term) > 50) {
            return;
        }
        $lowerTerm = strtolower($term);
        if (isset($stopMap[$lowerTerm])) {
            return;
        }

        $def = trim(preg_replace('/^[\s`\'"“]+|[\s`\'"”]+$/u', '', $rawDef));
        if (mb_strlen($def) < 15) {
            return;
        }
        if (!preg_match('/[.!?]$/u', $def)) {
            $def .= '.';
        }

        $key = strtolower($chId . '::' . $term);
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $enc[] = [
            'chapterId' => $chId,
            'term' => $term,
            'definition' => $def,
        ];
    };

    $chTitle = trim((string)($ch['title'] ?? ''));
    $chDesc = trim((string)($ch['description'] ?? ''));
    if ($chTitle !== '' && mb_strlen($chDesc) > 20) {
        $add($chTitle, $chDesc);
    }

    $questions = (isset($ch['questions']) && is_array($ch['questions'])) ? $ch['questions'] : [];
    foreach ($questions as $q) {
        if (!is_array($q)) {
            continue;
        }
        $qText = trim((string)($q['question'] ?? ''));
        $expl = trim((string)($q['explanation'] ?? ''));
        $correctIdx = isset($q['correctAnswer']) ? (int)$q['correctAnswer'] : -1;
        $choices = (isset($q['choices']) && is_array($q['choices'])) ? $q['choices'] : [];
        $correctText = ($correctIdx >= 0 && isset($choices[$correctIdx])) ? trim((string)$choices[$correctIdx]) : '';

        // Pattern 1: Question asks for specific command, option, flag, term, concept
        if (preg_match('/^(?:which|what)\s+(?:of\s+the\s+following\s+)?(?:command|option|flag|parameter|utility|tool|process|phase|step|technique|method|metric|term|concept|component|role|protocol|mode|algorithm|directive|function|operator)\b/i', $qText)) {
            if ($correctText !== '' && mb_strlen($correctText) <= 45 && !preg_match('/[.!?]$/u', $correctText)) {
                $cleanTerm = $correctText;
                if (str_starts_with($cleanTerm, '-') && preg_match('/^[a-zA-Z0-9_\-]+$/', $chTitle)) {
                    $cleanTerm = $chTitle . ' ' . $cleanTerm;
                }
                $add($cleanTerm, $expl !== '' ? $expl : ('Used in ' . $chTitle . ' for: ' . $correctText));
                continue;
            }
        }

        // Pattern 2: What is / What are <term>?
        if (preg_match('~^what\s+(?:is|are|was|were)\s+(?:an?|the)?\s*([a-zA-Z0-9\s\-_/\'’]{2,40}?)(?:\s+(?:used for|defined as|meant by|in\s+.*))?\s*\?~iu', $qText, $m)) {
            $cand = trim($m[1]);
            if (!isset($stopMap[strtolower($cand)])) {
                $add($cand, $expl !== '' ? $expl : $correctText);
                continue;
            }
        }

        // Pattern 3: What does the <term> do / display / mean / represent?
        if (preg_match('~^what\s+does\s+(?:the\s+)?([a-zA-Z0-9\-_`\'’\s]{2,35}?)\s+(?:do|display|mean|represent|perform)\s*\?~iu', $qText, $m)) {
            $cand = trim($m[1]);
            if (!isset($stopMap[strtolower($cand)])) {
                $add($cand, $expl !== '' ? $expl : $correctText);
                continue;
            }
        }

        // Pattern 4: Explanation defines a term: "Term is / refers to / represents ..."
        if (preg_match('/^([A-Z0-9][a-zA-Z0-9\s\-_\/]{1,35}?)\s+(?:is|are|refers to|denotes|represents|defines|is defined as|is performed)\s+([^.]+\.)/i', $expl, $m)) {
            $cand = trim($m[1]);
            if (!isset($stopMap[strtolower($cand)]) && !preg_match('/^(in|on|at|by|with|during|before|after|if|when|while|for|under|to)\b/i', $cand)) {
                $add($cand, $m[0]);
                continue;
            }
        }
    }

    return $enc;
}

function ensure_exam_encyclopedia(array &$bank): bool
{
    $existing = (isset($bank['encyclopedia']) && is_array($bank['encyclopedia'])) ? $bank['encyclopedia'] : [];
    if (!empty($existing)) {
        return false;
    }

    $allEnc = [];
    $chapters = (isset($bank['chapters']) && is_array($bank['chapters'])) ? $bank['chapters'] : [];
    foreach ($chapters as $ch) {
        $chId = (string)($ch['chapterId'] ?? '');
        if ($chId === '') {
            continue;
        }
        $terms = extract_chapter_encyclopedia($ch, $chId);
        foreach ($terms as $t) {
            $allEnc[] = $t;
        }
    }

    $bank['encyclopedia'] = $allEnc;
    return true;
}