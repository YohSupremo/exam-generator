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
    }
    return $pdo;
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

function exam_create(string $id, string $sourceName): void
{
    $now = date('c');
    $stmt = db()->prepare(
        'INSERT INTO exams (id, title, subject, source_name, status, created_at, updated_at)
         VALUES (:id, :title, :subject, :source_name, :status, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':id' => $id,
        ':title' => rtrim(pathinfo($sourceName, PATHINFO_FILENAME)),
        ':subject' => '',
        ':source_name' => $sourceName,
        ':status' => 'queued',
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

function exam_list(): array
{
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