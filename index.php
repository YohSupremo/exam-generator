<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/db.php';

$exams = exam_list();
$opencodeOk = is_file(OPENCODE_EXE);
$phpOk = is_file(PHP_EXE);

$activeJobId = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)($_GET['job'] ?? ''));
if ($activeJobId !== '') {
    $targetExam = exam_get($activeJobId);
    if (!$targetExam || !in_array($targetExam['status'], ['queued', 'running'], true)) {
        $activeJobId = null; // The exam in ?job= is already completed or cancelled
    }
}
if (!$activeJobId) {
    foreach ($exams as $ex) {
        if (in_array($ex['status'], ['queued', 'running'], true)) {
            $activeJobId = $ex['id'];
            break;
        }
    }
}

$initialQuota = [];
if (is_file(DATA_DIR . '/ai_quota.json')) {
    $initialQuota = json_decode((string)@file_get_contents(DATA_DIR . '/ai_quota.json'), true) ?: [];
}

$now = time();
$lastUp = !empty($initialQuota['updated_at']) ? strtotime($initialQuota['updated_at']) : 0;
$elapsed = $lastUp > 0 ? max(0, $now - $lastUp) : 999;

$remT = (int)($initialQuota['ratelimit']['remaining_tokens'] ?? 8000);
$limT = (int)($initialQuota['ratelimit']['limit_tokens'] ?? 8000);
$remR = (int)($initialQuota['ratelimit']['remaining_requests'] ?? 1000);
$limR = (int)($initialQuota['ratelimit']['limit_requests'] ?? 1000);

function parse_groq_duration(string $raw): float {
    $sec = 0.0;
    if (preg_match('/(\d+(?:\.\d+)?)h/', $raw, $h)) { $sec += (float)$h[1] * 3600; }
    if (preg_match('/(\d+(?:\.\d+)?)m(?!s)/', $raw, $m)) { $sec += (float)$m[1] * 60; }
    if (preg_match('/(\d+(?:\.\d+)?)s/', $raw, $s)) { $sec += (float)$s[1]; }
    if (preg_match('/(\d+(?:\.\d+)?)ms/', $raw, $ms)) { $sec += (float)$ms[1] / 1000; }
    return $sec;
}

function format_groq_duration(float $sec): string {
    if ($sec <= 0) return '0s';
    if ($sec < 1) return round($sec * 1000) . 'ms';
    if ($sec < 60) return round($sec, 1) . 's';
    $m = (int)floor($sec / 60);
    $s = (int)round($sec % 60);
    if ($m < 60) return $m . 'm ' . ($s < 10 ? '0' . $s : $s) . 's';
    $h = (int)floor($m / 60);
    $m = $m % 60;
    return $h . 'h ' . $m . 'm ' . ($s < 10 ? '0' . $s : $s) . 's';
}

$rawResetTok = (string)($initialQuota['ratelimit']['reset_tokens'] ?? '0s');
$rawResetReq = (string)($initialQuota['ratelimit']['reset_requests'] ?? '0s');

$resetTokSec = parse_groq_duration($rawResetTok);
$resetReqSec = parse_groq_duration($rawResetReq);

if ($resetTokSec > 0 && $elapsed > 0) {
    if ($elapsed >= $resetTokSec) {
        $remT = $limT;
        $initResetTokens = '0s';
    } else {
        $fraction = min(1.0, $elapsed / $resetTokSec);
        $remT = min($limT, (int)round($remT + ($limT - $remT) * $fraction));
        $initResetTokens = format_groq_duration(max(0, $resetTokSec - $elapsed));
    }
} else {
    $initResetTokens = format_groq_duration($resetTokSec);
}

if ($resetReqSec > 0 && $elapsed > 0) {
    if ($elapsed >= $resetReqSec) {
        $remR = $limR;
        $initResetRequests = '0s';
    } else {
        $initResetRequests = format_groq_duration(max(0, $resetReqSec - $elapsed));
    }
} else {
    $initResetRequests = format_groq_duration($resetReqSec);
}

$initRemTokens = number_format($remT);
$initLimTokens = number_format($limT);
$initRemRequests = number_format($remR);
$initLimRequests = number_format($limR);
$initLifetimeTokens = number_format(exam_lifetime_tokens());
$initTpmPct = $limT > 0 ? max(0, min(100, (int)round(($remT / $limT) * 100))) : 100;
$initRpmPct = $limR > 0 ? max(0, min(100, (int)round(($remR / $limR) * 100))) : 100;

