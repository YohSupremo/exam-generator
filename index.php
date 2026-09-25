<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/db.php';

$userToken = get_or_create_user_token();
$exams = exam_list($userToken);
$hasDirectAi = (defined('GROQ_API_KEY') && GROQ_API_KEY !== '')
    || (defined('OPENROUTER_API_KEY') && OPENROUTER_API_KEY !== '')
    || (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '');
$opencodeOk = is_file(OPENCODE_EXE) || $hasDirectAi;
$phpOk = is_file(PHP_EXE) || (defined('PHP_BINARY') && is_file(PHP_BINARY));

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
<meta name="theme-color" content="#09090b">
<meta name="description" content="SynthExam — upload a PDF and instantly generate a comprehensive interactive exam powered by AI.">
<title>SynthExam — AI-Powered Exam Generator</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..900;1,14..32,300..900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
<link rel="stylesheet" href="assets/site.css?v=<?= filemtime(__DIR__ . '/assets/site.css') ?>">
<style>
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

<!-- ===================== INTRO OPENING SPLASH: ROBOT TACTICAL BOOT INTERFACE ===================== -->
<div id="intro-splash" class="intro-splash" role="dialog" aria-modal="true" aria-label="Initializing SynthExam Robot Console">
    <div class="intro-mesh" aria-hidden="true"></div>
    <div class="intro-scanlines" aria-hidden="true"></div>

    <!-- 4 Tactical HUD Corner Brackets -->
    <div class="intro-corner intro-corner-tl" aria-hidden="true"></div>
    <div class="intro-corner intro-corner-tr" aria-hidden="true"></div>
    <div class="intro-corner intro-corner-bl" aria-hidden="true"></div>
    <div class="intro-corner intro-corner-br" aria-hidden="true"></div>

    <!-- Top Telemetry Stream -->
    <div class="intro-telemetry-bar" aria-hidden="true">
        <span class="telemetry-node"><span class="telemetry-led"></span> SYS-CORE: LPU QUANTUM v2.4</span>
        <span class="telemetry-divider">//</span>
        <span class="telemetry-node">NODE: 0x4F-88</span>
        <span class="telemetry-divider">//</span>
        <span class="telemetry-node">SECURITY: ENCRYPTED (TLS 1.3)</span>
    </div>

    <div class="intro-content">
        <!-- Center Cyber Reactor Beacon -->
        <div class="intro-beacon">
            <div class="intro-aura" aria-hidden="true"></div>
            <div class="intro-ring intro-ring-outer" aria-hidden="true"></div>
            <div class="intro-ring intro-ring-inner" aria-hidden="true"></div>
            <svg class="intro-logo-svg" width="68" height="68" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="introBgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#27272a"/>
                        <stop offset="50%" stop-color="#18181b"/>
                        <stop offset="100%" stop-color="#09090b"/>
                    </linearGradient>
                    <linearGradient id="introStrokeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#ffffff"/>
                        <stop offset="50%" stop-color="#a1a1aa"/>
                        <stop offset="100%" stop-color="#52525b"/>
                    </linearGradient>
                    <linearGradient id="introFacetTop" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#ffffff"/>
                        <stop offset="100%" stop-color="#d4d4d8"/>
                    </linearGradient>
                    <linearGradient id="introFacetLeft" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#a1a1aa"/>
                        <stop offset="100%" stop-color="#52525b"/>
                    </linearGradient>
                    <linearGradient id="introFacetRight" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#71717a"/>
                        <stop offset="100%" stop-color="#27272a"/>
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
            <div class="intro-system-tag">&gt;&gt; COGNITIVE_KERNEL_BOOT // v2.0</div>
            <h1 class="intro-title">SYNTH<span class="intro-gradient">EXAM</span> <span class="intro-version-chip">AI_2.0</span></h1>
            <p class="intro-subtitle">AUTONOMOUS COGNITIVE ASSESSMENT PLATFORM</p>
        </div>

        <!-- Robot Typewriter Terminal Interface -->
        <div class="robot-boot-terminal">
            <div class="robot-boot-header">
                <div class="robot-boot-dots">
                    <span class="robot-dot dot-r"></span>
                    <span class="robot-dot dot-y"></span>
                    <span class="robot-dot dot-g"></span>
                </div>
                <div class="robot-boot-tab">TERMINAL: /dev/tty0 &bull; BOOT_SEQUENCE</div>
                <div class="robot-boot-stats">STATUS: <span id="robot-boot-stat-val" class="stat-live">INITIALIZING</span></div>
            </div>
            <div class="robot-boot-body" id="robot-boot-body">
                <!-- Lines typed out dynamically with typewriter effect -->
            </div>
        </div>

        <!-- Diagnostic Progress Track & Percentage -->
        <div class="intro-progress-wrap">
            <div class="intro-progress-meta">
                <span class="intro-progress-label" id="intro-progress-label">INITIALIZING NEURAL SUBSYSTEMS...</span>
                <span class="intro-progress-percent" id="intro-progress-percent">0%</span>
            </div>
            <div class="intro-loader-track" aria-hidden="true">
                <div class="intro-loader-fill" id="intro-loader-fill"></div>
            </div>
        </div>
    </div>

    <!-- Skip Hint Button -->
    <div class="intro-skip-hint">
        <span class="skip-key-badge">ESC</span> or <span class="skip-key-badge">CLICK ANYWHERE</span> to skip sequence
    </div>
