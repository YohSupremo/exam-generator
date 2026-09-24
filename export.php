<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/db.php';

$id = preg_replace('/[^A-Za-z0-9\-_]/', '', $_GET['id'] ?? '');
$row = $id !== '' ? exam_get($id) : null;

if (!$row || $row['status'] !== 'done') {
    http_response_code(404);
    echo "Exam not found or not ready.";
    exit;
}

$dataFile = exam_dir($id) . '/questions.json';
if (!is_file($dataFile)) {
    http_response_code(500);
    echo "Question bank file missing.";
    exit;
}

$bankJson = (string)file_get_contents($dataFile);
$bankData = json_decode($bankJson, true);
$title = $bankData['title'] ?? $row['title'] ?? 'Exam';
$safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $title) ?: 'exam';

$siteCss = (string)file_get_contents(__DIR__ . '/assets/site.css');
$examAppCss = (string)file_get_contents(__DIR__ . '/assets/exam-app.css');
$examAppJs = (string)file_get_contents(__DIR__ . '/assets/exam-app.js');

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $safeName . '.html"');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#0b1120">
<title><?= htmlspecialchars($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
<?= $siteCss ?>

<?= $examAppCss ?>

/* Offline additions */
.back-link, .download-btn { display: none !important; }
.offline-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: var(--muted);
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 4px;
    margin-bottom: 8px;
    width: fit-content;
}
</style>
</head>
<body data-exam-id="<?= htmlspecialchars($id) ?>">
<header class="exam-header">
    <div class="container">
        <div class="exam-header-row">
            <div>
                <span class="offline-badge">Offline Edition</span>
                <h1 id="exam-title"><?= htmlspecialchars($title) ?></h1>
                <div class="exam-meta" id="exam-meta"></div>
            </div>
            <div class="exam-header-actions">
                <div class="exam-header-stats" id="exam-header-stats"></div>
                <button type="button" class="btn btn-sm print-btn" onclick="window.print()" title="Print exam or save as PDF">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    <span>Print Exam</span>
                </button>
            </div>
        </div>
    </div>
</header>

<nav class="exam-nav" id="exam-nav" aria-label="Exam sections"></nav>

<main class="container" id="view" aria-live="polite">
    <div class="loading">Loading question bank&hellip;</div>
</main>

<footer class="exam-footer">
    <div class="container">Interactive Comprehensive Exam &middot; Standalone Offline Edition</div>
</footer>

<script>
window.PRELOADED_BANK = <?= $bankJson ?>;
<?= $examAppJs ?>
</script>
</body>
</html>
