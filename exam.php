<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/db.php';

$id = preg_replace('/[^A-Za-z0-9\-_]/', '', $_GET['id'] ?? '');
$row = $id !== '' ? exam_get($id) : null;

if (!$row) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Not found</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;"><h1>Exam not found</h1><p><a href="index.php">Back to home</a></p></body></html>';
    exit;
}
if ($row['status'] !== 'done') {
    http_response_code(409);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Not ready</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;"><h1>Not ready yet</h1><p>This exam is still generating. <a href="index.php?job=' . htmlspecialchars($id) . '">Check progress</a>.</p></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#0b1120">
<title>Exam &middot; <?= htmlspecialchars($row['title']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/site.css">
<link rel="stylesheet" href="assets/exam-app.css">
</head>
<body data-exam-id="<?= htmlspecialchars($id) ?>">
<header class="exam-header">
    <div class="container">
        <div class="exam-header-row">
            <div>
                <a class="back-link" href="index.php">&larr; Back to exams</a>
                <h1 id="exam-title"><?= htmlspecialchars($row['title']) ?></h1>
                <div class="exam-meta" id="exam-meta"></div>
            </div>
            <div class="exam-header-actions">
                <div class="exam-header-stats" id="exam-header-stats"></div>
                <a class="btn btn-sm download-btn" href="export.php?id=<?= htmlspecialchars($id) ?>" download="<?= htmlspecialchars(preg_replace('/[^A-Za-z0-9_\-]/', '_', $row['title'])) ?>.html" title="Download standalone offline HTML file">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    <span>Download HTML</span>
                </a>
            </div>
        </div>
    </div>
</header>

<nav class="exam-nav" id="exam-nav" aria-label="Exam sections"></nav>

<main class="container" id="view" aria-live="polite">
    <div class="loading">Loading question bank&hellip;</div>
</main>

<footer class="exam-footer">
    <div class="container">Generated with the <em>interactive-comprehensive-exam-generator</em> skill via opencode.</div>
</footer>

<script src="assets/exam-app.js"></script>
</body>
</html>