</div>

<!-- ===================== DISCLAIMER & TERMS MODAL ===================== -->
<div id="terms-modal" class="terms-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="terms-modal-title" aria-hidden="true">
    <div class="terms-modal-backdrop"></div>
    <div class="terms-modal-card">
        <div class="terms-modal-header">
            <div class="terms-icon-badge">
                <span class="material-symbols-rounded">policy</span>
            </div>
            <div class="terms-header-text">
                <div class="terms-badge-row">
                    <span class="terms-badge">Studio Policies &bull; v2.4</span>
                </div>
                <h2 id="terms-modal-title" class="terms-title">Disclaimer &amp; Terms of Service</h2>
                <p class="terms-sub">Please review and acknowledge the educational disclaimer and usage terms before utilizing the studio.</p>
            </div>
            <button type="button" class="terms-close-btn" id="terms-close-btn" aria-label="Close terms modal" style="display:none;">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>

        <div class="terms-scroll-body" tabindex="0">
            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="material-symbols-rounded">psychology</span>
                    <h4>1. AI Synthesis &amp; Academic Verification</h4>
                </div>
                <p>All examination questions, distractors, answer keys, and cognitive rationales are autonomously generated using large language models. While engineered for rigorous academic depth, artificial intelligence outputs may occasionally contain hallucinations or contextual inaccuracies. All generated materials are intended as assistive review aids and should be cross-referenced with your official course syllabi and textbooks.</p>
            </div>

            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="material-symbols-rounded">school</span>
                    <h4>2. Educational &amp; Self-Study Purpose</h4>
                </div>
                <p>SynthExam is strictly an educational tool designed for self-assessment, study preparation, and cognitive review. It is not an accredited examination board and does not confer official certification, academic credit, or professional qualification.</p>
            </div>

            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="material-symbols-rounded">shield</span>
                    <h4>3. Document Ownership &amp; Fair Use</h4>
                </div>
                <p>You certify and warrant that you own or have obtained lawful authorization to upload and process any PDF documents submitted to the platform under applicable copyright or fair-use educational provisions. Document processing operates with zero persistent training retention.</p>
            </div>

            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="material-symbols-rounded">gavel</span>
                    <h4>4. Limitation of Liability</h4>
                </div>
                <p>The service and its synthesized outputs are provided strictly &ldquo;AS IS&rdquo; without warranties of any kind. The developers and providers disclaim all liability for any direct or indirect academic, educational, or testing outcomes resulting from the use of this software.</p>
            </div>
        </div>

        <div class="terms-modal-footer">
            <label class="terms-checkbox-label" for="terms-agree-checkbox">
                <input type="checkbox" id="terms-agree-checkbox" class="terms-checkbox">
                <span class="terms-checkbox-custom">
                    <span class="material-symbols-rounded terms-check-icon">check</span>
                </span>
                <span class="terms-label-text">I have read, understand, and agree to the <strong>Terms of Service</strong> and <strong>AI Assessment Disclaimer</strong>.</span>
            </label>

            <div class="terms-actions-row">
                <button type="button" id="terms-accept-btn" class="btn btn-primary btn-lg terms-accept-btn" disabled>
                    <span class="material-symbols-rounded">verified</span>
                    <span>Accept &amp; Enter Studio</span>
                </button>
            </div>
            <div class="terms-persist-note">Your acknowledgement is securely remembered on this device.</div>
        </div>
    </div>
</div>