$initModel = htmlspecialchars(!empty($initialQuota['model']) ? $initialQuota['model'] : (defined('OPENCODE_MODEL') ? OPENCODE_MODEL : 'openai/gpt-oss-20b'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#07101f">
<meta name="description" content="Auto Exam Maker — upload a PDF and instantly generate a comprehensive interactive exam powered by AI.">
<title>Auto Exam Maker — AI-Powered Exam Generator</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..900;1,14..32,300..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
<link rel="stylesheet" href="assets/site.css?v=<?= filemtime(__DIR__ . '/assets/site.css') ?>">
<style>
/* Critical Header Token Badge styling to prevent unstyled flash */
.header-token-badge {
    display: inline-flex !important;
    align-items: center !important;
    gap: 7px !important;
    height: 34px !important;
    padding: 0 12px 0 8px !important;
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.75) 100%) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    border-radius: 999px !important;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.08) !important;
    cursor: default;
    user-select: none;
    white-space: nowrap !important;
    line-height: 1 !important;
    font-size: 13px !important;
    font-variant-numeric: tabular-nums !important;
    vertical-align: middle;
}
.htb-icon-wrap {
    width: 22px !important;
    height: 22px !important;
    border-radius: 50% !important;
    background: rgba(245, 158, 11, 0.15) !important;
    border: 1px solid rgba(245, 158, 11, 0.35) !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #fbbf24 !important;
    flex: none !important;
}
.htb-remaining { font-weight: 700 !important; color: #ffffff !important; display: inline-block; transition: color 0.3s ease; }
.htb-remaining.htb-pulse { animation: htb-num-glow 0.6s ease-out; }
@keyframes htb-num-glow {
    0% { color: #fbbf24; transform: scale(1.08); text-shadow: 0 0 8px rgba(251, 191, 36, 0.8); }
    100% { color: #ffffff; transform: scale(1); text-shadow: none; }
}
.htb-sep { color: rgba(255, 255, 255, 0.35) !important; font-weight: 400 !important; margin: 0 1px !important; }
.htb-limit { font-weight: 500 !important; color: #94a3b8 !important; }
.htb-unit { font-size: 11px !important; font-weight: 600 !important; text-transform: uppercase !important; letter-spacing: 0.04em !important; color: #64748b !important; margin-left: 2px !important; }
.htb-dot { width: 7px !important; height: 7px !important; border-radius: 50% !important; flex: none !important; margin-left: 3px !important; }
.htb-dot.online { background: #34d399 !important; box-shadow: 0 0 0 2px rgba(52, 211, 153, 0.2) !important; }
.htb-dot.offline { background: #f87171 !important; }
.quota-progress-track { width: 100%; height: 6px; background: rgba(255, 255, 255, 0.08); border-radius: 999px; overflow: hidden; margin: 4px 0 2px 0; }
.quota-progress-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #10b981 0%, #34d399 100%); box-shadow: 0 0 8px rgba(52, 211, 153, 0.35); transition: width 0.4s ease; }
.quota-progress-fill.quota-green { background: linear-gradient(90deg, #10b981 0%, #34d399 100%) !important; box-shadow: 0 0 8px rgba(52, 211, 153, 0.35) !important; }
.quota-progress-fill.quota-amber { background: linear-gradient(90deg, #d97706 0%, #fbbf24 100%) !important; box-shadow: 0 0 8px rgba(251, 191, 36, 0.35) !important; }
.quota-progress-fill.quota-red { background: linear-gradient(90deg, #dc2626 0%, #f87171 100%) !important; box-shadow: 0 0 8px rgba(248, 113, 113, 0.45) !important; }
.quota-progress-fill.quota-blue { background: linear-gradient(90deg, #0284c7 0%, #38bdf8 100%) !important; box-shadow: 0 0 8px rgba(56, 189, 248, 0.35) !important; }
/* Early splash styling to avoid unstyled flash */
html.intro-skipped #intro-splash { display: none !important; }
</style>
<script>
try {
    if (sessionStorage.getItem('aem_intro_seen') && !window.location.search.includes('intro=1')) {
        document.documentElement.classList.add('intro-skipped');
    }
} catch (e) {}
</script>
</head>
<body>

<!-- ===================== INTRO OPENING SPLASH ===================== -->
<div id="intro-splash" class="intro-splash" role="dialog" aria-modal="true" aria-label="Loading Auto Exam Maker">
    <div class="intro-mesh" aria-hidden="true"></div>
    <div class="intro-content">
        <div class="intro-beacon">
            <div class="intro-aura" aria-hidden="true"></div>
            <div class="intro-ring" aria-hidden="true"></div>
            <svg class="intro-logo-svg" width="76" height="76" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="introBgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#1e1b4b"/>
                        <stop offset="50%" stop-color="#0f172a"/>
                        <stop offset="100%" stop-color="#082f49"/>
                    </linearGradient>
                    <linearGradient id="introStrokeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#38bdf8"/>
                        <stop offset="50%" stop-color="#818cf8"/>
                        <stop offset="100%" stop-color="#c084fc"/>
                    </linearGradient>
                    <linearGradient id="introFacetTop" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#38bdf8"/>
                        <stop offset="100%" stop-color="#0284c7"/>
                    </linearGradient>
                    <linearGradient id="introFacetLeft" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#6366f1"/>
                        <stop offset="100%" stop-color="#4338ca"/>
                    </linearGradient>
                    <linearGradient id="introFacetRight" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#a855f7"/>
                        <stop offset="100%" stop-color="#7e22ce"/>
                    </linearGradient>
                </defs>
                <rect x="1" y="1" width="38" height="38" rx="11" fill="url(#introBgGrad)" stroke="url(#introStrokeGrad)" stroke-width="1.3"/>
                <path d="M20 9L29 14.2L20 19.4L11 14.2L20 9Z" fill="url(#introFacetTop)"/>
                <path d="M11 14.2L20 19.4V29.8L11 24.6V14.2Z" fill="url(#introFacetLeft)"/>
                <path d="M20 19.4L29 14.2V24.6L20 29.8V19.4Z" fill="url(#introFacetRight)"/>
                <path d="M20 9L20 19.4M11 14.2L20 19.4L29 14.2M20 19.4L20 29.8" stroke="rgba(255,255,255,0.45)" stroke-width="0.8" stroke-linecap="round" stroke-linejoin="round"/>
                <path class="intro-spark" d="M20 12L20.9 14.1L23 15L20.9 15.9L20 18L19.1 15.9L17 15L19.1 14.1L20 12Z" fill="#ffffff"/>
            </svg>
        </div>
        <div class="intro-text-group">
            <h1 class="intro-title">Auto Exam <span class="intro-gradient">Maker</span></h1>
            <p class="intro-subtitle">Cognitive Assessment Engine &bull; AI 2.0</p>
        </div>
        <div class="intro-loader-track" aria-hidden="true">
            <div class="intro-loader-fill" id="intro-loader-fill"></div>
        </div>
        <div class="intro-status-text" id="intro-status-text">Initializing Cognitive LPU Engine&hellip;</div>
    </div>
    <div class="intro-skip-hint">Click anywhere to skip</div>
</div>

<!-- Ambient background lighting for glassmorphic depth -->
<div class="ambient-glow ambient-top-left" aria-hidden="true"></div>
<div class="ambient-glow ambient-top-right" aria-hidden="true"></div>
<div class="ambient-glow ambient-center" aria-hidden="true"></div>

<!-- ===================== NAVBAR ===================== -->
<header class="site-header" id="site-header">
    <div class="container header-inner">

        <!-- Brand -->
        <a class="brand" href="index.php" aria-label="Auto Exam Maker home">
            <div class="brand-logo-wrap">
                <svg class="brand-logo-svg" width="38" height="38" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="brandBgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#1e1b4b"/>
                            <stop offset="50%" stop-color="#0f172a"/>
                            <stop offset="100%" stop-color="#082f49"/>
                        </linearGradient>
                        <linearGradient id="brandStrokeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#38bdf8"/>
                            <stop offset="50%" stop-color="#818cf8"/>
                            <stop offset="100%" stop-color="#c084fc"/>
                        </linearGradient>
                        <linearGradient id="facetTop" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#38bdf8"/>
                            <stop offset="100%" stop-color="#0284c7"/>
                        </linearGradient>
                        <linearGradient id="facetLeft" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#6366f1"/>
                            <stop offset="100%" stop-color="#4338ca"/>
                        </linearGradient>
                        <linearGradient id="facetRight" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#a855f7"/>
                            <stop offset="100%" stop-color="#7e22ce"/>
                        </linearGradient>
                    </defs>
                    <!-- Base Squircle -->
                    <rect x="1" y="1" width="38" height="38" rx="11" fill="url(#brandBgGrad)" stroke="url(#brandStrokeGrad)" stroke-width="1.3"/>
                    <!-- Isometric Faceted Academic Prism / Layered AI Hexagon -->
                    <path d="M20 9L29 14.2L20 19.4L11 14.2L20 9Z" fill="url(#facetTop)"/>
                    <path d="M11 14.2L20 19.4V29.8L11 24.6V14.2Z" fill="url(#facetLeft)"/>
                    <path d="M20 19.4L29 14.2V24.6L20 29.8V19.4Z" fill="url(#facetRight)"/>
                    <!-- Specular Highlights -->
                    <path d="M20 9L20 19.4M11 14.2L20 19.4L29 14.2M20 19.4L20 29.8" stroke="rgba(255,255,255,0.45)" stroke-width="0.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <!-- Floating AI Spark Ignition Core -->
                    <path d="M20 12L20.9 14.1L23 15L20.9 15.9L20 18L19.1 15.9L17 15L19.1 14.1L20 12Z" fill="#ffffff"/>
                </svg>
            </div>
            <div class="brand-text">
                <div class="brand-name-row">
                    <span class="brand-name">Auto Exam <span class="brand-name-gradient">Maker</span></span>
                    <span class="brand-ai-badge">AI 2.0</span>
                </div>
                <span class="brand-sub">Cognitive Assessment Engine</span>
            </div>
        </a>

        <!-- Desktop nav links -->
        <nav class="site-nav" aria-label="Main navigation">
            <a class="nav-link" href="#upload">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span>New Exam</span>
            </a>
            <a class="nav-link" href="#exams">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <span>My Exams</span>
            </a>
            <a class="nav-link" href="#backend">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                <span>System Health</span>
            </a>
        </nav>

        <!-- Right side -->
        <div class="header-actions">
            <!-- Professional Single-Line Header Token Badge: Remaining / Limit -->
            <div class="header-token-badge" id="header-token-badge" title="AI Rate Limit: <?= $initRemTokens ?> / <?= $initLimTokens ?> TPM">
                <span class="htb-icon-wrap" aria-hidden="true">
                    <svg class="htb-bolt" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                </span>
                <span class="htb-remaining" id="htb-remaining"><?= $initRemTokens ?></span>
                <span class="htb-sep">/</span>
                <span class="htb-limit" id="htb-limit"><?= $initLimTokens ?></span>
                <span class="htb-unit">Tokens</span>
                <span class="htb-dot online" id="htb-dot" title="Engine online"></span>
            </div>

            <div class="backend-status" id="backend-status" style="display:none;"></div>
            <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false" aria-controls="mobile-nav">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

    </div>
</header>

<!-- Mobile nav drawer -->
<div class="mobile-nav" id="mobile-nav" role="navigation" aria-label="Mobile navigation">
    <a class="mobile-nav-link" href="#upload" id="mnl-upload">
        <span class="mnl-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
        </span>
        <div>
            <div style="font-weight:600;">New Exam</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:1px;">Upload a PDF to generate questions</div>
        </div>
    </a>
    <a class="mobile-nav-link" href="#exams" id="mnl-exams">
        <span class="mnl-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
        </span>
        <div>
            <div style="font-weight:600;">My Exams</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:1px;">View and manage your generated exams</div>
        </div>
    </a>
    <div class="mobile-nav-divider"></div>
    <a class="mobile-nav-link" href="#backend" id="mnl-backend">
        <span class="mnl-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
        </span>
        <div>
            <div style="font-weight:600;">System Status</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:1px;">Check backend dependencies &amp; AI limits</div>
        </div>
    </a>
</div>

<!-- ===================== MAIN ===================== -->
<main class="container">

    <!-- Upload card -->
    <section class="card upload-card" id="upload">
        <div class="studio-badge-row">
            <span class="studio-badge">
                <span class="material-symbols-rounded studio-badge-icon">auto_awesome</span>
                <span>Cognitive AI Exam Studio</span>
                <span class="studio-version-tag">LPU Accelerated</span>
            </span>
        </div>
        <h1 class="card-main-title">Generate High-Impact <span class="gradient-text">Interactive Exams</span></h1>
        <p class="muted card-desc">Drop any PDF document (lecture slides, textbook chapters, reviewers, or syllabi). Our multi-pass cognitive engine maps conceptual hierarchies to synthesize rigorous application scenarios, dual-mode test runners, and exhaustive rationale keys.</p>
        
        <div class="feature-strip">
            <div class="feature-card feature-card-indigo">
                <div class="fc-glow"></div>
                <div class="fc-header">
                    <div class="fc-icon-wrap fc-indigo">
                        <span class="material-symbols-rounded">psychology</span>
                    </div>
                    <span class="fc-tag fc-tag-indigo">BLOOM'S TAXONOMY</span>
                </div>
                <div class="fc-body">
                    <h4 class="fc-title">~30 Questions / Chapter</h4>
                    <p class="fc-desc">Scenario, case analysis &amp; multi-tier logic questions testing deep cognitive application &mdash; not simple recall.</p>
                </div>
            </div>

            <div class="feature-card feature-card-cyan">
                <div class="fc-glow"></div>
                <div class="fc-header">
                    <div class="fc-icon-wrap fc-cyan">
                        <span class="material-symbols-rounded">timer</span>
                    </div>
                    <span class="fc-tag fc-tag-cyan">DUAL MODES</span>
                </div>
                <div class="fc-body">
                    <h4 class="fc-title">Dual Testing Modes</h4>
                    <p class="fc-desc">High-stakes 10s blitz countdown or self-paced reflective study with immediate answer evaluation and scoring.</p>
                </div>
            </div>

            <div class="feature-card feature-card-purple">
                <div class="fc-glow"></div>
                <div class="fc-header">
                    <div class="fc-icon-wrap fc-purple">
                        <span class="material-symbols-rounded">auto_stories</span>
                    </div>
                    <span class="fc-tag fc-tag-purple">DEEP RATIONALE</span>
                </div>
                <div class="fc-body">
                    <h4 class="fc-title">Key &amp; Encyclopedia</h4>
                    <p class="fc-desc">Instant evaluation, exhaustive rationales for every distractor, and an interactive domain concept glossary.</p>
                </div>
            </div>
        </div>

        <form id="upload-form">
            <div class="dropzone-outer">
                <label class="dropzone" id="dropzone">
                    <input type="file" id="pdf-file" name="pdf" accept="application/pdf,.pdf" required>
                    
                    <div class="dz-idle-state" id="dz-idle-state">
                        <div class="dz-beacon">
                            <div class="dz-beacon-ring"></div>
                            <div class="dz-icon-wrap">
                                <span class="material-symbols-rounded dz-icon">cloud_upload</span>
                            </div>
                        </div>
                        <div class="dz-text-group">
                            <span class="dz-headline">Click to browse or drop your PDF document here</span>
                            <span class="dz-subline">Accepts textbook chapters, lecture presentations, notes &bull; up to 50MB</span>
                        </div>
                        <div class="dz-chips-row">
                            <span class="dz-chip"><span class="material-symbols-rounded">picture_as_pdf</span> PDF Document</span>
                            <span class="dz-chip"><span class="material-symbols-rounded">insights</span> Deep Chapter Mapping</span>
                            <span class="dz-chip"><span class="material-symbols-rounded">verified_user</span> 100% Private &amp; Local</span>
                        </div>
                    </div>

                    <!-- Active Selected File Card (revealed when file chosen) -->
                    <div class="dz-file-selected" id="dz-file-selected" style="display:none;">
                        <div class="dfs-icon-wrap">
                            <span class="material-symbols-rounded dfs-pdf-icon">picture_as_pdf</span>
                        </div>
                        <div class="dfs-info">
                            <div class="dfs-name" id="dfs-name">selected_document.pdf</div>
                            <div class="dfs-meta">
                                <span class="dfs-size" id="dfs-size">0.00 MB</span>
                                <span class="dfs-divider">&bull;</span>
                                <span class="dfs-status"><span class="material-symbols-rounded dfs-check">check_circle</span> Ready to synthesize</span>
                            </div>
                        </div>
                        <button type="button" class="dfs-remove-btn" id="dfs-remove-btn" title="Change file">
                            <span class="material-symbols-rounded">swap_horiz</span>
                            <span>Change File</span>
                        </button>
                    </div>

                    <div class="dz-hint" id="dz-hint" style="display:none;"></div>
                </label>
            </div>

            <div class="form-row">
                <div class="form-label-row">
                    <label for="title-input" class="field-label">
                        <span class="material-symbols-rounded label-icon">edit_note</span>
                        <span>Exam Title</span>
                    </label>
                    <span class="label-badge-optional">Optional &mdash; auto-derived from PDF filename</span>
                </div>
                <div class="input-wrap input-glow-wrap">
                    <span class="material-symbols-rounded input-leading-symbol">title</span>
                    <input type="text" id="title-input" name="title" placeholder="e.g. Chapter 4: Distributed Consensus &amp; Raft" maxlength="120">
                </div>
            </div>

            <div class="form-actions-row">
                <button type="submit" class="btn btn-primary btn-lg btn-synthesize" id="submit-btn" disabled>
                    <span class="material-symbols-rounded btn-synth-icon">auto_awesome</span>
                    <span class="btn-text">Synthesize Interactive Exam</span>
                </button>
                <div class="synthesis-hint">
                    <span class="material-symbols-rounded hint-icon">bolt</span>
                    <span>Generates interactive examination suite &amp; chapter glossary</span>
                </div>
            </div>

            <!-- Active Status / Pipeline Tracker -->
            <div id="status-area" class="status-card" <?= $activeJobId ? '' : 'hidden' ?>>
                <div class="status-card-header">
                    <div class="status-title-group">
                        <div class="status-badge-row">
                            <span class="pulse-dot"></span>
                            <span class="status-badge" id="status-phase-badge">Phase 1 of 5</span>
                            <span class="status-eta-pill" id="status-eta-pill"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="display:inline;vertical-align:-1px;margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>Calculating ETA&hellip;</span>
                        </div>
                        <h3 class="status-phase-heading" id="status-phase-heading"><?= $activeJobId ? 'Resuming Progress&hellip;' : 'Preparation' ?></h3>
                        <p class="status-phase-sub" id="status-phase-sub"><?= $activeJobId ? 'Syncing with generation worker&hellip;' : 'Extracting text &amp; analyzing document structure' ?></p>
                    </div>
                    <div class="status-actions">
                        <button type="button" class="btn btn-sm btn-danger-soft" id="cancel-tracked-btn" title="Cancel generation">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            <span>Cancel</span>
                        </button>
                    </div>
                </div>

                <div class="phase-stepper" id="phase-stepper"></div>

                <div class="phase-progress-wrap">
                    <div class="phase-progress-bar" id="phase-progress-bar" style="width: 0%;"></div>
                </div>

                <div class="token-live-banner" id="token-live-banner" style="display:none;">
                    <div class="token-live-metric">
                        <span class="t-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="color:var(--accent-amber);vertical-align:-1px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg></span>
                        <span class="t-lbl">Exam AI Tokens:</span>
                        <strong class="t-val" id="token-live-count">0</strong>
                    </div>
                    <div class="token-live-metric" id="token-live-rate-wrap">
                        <span class="t-lbl">Groq Rate Limit:</span>
                        <span class="t-val-sm" id="token-live-rate-text">8,000 / 8,000 TPM</span>
                        <div class="tpm-track" title="Remaining tokens per minute">
                            <div class="tpm-bar" id="token-live-tpm-bar" style="width: 100%;"></div>
                        </div>
                        <span class="t-reset-tag" id="token-live-reset-tag">Resets in 0s</span>
                    </div>
                </div>

                <div class="status-footer">
                    <div class="status-footer-msg">
                        <span class="spinner-sm"></span>
                        <span id="status-text">Starting exam generation&hellip;</span>
                    </div>
                    <div class="status-percent-text" id="status-percent-text">0%</div>
                </div>
            </div>
        </form>
    </section>

    <!-- Exams list -->
    <section class="card" id="exams">
        <div class="section-header-row">
            <div>
                <div class="section-tag-wrapper">
                    <span class="section-tag">
                        <span class="section-tag-dot"></span>
                        Exam Repository
                    </span>
                </div>
                <h2 class="section-title">Your Generated Exams</h2>
                <p class="muted section-sub">Access, practice, or export any exam you have generated.</p>
            </div>
            <div class="section-header-actions">
                <span class="count-pill">
                    <strong><?= count($exams) ?></strong> <?= count($exams) === 1 ? 'Exam' : 'Exams' ?>
                </span>
            </div>
        </div>

        <?php if (empty($exams)): ?>
            <div class="empty-state">
                <div class="empty-icon-wrap">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </div>
                <h3>No Exams Generated Yet</h3>
                <p class="muted">Upload your first PDF document above to start generating comprehensive chapter-by-chapter interactive exams.</p>
            </div>
        <?php else: ?>
        <div class="table-glass-wrapper">
            <table class="exam-table">
                <thead>
                    <tr>
                        <th>Title &amp; Source</th>
                        <th>Status</th>
                        <th>Chapters</th>
                        <th>Questions</th>
                        <th>AI Tokens</th>
                        <th class="th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($exams as $ex): ?>
                    <tr data-id="<?= htmlspecialchars($ex['id']) ?>">
                        <td class="tt">
                            <div class="exam-title-row">
                                <span class="exam-info-badge has-tooltip" data-tooltip="Generated <?= htmlspecialchars(date('M j, Y g:i A', strtotime($ex['created_at']))) ?>" title="Generated <?= htmlspecialchars(date('M j, Y g:i A', strtotime($ex['created_at']))) ?>" aria-label="Generated date">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="16" x2="12" y2="12"></line>
                                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                    </svg>
                                </span>
                                <div class="exam-title-cell has-tooltip-title" data-title-tooltip="<?= htmlspecialchars($ex['title']) ?>" title="<?= htmlspecialchars($ex['title']) ?>"><?= htmlspecialchars($ex['title']) ?></div>
                            </div>
                            <?php if ($ex['source_name']): ?>
                                <div class="exam-source-chip" title="Original PDF File">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                    <span><?= htmlspecialchars($ex['source_name']) ?></span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= htmlspecialchars($ex['status']) ?>" data-status="<?= htmlspecialchars($ex['status']) ?>">
                                <?= htmlspecialchars($ex['status']) ?>
                            </span>
                            <?php if (in_array($ex['status'], ['queued', 'running'], true)): ?>
                                <div class="phase-mini" data-phase-mini="<?= htmlspecialchars($ex['id']) ?>"></div>
                                <div class="eta" data-eta="<?= htmlspecialchars($ex['id']) ?>">&hellip;</div>
                            <?php endif; ?>
                        </td>
                        <td><span class="metric-num"><?= (int)$ex['num_chapters'] ?: '&mdash;' ?></span></td>
                        <td><span class="metric-num"><?= (int)$ex['total_questions'] ?: '&mdash;' ?></span></td>
                        <td>
                            <?php if (!empty($ex['total_tokens'])): ?>
                                <span class="token-pill" title="<?= number_format((int)$ex['total_tokens']) ?> tokens consumed">
                                    <svg class="token-pill-bolt" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-1px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg> <?= number_format((int)$ex['total_tokens']) ?>
                                </span>
                            <?php elseif ($ex['status'] === 'done'): ?>
                                <span class="token-pill" title="Estimated ~18,000 tokens">
                                    <svg class="token-pill-bolt" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-1px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg> ~18k
                                </span>
                            <?php else: ?>
                                <span class="muted" data-exam-tokens="<?= htmlspecialchars($ex['id']) ?>">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td class="td-actions">
                            <?php if ($ex['status'] === 'done'): ?>
                                <div class="row-actions">
                                    <a class="btn btn-action-icon btn-action-primary has-tooltip" href="exam.php?id=<?= urlencode($ex['id']) ?>" data-tooltip="Open Interactive Exam" title="Open Interactive Exam" aria-label="Open Exam">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                    </a>
                                    <a class="btn btn-action-icon btn-action-secondary download-btn-sm has-tooltip" href="export.php?id=<?= urlencode($ex['id']) ?>" download="<?= htmlspecialchars(preg_replace('/[^A-Za-z0-9_\-]/', '_', $ex['title'])) ?>.html" data-tooltip="Download Standalone HTML" title="Download Standalone HTML" aria-label="Download HTML">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    </a>
                                    <button type="button" class="btn btn-action-icon btn-action-danger has-tooltip" onclick="deleteExam('<?= htmlspecialchars($ex['id']) ?>')" data-tooltip="Delete Exam" title="Delete Exam" aria-label="Delete Exam">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </div>
                            <?php elseif (in_array($ex['status'], ['queued', 'running'], true)): ?>
                                <div class="row-actions">
                                    <a class="btn btn-action-icon btn-action-primary has-tooltip" href="?job=<?= urlencode($ex['id']) ?>" id="job-link-<?= htmlspecialchars($ex['id']) ?>" data-tooltip="Track Generation Progress" title="Track Progress" aria-label="Track Progress">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                                    </a>
                                    <button type="button" class="btn btn-action-icon btn-action-danger has-tooltip" data-cancel="<?= htmlspecialchars($ex['id']) ?>" data-tooltip="Cancel Generation" title="Cancel Generation" aria-label="Cancel">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="row-actions">
                                    <button type="button" class="btn btn-action-icon btn-action-danger has-tooltip" onclick="deleteExam('<?= htmlspecialchars($ex['id']) ?>')" data-tooltip="Delete Exam" title="Delete Exam" aria-label="Delete Exam">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- Backend status & AI Quota -->
    <section class="card" id="backend">
        <div class="backend-header-row">
            <div>
                <div class="section-tag-wrapper">
                    <span class="section-tag">
                        <span class="section-tag-dot"></span>
                        Telemetry &amp; Engine Quotas
                    </span>
                </div>
                <h2 class="section-title">System &amp; AI Engine Status</h2>
                <p class="muted section-sub">Live token replenishment rates, API quotas, and backend micro-dependencies.</p>
            </div>
            <button type="button" class="btn btn-ghost" id="refresh-quota-btn" title="Refresh Live Quotas">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <polyline points="1 20 1 14 7 14"></polyline>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                </svg>
                <span>Refresh Quotas</span>
            </button>
        </div>
        
        <!-- Live AI Quota Grid -->
        <div class="ai-quota-grid" id="ai-quota-grid">
            <div class="quota-stat-card">
                <div class="stat-top">
                    <span class="stat-title">AI Provider &amp; Model</span>
                    <span class="badge-pill badge-green" id="quota-status-pill">Active</span>
                </div>
                <div class="stat-main-val" id="quota-provider-name">Groq LPU Engine</div>
                <div class="stat-sub-val" id="quota-model-name"><?= $initModel ?></div>
                <div class="card-glow-line glow-cyan"></div>
            </div>

            <div class="quota-stat-card">
                <div class="stat-top">
                    <span class="stat-title">Tokens Per Minute (TPM)</span>
                    <span class="stat-badge" id="quota-tpm-percent"><?= $initTpmPct ?>%</span>
                </div>
                <div class="stat-main-val" id="quota-tpm-remaining"><?= $initRemTokens ?> / <?= $initLimTokens ?></div>
                <div class="quota-progress-track">
                    <div class="quota-progress-fill <?= $initTpmPct < 20 ? 'quota-red' : ($initTpmPct < 50 ? 'quota-amber' : 'quota-green') ?>" id="quota-tpm-fill" style="width: <?= $initTpmPct ?>%;"></div>
                </div>
                <div class="stat-sub-val" id="quota-tpm-reset">Cooldown: <?= $initResetTokens ?></div>
                <div class="card-glow-line glow-emerald"></div>
            </div>

            <div class="quota-stat-card">
                <div class="stat-top">
                    <span class="stat-title">Requests Per Minute (RPM)</span>
                    <span class="stat-badge" id="quota-rpm-percent"><?= $initRpmPct ?>%</span>
                </div>
                <div class="stat-main-val" id="quota-rpm-remaining"><?= $initRemRequests ?> / <?= $initLimRequests ?></div>
                <div class="quota-progress-track">
                    <div class="quota-progress-fill quota-blue" id="quota-rpm-fill" style="width: <?= $initRpmPct ?>%;"></div>
                </div>
                <div class="stat-sub-val" id="quota-rpm-reset">Reset: <?= $initResetRequests ?></div>
                <div class="card-glow-line glow-blue"></div>
            </div>

            <div class="quota-stat-card">
                <div class="stat-top">
                    <span class="stat-title">Total Lifetime Tokens</span>
                    <span class="t-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg></span>
                </div>
                <div class="stat-main-val" id="quota-lifetime-tokens"><?= number_format(exam_lifetime_tokens()) ?></div>
                <div class="stat-sub-val">Aggregated usage across generated exams</div>
                <div class="card-glow-line glow-purple"></div>
            </div>
        </div>

        <div class="sys-check-grid" style="margin-top: 1.5rem;">
            <div class="sys-check-item <?= $phpOk ? 'ok' : 'bad' ?>">
                <div class="sys-check-icon"><?= $phpOk ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' ?></div>
                <div class="sys-check-info">
                    <span class="sys-check-title">PHP CLI Runtime</span>
                    <span class="sys-check-detail" title="<?= htmlspecialchars(PHP_EXE) ?>"><?= $phpOk ? 'Found: ' . htmlspecialchars(basename(PHP_EXE)) : 'Missing executable' ?></span>
                </div>
                <span class="sys-badge <?= $phpOk ? 'ok' : 'bad' ?>"><?= $phpOk ? 'Ready' : 'Error' ?></span>
            </div>

            <div class="sys-check-item <?= $opencodeOk ? 'ok' : 'bad' ?>">
                <div class="sys-check-icon"><?= $opencodeOk ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' ?></div>
                <div class="sys-check-info">
                    <span class="sys-check-title">opencode CLI</span>
                    <span class="sys-check-detail" title="<?= htmlspecialchars(OPENCODE_EXE) ?>"><?= $opencodeOk ? 'Found: ' . htmlspecialchars(basename(OPENCODE_EXE)) : 'Missing executable' ?></span>
                </div>
                <span class="sys-badge <?= $opencodeOk ? 'ok' : 'bad' ?>"><?= $opencodeOk ? 'Ready' : 'Error' ?></span>
            </div>

            <div class="sys-check-item ok">
                <div class="sys-check-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                <div class="sys-check-info">
                    <span class="sys-check-title">LPU Acceleration</span>
                    <span class="sys-check-detail">Direct Groq Hardware (gpt-oss-20b)</span>
                </div>
                <span class="sys-badge ok">Optimal</span>
            </div>

            <div class="sys-check-item <?= is_file(SKILL_DIR . '/SKILL.md') ? 'ok' : 'bad' ?>">
                <div class="sys-check-icon"><?= is_file(SKILL_DIR . '/SKILL.md') ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' ?></div>
                <div class="sys-check-info">
                    <span class="sys-check-title">Agent Skill</span>
                    <span class="sys-check-detail"><?= htmlspecialchars(SKILL_NAME) ?></span>
                </div>
                <span class="sys-badge <?= is_file(SKILL_DIR . '/SKILL.md') ? 'ok' : 'bad' ?>"><?= is_file(SKILL_DIR . '/SKILL.md') ? 'Active' : 'Missing' ?></span>
            </div>
        </div>
    </section>

</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-brand">
            <svg class="footer-logo-svg" width="24" height="24" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="1" y="1" width="38" height="38" rx="10" fill="url(#brandBgGrad)" stroke="url(#brandStrokeGrad)" stroke-width="1.3"/>
                <path d="M20 9L29 14.2L20 19.4L11 14.2L20 9Z" fill="url(#facetTop)"/>
                <path d="M11 14.2L20 19.4V29.8L11 24.6V14.2Z" fill="url(#facetLeft)"/>
                <path d="M20 19.4L29 14.2V24.6L20 29.8V19.4Z" fill="url(#facetRight)"/>
                <path d="M20 9L20 19.4M11 14.2L20 19.4L29 14.2M20 19.4L20 29.8" stroke="rgba(255,255,255,0.45)" stroke-width="0.8" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M20 12L20.9 14.1L23 15L20.9 15.9L20 18L19.1 15.9L17 15L19.1 14.1L20 12Z" fill="#ffffff"/>
            </svg>
            <span>Auto Exam Maker</span>
            <span class="footer-version-tag">Local Studio v2.4</span>
        </div>
        <div class="footer-note">Runs locally on your environment via opencode &amp; Groq LPU &bull; Zero external data tracking</div>
    </div>
</footer>

<script>
const AUTO_POLL_JOB = <?= json_encode($activeJobId ?: null) ?>;
</script>
<script src="assets/site.js?v=<?= filemtime(__DIR__ . '/assets/site.js') ?>"></script>
</body>
</html>