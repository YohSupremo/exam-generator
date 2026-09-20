<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/db.php';

$exams = exam_list();
$opencodeOk = is_file(OPENCODE_EXE);
$phpOk = is_file(PHP_EXE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#0b1120">
<title>Auto Exam Maker</title>
<link rel="stylesheet" href="assets/site.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <div class="brand">
            <span class="brand-mark">A&times;M</span>
            <div>
                <h1>Auto Exam Maker</h1>
                <p>Upload a PDF reference &middot; get an interactive comprehensive exam</p>
            </div>
        </div>
        <div class="backend-status" id="backend-status"></div>
    </div>
</header>

<main class="container">
    <section class="card upload-card">
        <h2>Create a new exam</h2>
        <p class="muted">Drop a PDF (lecture notes, slides, reviewer, textbook). The backend reads it and generates
        ~30 challenging questions per chapter with timed/untimed modes, an overall exam, answer key, and encyclopedia.</p>
        <div class="feature-strip">
            <div class="feature">
                <span class="fi">&#129504;</span>
                <div><h4>~30 questions / chapter</h4><p>Scenario, application &amp; analysis questions, not just recall.</p></div>
            </div>
            <div class="feature">
                <span class="fi">&#9201;</span>
                <div><h4>Timed &amp; Untimed</h4><p>10-second timed challenges or relaxed untimed practice per chapter.</p></div>
            </div>
            <div class="feature">
                <span class="fi">&#127891;</span>
                <div><h4>Key + Encyclopedia</h4><p>Instant feedback, a full answer key, and an explainer glossary.</p></div>
            </div>
        </div>
        <form id="upload-form">
            <label class="dropzone" id="dropzone">
                <input type="file" id="pdf-file" name="pdf" accept="application/pdf,.pdf" required>
                <span class="dz-icon">&#128196;</span>
                <span class="dz-text">Click or drag a PDF here</span>
                <span class="dz-hint" id="dz-hint"></span>
            </label>
            <div class="form-row">
                <label for="title-input">Exam title (optional)</label>
                <input type="text" id="title-input" name="title" placeholder="Defaults to the PDF filename" maxlength="120">
            </div>
            <button type="submit" class="btn btn-primary" id="submit-btn" disabled>Generate Exam</button>
            <div id="status-area" class="status-area" hidden>
                <div class="spinner"></div>
                <div id="status-text">Queued&hellip;</div>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Your exams</h2>
        <?php if (empty($exams)): ?>
            <p class="muted">No exams yet. Upload a PDF above to create your first one.</p>
        <?php else: ?>
        <table class="exam-table">
            <thead>
                <tr><th>Title</th><th>Status</th><th>Chapters</th><th>Questions</th><th>Created</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($exams as $ex): ?>
                <tr>
                    <td class="tt"><?= htmlspecialchars($ex['title']) ?>
                        <?php if ($ex['source_name']): ?><div class="sub"><?= htmlspecialchars($ex['source_name']) ?></div><?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= htmlspecialchars($ex['status']) ?>" data-status="<?= htmlspecialchars($ex['status']) ?>"><?= htmlspecialchars($ex['status']) ?></span></td>
                    <td><?= (int)$ex['num_chapters'] ?: '&mdash;' ?></td>
                    <td><?= (int)$ex['total_questions'] ?: '&mdash;' ?></td>
                    <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($ex['created_at']))) ?></td>
                    <td>
                        <?php if ($ex['status'] === 'done'): ?>
                            <div class="row-actions">
                                <a class="btn btn-sm" href="exam.php?id=<?= urlencode($ex['id']) ?>">Open</a>
                                <a class="btn btn-sm download-btn-sm" href="export.php?id=<?= urlencode($ex['id']) ?>" download="<?= htmlspecialchars(preg_replace('/[^A-Za-z0-9_\-]/', '_', $ex['title'])) ?>.html" title="Download standalone HTML">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    <span>Download HTML</span>
                                </a>
                            </div>
                        <?php elseif (in_array($ex['status'], ['queued', 'running'], true)): ?>
                            <a class="btn btn-sm" href="?job=<?= urlencode($ex['id']) ?>" id="job-link-<?= htmlspecialchars($ex['id']) ?>">Track</a>
                        <?php else: ?>
                            <button class="btn btn-sm" onclick="deleteExam('<?= htmlspecialchars($ex['id']) ?>')">Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Backend</h2>
        <ul class="check-list">
            <li class="<?= $phpOk ? 'ok' : 'bad' ?>">PHP CLI: <?= $phpOk ? 'found (' . htmlspecialchars(PHP_EXE) . ')' : 'NOT FOUND' ?></li>
            <li class="<?= $opencodeOk ? 'ok' : 'bad' ?>">opencode CLI: <?= $opencodeOk ? 'found (' . htmlspecialchars(OPENCODE_EXE) . ')' : 'NOT FOUND' ?></li>
            <li class="ok">Skill: <?= htmlspecialchars(SKILL_NAME) ?> <?= is_file(SKILL_DIR . '/SKILL.md') ? 'installed' : 'MISSING' ?></li>
        </ul>
    </section>
</main>

<footer class="site-footer">
    <div class="container">Auto Exam Maker &middot; runs entirely on your machine via opencode</div>
</footer>

<script>
const AUTO_POLL_JOB = <?= json_encode($_GET['job'] ?? null) ?>;
</script>
<script src="assets/site.js"></script>
</body>
</html>