<!-- Deletion Confirmation Dialog Card -->
<div id="delete-modal" class="custom-modal-overlay" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title" style="display:none;">
    <div class="custom-modal-backdrop" id="delete-modal-backdrop"></div>
    <div class="custom-modal-card custom-modal-card-danger">
        <div class="custom-modal-header">
            <div class="custom-modal-icon-badge badge-danger">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
            </div>
            <div class="custom-modal-header-text">
                <div class="custom-modal-badge-row">
                    <span class="custom-modal-badge badge-danger-tag">Permanent Action</span>
                </div>
                <h3 id="delete-modal-title" class="custom-modal-title">Delete Exam?</h3>
                <p id="delete-modal-desc" class="custom-modal-sub">Are you sure you want to permanently delete this exam and all generated content?</p>
            </div>
            <button type="button" class="custom-modal-close-btn" id="delete-modal-close-btn" aria-label="Close dialog">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <div class="custom-modal-body">
            <div class="delete-target-preview">
                <div class="delete-target-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </div>
                <div class="delete-target-info">
                    <span class="delete-target-label">Selected Exam</span>
                    <strong class="delete-target-name" id="delete-target-name">&mdash;</strong>
                </div>
            </div>
            <p class="delete-warning-note">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                <span>This cannot be undone. All interactive chapters, question keys, and diagnostics will be removed immediately.</span>
            </p>
        </div>

        <div class="custom-modal-footer">
            <button type="button" class="btn btn-secondary custom-modal-cancel-btn" id="delete-modal-cancel-btn">
                <span>Cancel</span>
            </button>
            <button type="button" class="btn btn-danger custom-modal-confirm-btn" id="delete-modal-confirm-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                <span class="btn-text">Delete Exam</span>
            </button>
        </div>
    </div>
</div>

<!-- Floating Notification Card (Toast Container) -->
<div id="studio-toast-container" class="studio-toast-container" aria-live="polite" aria-atomic="true"></div>

<!-- Ambient background lighting for glassmorphic depth -->
<div class="ambient-glow ambient-top-left" aria-hidden="true"></div>
<div class="ambient-glow ambient-top-right" aria-hidden="true"></div>
<div class="ambient-glow ambient-center" aria-hidden="true"></div>

<!-- Cyber Pointer Snake / Trail Animation -->
<canvas id="pointer-snake-canvas" class="pointer-snake-canvas" aria-hidden="true"></canvas>

