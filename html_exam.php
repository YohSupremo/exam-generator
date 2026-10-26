<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/db.php';

$userToken = get_or_create_user_token();
$examId = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)($_GET['id'] ?? ''));
$exam = $examId !== '' ? html_exam_get($examId) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#09090b">
    <meta name="description" content="SynthExam — upload a self-contained HTML exam and deliver it for interactive answering.">
    <title>SynthExam — HTML Exam Delivery</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/site.css?v=<?= filemtime(__DIR__ . '/assets/site.css') ?>">
    <style>
        .hx-page { max-width: 1080px; margin: 0 auto; padding: 32px 0 70px; }
        .hx-panel { padding: 26px; }
        .hx-frame-wrap { height: calc(100vh - 250px); min-height: 440px; }
        .hx-frame { width: 100%; height: 100%; border: 0; border-radius: var(--radius-md); background: #ffffff; display: block; }
        .hx-take-top { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .hx-take-top .hx-title-block { flex: 1; min-width: 220px; }
        .hx-attempt-badge { display: inline-flex; align-items: center; gap: 7px; padding: 6px 14px; border-radius: var(--radius-full); background: var(--surface-card); border: 1px solid var(--glass-border); font-size: 12.5px; font-weight: 600; color: var(--text-secondary); }
        .hx-status-badge { display: inline-flex; align-items: center; gap: 7px; padding: 6px 14px; border-radius: var(--radius-full); font-size: 12.5px; font-weight: 600; border: 1px solid var(--glass-border); color: var(--text-secondary); }
        .hx-status-badge::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--accent-amber); }
        .hx-status-badge.is-submitted { color: var(--accent-emerald); }
        .hx-status-badge.is-submitted::before { background: var(--accent-emerald); }
        .hx-status-badge.is-idle { color: var(--accent-rose); }
        .hx-status-badge.is-idle::before { background: var(--accent-rose); }
        .hx-top-actions { display: flex; align-items: center; gap: 10px; }
        .hx-upload-title { margin-bottom: 6px; }
        .hx-divider { display: flex; align-items: center; gap: 12px; margin: 22px 0; color: var(--text-muted); font-size: 12.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; }
        .hx-divider::before, .hx-divider::after { content: ""; height: 1px; flex: 1; background: var(--glass-border-subtle); }
        .hx-dropzone { border: 1px dashed var(--glass-border-hover); border-radius: var(--radius-md); padding: 18px; text-align: center; color: var(--text-muted); font-size: 13.5px; background: var(--surface-sub); }
        .hx-paste-area { width: 100%; min-height: 150px; border-radius: var(--radius-md); border: 1px solid var(--glass-border); background: var(--surface-sub); color: var(--text-primary); padding: 12px; font-family: monospace; font-size: 12px; }
        .hx-msg { margin-top: 14px; font-size: 13px; color: var(--accent-rose); min-height: 18px; }
        .hx-result { margin-top: 22px; text-align: center; padding: 30px 26px; }
        .hx-result.hidden { display: none; }
        .hx-score-big { font-size: 54px; font-weight: 800; letter-spacing: -0.02em; background: linear-gradient(135deg, #ffffff, #a1a1aa); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .hx-result-meta { margin-top: 8px; color: var(--text-secondary); font-size: 14.5px; }
        .hx-result-actions { margin-top: 20px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .hx-embed-note { margin-top: 14px; font-size: 12.5px; color: var(--text-muted); }
        @media (max-width: 760px) { .hx-frame-wrap { height: calc(100vh - 330px); min-height: 360px; } }
    </style>
</head>
<body>
    <main class="container hx-page">
        <!-- ===================== TAKE VIEW ===================== -->
        <section class="card hx-panel" id="hx-take-view"<?= $exam ? '' : ' style="display:none;"' ?>>
            <div class="hx-take-top">
                <div class="hx-title-block">
                    <div style="font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted);">HTML Exam Delivery</div>
                    <h1 class="card-main-title" id="hx-exam-title"><?= htmlspecialchars((string)($exam['title'] ?? 'HTML Exam')) ?></h1>
                    <div class="muted" id="hx-exam-sub">Source: <?= htmlspecialchars((string)($exam['source_name'] ?? 'inline HTML')) ?></div>
                </div>
                <div class="hx-top-actions">
                    <span class="hx-attempt-badge" id="hx-attempt-badge">Attempt #—</span>
                    <span class="hx-status-badge" id="hx-status-badge">Taking</span>
                    <button type="button" class="btn btn-danger-soft btn-sm" id="hx-delete-btn">
                        <span class="btn-text">Delete Exam</span>
                    </button>
                </div>
            </div>

            <div class="hx-frame-wrap">
                <iframe id="hx-frame" class="hx-frame" title="HTML Exam"></iframe>
            </div>

            <div class="hx-result hidden" id="hx-result">
                <div class="hx-score-big" id="hx-result-score">--</div>
                <div class="hx-result-meta" id="hx-result-meta">--</div>
                <div id="hx-result-auto" class="muted" style="margin-top:8px; display:none;">Auto-submitted after idle timeout.</div>
                <div class="hx-result-actions">
                    <button type="button" class="btn btn-primary" id="hx-retake-btn">
                        <span class="btn-text">Retake Exam</span>
                    </button>
                    <a class="btn btn-secondary" href="index.php#exams">
                        <span class="btn-text">Back to Exam Library</span>
                    </a>
                </div>
            </div>

            <div class="hx-embed-note">Self-contained exam &bull; served for interactive answering &bull; idle auto-submit enabled.</div>
        </section>

        <!-- ===================== UPLOAD / CREATE VIEW ===================== -->
        <section class="card hx-panel" id="hx-upload-view">
            <h2 class="card-main-title hx-upload-title">HTML Exam Delivery</h2>
            <p class="muted card-desc">Upload a self-contained HTML file that embeds its questions and answers, or paste HTML directly. The exam is stored and can be answered by anyone with the exam ID.</p>

            <form id="hx-upload-form" enctype="multipart/form-data">
                <div class="hx-dropzone">
                    <span class="material-symbols-rounded" style="font-size:28px;">upload_file</span>
                    <div><strong>Drop or choose an HTML exam file</strong></div>
                    <div>Questions + answers embedded &bull; up to 10 MB</div>
                    <input type="file" id="hx-file" name="html" accept="text/html,.html,.htm" style="margin-top:12px;">
                </div>
                <div class="form-row" style="margin-top:14px;">
                    <div class="form-label-row">
                        <label for="hx-title" class="field-label">Exam Title</label>
                        <span class="label-badge-optional">Optional</span>
                    </div>
                    <div class="input-wrap">
                        <input type="text" id="hx-title" placeholder="e.g. Chapter 5: CAP Theorem">
                    </div>
                </div>
                <div class="form-actions-row" style="margin-top:16px;">
                    <button type="submit" class="btn btn-primary btn-lg" id="hx-submit-btn">
                        <span class="btn-text">Upload &amp; Start Exam</span>
                    </button>
                </div>
            </form>

            <div class="hx-divider">or stream / paste HTML</div>

            <form id="hx-paste-form">
                <textarea id="hx-paste" class="hx-paste-area" placeholder="<style>/* your exam styles */</style> ... questions with embedded answers ..." spellcheck="false"></textarea>
                <div class="form-row" style="margin-top:14px;">
                    <div class="form-label-row">
                        <label for="hx-paste-title" class="field-label">Exam Title</label>
                        <span class="label-badge-optional">Optional</span>
                    </div>
                    <div class="input-wrap">
                        <input type="text" id="hx-paste-title" placeholder="e.g. Chapter 5: CAP Theorem">
                    </div>
                </div>
                <div class="form-actions-row" style="margin-top:16px;">
                    <button type="submit" class="btn btn-secondary btn-lg" id="hx-paste-submit-btn">
                        <span class="btn-text">Start Exam from Paste</span>
                    </button>
                </div>
            </form>

            <div class="hx-msg" id="hx-msg"></div>
        </section>
    </main>

    <script id="hx-runtime-script">
        (function () {
            var ctx = window.__EXAM_CTX__ || {};
            var EXAM_ID = ctx.exam_id || '';
            var ATTEMPT_NO = ctx.attempt || 0;
            var IDLE_TIMEOUT_MS = 300000;
            var HEARTBEAT_MS = 15000;

            var answers = [];
            var submitted = false;
            var lastActivity = Date.now();

            function normAnswers(raw) {
                var list = [];
                if (Array.isArray(raw)) {
                    for (var i = 0; i < raw.length; i++) { var v = raw[i]; list.push(typeof v === 'number' ? v : (parseInt(v, 10) || 0)); }
                    return list;
                }
                if (raw && typeof raw === 'object') {
                    var keys = Object.keys(raw);
                    for (var j = 0; j < keys.length; j++) { var kv = raw[keys[j]]; list.push(typeof kv === 'number' ? kv : (parseInt(kv, 10) || 0)); }
                    return list;
                }
                return list;
            }

            function markActivity() { lastActivity = Date.now(); }

            function stopHeartbeat() { if (heartbeatTimer) { clearInterval(heartbeatTimer); heartbeatTimer = null; } }

            function postResult(payload) {
                if (submitted) { return; }
                submitted = true;
                stopHeartbeat();
                try { window.parent.postMessage({ source: 'synthexam', type: 'exam:result', payload: payload || {} }, '*'); } catch (e) {}
            }

            function doSubmit() {
                if (submitted || !EXAM_ID || !ATTEMPT_NO) { return Promise.resolve(); }
                return fetch('api/html_submit.php?op=submit', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ exam_id: EXAM_ID, attempt: ATTEMPT_NO, answers: answers })
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data && data.ok) { postResult(data); }
                    return data;
                }).catch(function () { return null; });
            }

            var heartbeatTimer = setInterval(function () {
                if (submitted) { return; }
                fetch('api/html_submit.php?op=heartbeat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ exam_id: EXAM_ID, attempt: ATTEMPT_NO, answers: answers })
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data && data.auto_submitted) { postResult(data); }
                }).catch(function () { });
            }, HEARTBEAT_MS);

            var origFetch = window.fetch;
            window.fetch = function (input, init) {
                var url = typeof input === 'string' ? input : ((input && input.url) || '');
                var method = String((init && init.method) || (input && input.method) || 'GET');
                var body = init ? init.body : null;
                return Promise.resolve().then(function () {
                    if (/html_submit\.php/.test(url) && method.toUpperCase() === 'POST' && typeof body === 'string') {
                        try {
                            var json = JSON.parse(body);
                            if (json && json.op === 'submit') { answers = normAnswers(json.answers); markActivity(); }
                        } catch (e) {}
                    }
                    return origFetch.call(window, input, init);
                });
            };

            function idleCheck() {
                if (submitted) { return; }
                if (Date.now() - lastActivity > IDLE_TIMEOUT_MS) { doSubmit(); }
            }
            setInterval(idleCheck, 5000);

            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') { markActivity(); }
            });
            window.addEventListener('focus', markActivity);

            window.addEventListener('message', function (e) {
                var d = e.data;
                if (d && d.source === 'synthexam' && d.type === 'exam:result') { postResult(d.payload); }
            });
        })();
    </script>

    <script>
        (function () {
            'use strict';
            var EXAM_ID = <?= json_encode($examId) ?>;
            var EXAM_TITLE = <?= json_encode((string)($exam['title'] ?? '')) ?>;
            var takeView = document.getElementById('hx-take-view');
            var uploadView = document.getElementById('hx-upload-view');
            var frame = document.getElementById('hx-frame');
            var attemptBadge = document.getElementById('hx-attempt-badge');
            var statusBadge = document.getElementById('hx-status-badge');
            var examTitleEl = document.getElementById('hx-exam-title');
            var examSubEl = document.getElementById('hx-exam-sub');
            var resultEl = document.getElementById('hx-result');
            var resultScoreEl = document.getElementById('hx-result-score');
            var resultMetaEl = document.getElementById('hx-result-meta');
            var resultAutoEl = document.getElementById('hx-result-auto');
            var retakeBtn = document.getElementById('hx-retake-btn');
            var deleteBtn = document.getElementById('hx-delete-btn');
            var msgEl = document.getElementById('hx-msg');
            var uploadForm = document.getElementById('hx-upload-form');
            var pasteForm = document.getElementById('hx-paste-form');
            var fileInput = document.getElementById('hx-file');
            var titleInput = document.getElementById('hx-title');
            var pasteInput = document.getElementById('hx-paste');
            var pasteTitleInput = document.getElementById('hx-paste-title');
            var submitBtn = document.getElementById('hx-submit-btn');
            var pasteSubmitBtn = document.getElementById('hx-paste-submit-btn');
            var RUNTIME = document.getElementById('hx-runtime-script').textContent;
            var ATTEMPT_NO = 0;

            function showMsg(m) { if (msgEl) { msgEl.textContent = m || ''; } }

            function startAttempt() {
                return fetch('api/html_submit.php?op=start', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ exam_id: EXAM_ID })
                }).then(function (r) { return r.json(); }).then(function (d) {
                    if (d && d.ok) {
                        ATTEMPT_NO = d.attempt_no;
                        attemptBadge.textContent = 'Attempt #' + d.attempt_no;
                        statusBadge.textContent = d.status;
                        statusBadge.classList.remove('is-submitted', 'is-idle');
                        return d;
                    }
                    throw new Error((d && d.error) || 'Failed to start attempt');
                });
            }

            function renderExam(attemptNo) {
                return fetch('api/html_get.php?id=' + encodeURIComponent(EXAM_ID) + '&raw=1')
                    .then(function (r) { return r.text(); })
                    .then(function (html) {
                        var inject = '<scr' + 'ipt>window.__EXAM_CTX__={exam_id:' + JSON.stringify(EXAM_ID) + ', attempt:' + attemptNo + '};</scr' + 'ipt>\n' + RUNTIME + '\n</scr' + 'ipt>';
                        var doc = html.replace(/<\/body\s*>/i, inject + '\n</body>');
                        if (!/<\/body\s*>/i.test(html)) { doc = doc + '\n</body>'; }
                        frame.srcdoc = doc;
                    });
            }

            function setStatus(s) {
                statusBadge.textContent = s;
                statusBadge.classList.remove('is-submitted', 'is-idle');
                if (s === 'submitted') { statusBadge.classList.add('is-submitted'); }
            }

            function loadTakeView() {
                examTitleEl.textContent = EXAM_TITLE || 'HTML Exam';
                return startAttempt().then(function (d) {
                    attemptBadge.textContent = 'Attempt #' + d.attempt_no;
                    setStatus(d.status);
                    return renderExam(d.attempt_no);
                }).catch(function (e) {
                    showMsg('Failed to load HTML exam: ' + (e && e.message ? e.message : 'unknown error'));
                });
            }

            function showResult(p) {
                if (!p) { return; }
                resultEl.classList.remove('hidden');
                var pct = Math.round((p.score || 0) * 100);
                resultScoreEl.textContent = pct + '%';
                resultMetaEl.textContent = (p.correct || 0) + ' of ' + (p.total || 0) + ' correct';
                resultAutoEl.style.display = (p.auto_submitted ? 'block' : 'none');
                setStatus('submitted');
            }

            function hideResult() { resultEl.classList.add('hidden'); }

            function retake() {
                hideResult();
                setStatus('Taking');
                return startAttempt().then(function (d) {
                    attemptBadge.textContent = 'Attempt #' + d.attempt_no;
                    setStatus(d.status);
                    return renderExam(d.attempt_no);
                });
            }

            function handleUploadSubmit(e) {
                e.preventDefault();
                var file = fileInput.files ? fileInput.files[0] : null;
                var title = (titleInput.value || '').trim();
                if (!file && !(pasteInput.value || '').trim()) { showMsg('Please choose a file or paste HTML.'); return; }
                if (file) {
                    var fd = new FormData();
                    fd.append('html', file);
                    if (title) { fd.append('title', title); }
                    submitBtn.disabled = true;
                    fetch('api/html_submit.php', { method: 'POST', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            submitBtn.disabled = false;
                            if (d && d.ok) { window.location.href = 'html_exam.php?id=' + d.id; }
                            else { showMsg('Upload failed: ' + ((d && d.error) || 'unknown error')); }
                        });
                } else {
                    var html = (pasteInput.value || '').trim();
                    if (!html) { showMsg('Please paste some HTML.'); return; }
                    var pTitle = (pasteTitleInput.value || '').trim();
                    fetch('api/html_submit.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ html: html, title: pTitle || undefined })
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (d && d.ok) { window.location.href = 'html_exam.php?id=' + d.id; }
                            else { showMsg('Upload failed: ' + ((d && d.error) || 'unknown error')); }
                        });
                }
            }

            function init() {
                window.addEventListener('message', function (e) {
                    var d = e.data;
                    if (d && d.source === 'synthexam' && d.type === 'exam:result') { showResult(d.payload); }
                });

                if (retakeBtn) { retakeBtn.addEventListener('click', function (e) { e.preventDefault(); retake(); }); }

                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function () {
                        if (!confirm('Delete this HTML exam? This cannot be undone.')) { return; }
                        fetch('api/html_submit.php?op=delete&id=' + encodeURIComponent(EXAM_ID), { method: 'POST' })
                            .then(function (r) { return r.json(); })
                            .then(function (d) {
                                if (d && d.ok) { window.location.href = 'index.php'; }
                                else { showMsg('Delete failed: ' + ((d && d.error) || 'unknown error')); }
                            });
                    });
                }

                if (uploadForm) { uploadForm.addEventListener('submit', handleUploadSubmit); }
                if (pasteForm) { pasteForm.addEventListener('submit', handleUploadSubmit); }

                if (takeView && uploadView) {
                    if (EXAM_ID) { loadTakeView(); }
                    else { uploadView.style.display = 'block'; }
                }
            }

            init();
        })();
    </script>
</body>
</html>