<!-- ===================== NAVBAR ===================== -->
<header class="site-header" id="site-header">
    <div class="container header-inner">

        <!-- Brand -->
        <a class="brand" href="index.php" aria-label="SynthExam home">
            <div class="brand-logo-wrap">
                <svg class="brand-logo-svg" width="38" height="38" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="brandBgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#27272a"/>
                            <stop offset="50%" stop-color="#18181b"/>
                            <stop offset="100%" stop-color="#09090b"/>
                        </linearGradient>
                        <linearGradient id="brandStrokeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ffffff"/>
                            <stop offset="50%" stop-color="#a1a1aa"/>
                            <stop offset="100%" stop-color="#52525b"/>
                        </linearGradient>
                        <linearGradient id="facetTop" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ffffff"/>
                            <stop offset="100%" stop-color="#d4d4d8"/>
                        </linearGradient>
                        <linearGradient id="facetLeft" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#a1a1aa"/>
                            <stop offset="100%" stop-color="#52525b"/>
                        </linearGradient>
                        <linearGradient id="facetRight" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#71717a"/>
                            <stop offset="100%" stop-color="#27272a"/>
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
                    <span class="brand-name">Synth<span class="brand-name-gradient">Exam</span></span>
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

            <!-- Sticky Header Sound Effects Toggle (Always accessible) -->
            <button type="button" class="header-sfx-btn robot-sfx-toggle" id="header-sfx-btn" aria-label="Toggle sound effects" title="Sound effects active (Click to mute)">
                <span class="material-symbols-rounded sfx-btn-icon">volume_up</span>
                <span class="sfx-btn-text">SFX: ON</span>
            </button>

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
    <section class="card upload-card compact-studio" id="upload">
        <div class="studio-header-compact">
            <div class="studio-badge-row">
                <div class="section-tag-wrapper">
                    <span class="section-tag">
                        <span class="section-tag-dot"></span>
                        SECTION 01: INGESTION STUDIO
                    </span>
                </div>

                <!-- Robot Terminal Telemetry HUD -->
                <div class="robot-hud-status">
                    <span class="robot-indicator-pulse"></span>
                    <span class="robot-hud-status-text">NEURAL LINK: ACTIVE</span>
                </div>
            </div>
            <div class="studio-title-desc-row">
                <h1 class="card-main-title">Generate High-Impact <span class="gradient-text">Interactive Exams</span></h1>
                <p class="muted card-desc" id="studio-robot-desc">Drop any PDF document (lecture slides, textbook chapters, reviewers, or syllabi) to synthesize rigorous application exams &amp; answer keys.</p>
            </div>

            <!-- Robot Tactical Terminal Bar with Typewriter Effect -->
            <div class="robot-terminal-bar" id="robot-terminal-bar" title="Click to advance telemetry stream">
                <div class="robot-terminal-channel">
                    <span class="robot-terminal-tag">SYSTEM CORE</span>
                    <span class="robot-terminal-channel-id">0x4F:88</span>
                </div>
                <div class="robot-terminal-feed">
                    <span class="robot-terminal-prompt">&gt;&gt;</span>
                    <span class="robot-typewriter-text" id="robot-typewriter-text">INITIALIZING COGNITIVE SYNTHESIS ENGINE...</span>
                    <span class="robot-terminal-cursor" aria-hidden="true"></span>
                </div>
            </div>
        </div>

        <form id="upload-form">
            <div class="studio-grid">
                <!-- LEFT: Primary Action Panel -->
                <div class="studio-col-action">
                    <div class="dropzone-outer hud-corners">
                        <span class="hud-corner hud-corner-tl" aria-hidden="true"></span>
                        <span class="hud-corner hud-corner-tr" aria-hidden="true"></span>
                        <span class="hud-corner hud-corner-bl" aria-hidden="true"></span>
                        <span class="hud-corner hud-corner-br" aria-hidden="true"></span>
                        <label class="dropzone dropzone-compact" id="dropzone">
                            <input type="file" id="pdf-file" name="pdf" accept="application/pdf,.pdf" required>
                            
                            <div class="dz-idle-state" id="dz-idle-state">
                                <div class="dz-beacon dz-beacon-compact">
                                    <div class="dz-beacon-ring"></div>
                                    <div class="dz-icon-wrap dz-icon-wrap-compact">
                                        <span class="material-symbols-rounded dz-icon">cloud_upload</span>
                                    </div>
                                </div>
                                <div class="dz-text-group">
                                    <span class="dz-headline">Click or drop your PDF document here</span>
                                    <span class="dz-subline">Textbooks, lecture slides, notes &bull; up to 50MB</span>
                                </div>
                                <div class="dz-chips-row dz-chips-compact">
                                    <span class="dz-chip"><span class="material-symbols-rounded">picture_as_pdf</span> PDF Document</span>
                                    <span class="dz-chip"><span class="material-symbols-rounded">insights</span> Deep Chapter Mapping</span>
                                    <span class="dz-chip"><span class="material-symbols-rounded">verified_user</span> 100% Private</span>
                                </div>
                            </div>

                            <!-- Active Selected File Card (revealed when file chosen) -->
                            <div class="dz-file-selected" id="dz-file-selected" style="display:none;">
                                <div class="dfs-main">
                                    <div class="dfs-icon-wrap">
                                        <span class="material-symbols-rounded dfs-pdf-icon">picture_as_pdf</span>
                                    </div>
                                    <div class="dfs-info">
                                        <div class="dfs-name" id="dfs-name">selected-document.pdf</div>
                                        <div class="dfs-meta">
                                            <span class="dfs-size" id="dfs-size">0.00 MB</span>
                                            <span class="dfs-divider">&bull;</span>
                                            <span class="dfs-status"><span class="material-symbols-rounded dfs-check">check_circle</span> Ready to synthesize</span>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="dfs-remove-btn" id="dfs-remove-btn" title="Change file">
                                    <span class="material-symbols-rounded">swap_horiz</span>
                                    <span>Change</span>
                                </button>
                            </div>

                            <div class="dz-hint" id="dz-hint" style="display:none;"></div>
                        </label>
                    </div>

                    <div class="form-row form-row-compact">
                        <div class="form-label-row">
                            <label for="title-input" class="field-label">
                                <span class="material-symbols-rounded label-icon">edit_note</span>
                                <span>Exam Title</span>
                            </label>
                            <span class="label-badge-optional">Optional &mdash; auto-derived from filename</span>
                        </div>
                        <div class="input-wrap input-glow-wrap">
                            <span class="material-symbols-rounded input-leading-symbol">title</span>
                            <input type="text" id="title-input" name="title" placeholder="e.g. Chapter 4: Distributed Systems &amp; Raft" maxlength="120">
                        </div>
                    </div>

                    <div class="form-actions-row form-actions-compact">
                        <button type="submit" class="btn btn-primary btn-lg btn-synthesize" id="submit-btn" disabled>
                            <span class="material-symbols-rounded btn-synth-icon">auto_awesome</span>
                            <span class="btn-text">Synthesize Interactive Exam</span>
                        </button>
                    </div>
                </div>

                <!-- RIGHT: Engine Capabilities Accordion & Specs -->
                <div class="studio-col-info">
                    <div class="feature-accordion feature-accordion-compact" id="feature-accordion" role="region" aria-label="Engine Capabilities">
                        <!-- Item 1: Bloom's Taxonomy -->
                        <div class="feature-acc-item is-open" data-acc-id="acc-1">
                            <button type="button" class="feature-acc-trigger" aria-expanded="true" aria-controls="acc-panel-1" id="acc-btn-1">
                                <div class="feature-acc-trigger-left">
                                    <div class="fc-icon-wrap fc-indigo">
                                        <span class="material-symbols-rounded">psychology</span>
                                    </div>
                                    <div class="feature-acc-title-group">
                                        <h4 class="fc-title">~30 Questions / Chapter</h4>
                                        <span class="fc-tag fc-tag-indigo">Protocol 01</span>
                                    </div>
                                </div>
                                <div class="feature-acc-trigger-right">
                                    <span class="material-symbols-rounded feature-acc-chevron">expand_more</span>
                                </div>
                            </button>
                            <div class="feature-acc-panel" id="acc-panel-1" role="region" aria-labelledby="acc-btn-1">
                                <div class="feature-acc-content">
                                    <p class="fc-desc">Scenario, case analysis &amp; multi-tier logic questions testing deep cognitive application &mdash; not simple recall.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Item 2: Dual Testing Modes -->
                        <div class="feature-acc-item" data-acc-id="acc-2">
                            <button type="button" class="feature-acc-trigger" aria-expanded="false" aria-controls="acc-panel-2" id="acc-btn-2">
                                <div class="feature-acc-trigger-left">
                                    <div class="fc-icon-wrap fc-cyan">
                                        <span class="material-symbols-rounded">timer</span>
                                    </div>
                                    <div class="feature-acc-title-group">
                                        <h4 class="fc-title">Dual Testing Modes</h4>
                                        <span class="fc-tag fc-tag-cyan">Protocol 02</span>
                                    </div>
                                </div>
                                <div class="feature-acc-trigger-right">
                                    <span class="material-symbols-rounded feature-acc-chevron">expand_more</span>
                                </div>
                            </button>
                            <div class="feature-acc-panel" id="acc-panel-2" role="region" aria-labelledby="acc-btn-2">
                                <div class="feature-acc-content">
                                    <p class="fc-desc">High-stakes 10s blitz countdown or self-paced reflective study with immediate answer evaluation and scoring.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Item 3: Deep Rationale -->
                        <div class="feature-acc-item" data-acc-id="acc-3">
                            <button type="button" class="feature-acc-trigger" aria-expanded="false" aria-controls="acc-panel-3" id="acc-btn-3">
                                <div class="feature-acc-trigger-left">
                                    <div class="fc-icon-wrap fc-purple">
                                        <span class="material-symbols-rounded">auto_stories</span>
                                    </div>
                                    <div class="feature-acc-title-group">
                                        <h4 class="fc-title">Key &amp; Encyclopedia</h4>
                                        <span class="fc-tag fc-tag-purple">Protocol 03</span>
                                    </div>
                                </div>
                                <div class="feature-acc-trigger-right">
                                    <span class="material-symbols-rounded feature-acc-chevron">expand_more</span>
                                </div>
                            </button>
                            <div class="feature-acc-panel" id="acc-panel-3" role="region" aria-labelledby="acc-btn-3">
                                <div class="feature-acc-content">
                                    <p class="fc-desc">Instant evaluation, exhaustive rationales for every distractor, and an interactive domain concept glossary.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Item 4: AI Diagnostics & Remediation -->
                        <div class="feature-acc-item" data-acc-id="acc-4">
                            <button type="button" class="feature-acc-trigger" aria-expanded="false" aria-controls="acc-panel-4" id="acc-btn-4">
                                <div class="feature-acc-trigger-left">
                                    <div class="fc-icon-wrap fc-silver">
                                        <span class="material-symbols-rounded">insights</span>
                                    </div>
                                    <div class="feature-acc-title-group">
                                        <h4 class="fc-title">AI Diagnostics &amp; Remediation</h4>
                                        <span class="fc-tag fc-tag-silver">Protocol 04</span>
                                    </div>
                                </div>
                                <div class="feature-acc-trigger-right">
                                    <span class="material-symbols-rounded feature-acc-chevron">expand_more</span>
                                </div>
                            </button>
                            <div class="feature-acc-panel" id="acc-panel-4" role="region" aria-labelledby="acc-btn-4">
                                <div class="feature-acc-content">
                                    <p class="fc-desc">Automated post-exam cognitive audits pinpointing conceptual blindspots, distractor fallacies, and custom review drills.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Engine Telemetry Specs Bar -->
                    <div class="studio-specs-card">
                        <div class="studio-spec-item">
                            <span class="material-symbols-rounded spec-icon">speed</span>
                            <div class="spec-text">
                                <span class="spec-label">Speed</span>
                                <strong class="spec-val">&lt; 15s / Chapter</strong>
                            </div>
                        </div>
                        <div class="studio-spec-item">
                            <span class="material-symbols-rounded spec-icon">memory</span>
                            <div class="spec-text">
                                <span class="spec-label">Engine</span>
                                <strong class="spec-val">Groq LPU Array</strong>
                            </div>
                        </div>
                        <div class="studio-spec-item">
                            <span class="material-symbols-rounded spec-icon">lock</span>
                            <div class="spec-text">
                                <span class="spec-label">Privacy</span>
                                <strong class="spec-val">100% Client/Local</strong>
                            </div>
                        </div>
                    </div>
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
                        SECTION 02: EXAM REPOSITORY
                    </span>
                </div>
                <h2 class="section-title robot-typewrite-title" data-original="Your Generated Exams">Your Generated Exams</h2>
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
        <div class="table-glass-wrapper hud-corners">
            <span class="hud-corner hud-corner-tl" aria-hidden="true"></span>
            <span class="hud-corner hud-corner-tr" aria-hidden="true"></span>
            <span class="hud-corner hud-corner-bl" aria-hidden="true"></span>
            <span class="hud-corner hud-corner-br" aria-hidden="true"></span>
            <table class="exam-table">
                <thead>
                    <tr>
                        <th>Title &amp; Source</th>
                        <th>Status</th>
                        <th>Chapters</th>
                        <th>Questions</th>
                        <th>AI Tokens</th>
                        <th class="th-actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($exams as $idx => $ex): ?>
                    <tr data-id="<?= htmlspecialchars($ex['id']) ?>" class="<?= $idx >= 5 ? 'exam-row-extra is-collapsed' : '' ?>">
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
        <?php if (count($exams) > 5): ?>
            <div class="exams-toggle-wrap">
                <button type="button" class="exams-toggle-btn" id="exams-toggle-btn" aria-expanded="false" data-count="<?= count($exams) - 5 ?>">
                    <span class="exams-toggle-text">Show <?= count($exams) - 5 ?> More <?= (count($exams) - 5) === 1 ? 'Exam' : 'Exams' ?></span>
                    <span class="exams-toggle-badge">+<?= count($exams) - 5 ?></span>
                    <svg class="toggle-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- Backend status & AI Quota -->
    <section class="card" id="backend">
        <div class="backend-header-row">
            <div>
                <div class="section-tag-wrapper">
                    <span class="section-tag">
                        <span class="section-tag-dot"></span>
                        SECTION 03: CORE TELEMETRY
                    </span>
                </div>
                <h2 class="section-title robot-typewrite-title" data-original="System & AI Engine Status">System &amp; AI Engine Status</h2>
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
            <span>SynthExam</span>
            <span class="footer-version-tag">Local Studio v2.4</span>
        </div>
        <div class="footer-note">Runs locally on your environment via opencode &amp; Groq LPU &bull; Zero external data tracking &bull; <a href="#terms" id="footer-terms-link" class="footer-link">Terms &amp; Disclaimer</a></div>
    </div>
</footer>

<script>
const AUTO_POLL_JOB = <?= json_encode($activeJobId ?: null) ?>;
</script>
<script src="assets/site.js?v=<?= filemtime(__DIR__ . '/assets/site.js') ?>"></script>
</body>
</html>