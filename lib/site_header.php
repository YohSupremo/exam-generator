<?php
declare(strict_types=1);

/**
 * SynthExam — shared site navbar.
 *
 * Included by both the exam generator (index.php) and the thesis maker
 * (thesis/index.php) so the brand, links, badges, settings panel and mobile
 * drawer stay identical across the app.
 *
 * Caller may set:
 *   $navBase       '' (root) or '../' (subdirectory)
 *   $navActive     'home' | 'exams' | 'html' | 'backend' | 'thesis'
 *   $navBrandBadge / $navBrandSub
 *   $navRemTokens / $navLimTokens / $navQueueTotal
 */
require_once __DIR__ . '/config.php';

$navBase       = $navBase ?? '';
$navActive     = $navActive ?? '';
$navBrandBadge = $navBrandBadge ?? 'AI 2.0';
$navBrandSub   = $navBrandSub ?? 'Cognitive Assessment Engine';

if (!isset($navRemTokens, $navLimTokens)) {
    $quotaFile = DATA_DIR . '/ai_quota.json';
    $quota = is_file($quotaFile)
        ? (json_decode((string)@file_get_contents($quotaFile), true) ?: [])
        : [];
    $navRemTokens = number_format((int)($quota['ratelimit']['remaining_tokens'] ?? 8000));
    $navLimTokens = number_format((int)($quota['ratelimit']['limit_tokens'] ?? 8000));
}
if (!isset($navQueueTotal)) {
    $navQueueTotal = 0;
    try {
        require_once __DIR__ . '/db.php';
        $navQueueTotal = (int)db()->query(
            "SELECT COUNT(*) FROM exams WHERE status IN ('queued','running')"
        )->fetchColumn();
    } catch (Throwable $e) {}
}
?>
<header class="site-header" id="site-header">
    <div class="container header-inner">

        <!-- Brand -->
        <a class="brand" href="<?= $navBase ?>index.php" aria-label="SynthExam home">
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
                    <span class="brand-ai-badge"><?= $navBrandBadge ?></span>
                </div>
                <span class="brand-sub"><?= $navBrandSub ?></span>
            </div>
        </a>

        <!-- Desktop nav links -->
        <nav class="site-nav" aria-label="Main navigation">
            <a class="nav-link<?= $navActive === 'home' ? ' is-current' : '' ?>" href="<?= $navBase ?>index.php#upload">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span>New Exam</span>
            </a>
            <a class="nav-link<?= $navActive === 'exams' ? ' is-current' : '' ?>" href="<?= $navBase ?>index.php#exams">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <span>My Exams</span>
            </a>
            <?php if (!HTML_EXAM_LOCKED): ?>
            <a class="nav-link<?= $navActive === 'html' ? ' is-current' : '' ?>" href="<?= $navBase ?>html_exam.php">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                <span>HTML Exam</span>
            </a>
            <?php endif; ?>
            <a class="nav-link<?= $navActive === 'backend' ? ' is-current' : '' ?>" href="<?= $navBase ?>index.php#backend">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                <span>System Health</span>
            </a>
            <a class="nav-link<?= $navActive === 'thesis' ? ' is-current' : '' ?>" href="<?= $navBase ?>thesis/index.php" title="Thesis Title Maker">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                <span>Thesis Maker</span>
            </a>
        </nav>

            <!-- Right side -->
            <div class="header-actions">
                <!-- Live Online Users Badge -->
                <div class="live-users-badge" id="live-users-badge" title="Live active users on website">
                    <span class="live-dot" aria-hidden="true"></span>
                    <span class="live-users-count" id="live-users-count">1</span>
                    <span class="live-users-label">online</span>
                </div>

                <!-- Professional Single-Line Header Token Badge: Remaining / Limit -->
                <div class="header-token-badge" id="header-token-badge" title="AI Rate Limit: <?= $navRemTokens ?> / <?= $navLimTokens ?> TPM">
                    <span class="htb-icon-wrap" aria-hidden="true">
                        <svg class="htb-bolt" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                    </span>
                    <span class="htb-remaining" id="htb-remaining"><?= $navRemTokens ?></span>
                    <span class="htb-sep">/</span>
                    <span class="htb-limit" id="htb-limit"><?= $navLimTokens ?></span>
                    <span class="htb-unit">Tokens</span>
                    <span class="htb-dot online" id="htb-dot" title="Engine online"></span>
                </div>

                <!-- Live Queue Counter Badge -->
                <div class="queue-badge<?= $navQueueTotal > 0 ? ' queue-badge--active' : ' queue-badge--idle' ?>" id="queue-badge"
                     title="<?= $navQueueTotal > 0 ? $navQueueTotal . ' job(s) in queue' : 'No jobs in queue' ?>">
                    <span class="queue-badge-icon" aria-hidden="true">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="8" y1="6" x2="21" y2="6"/>
                            <line x1="8" y1="12" x2="21" y2="12"/>
                            <line x1="8" y1="18" x2="21" y2="18"/>
                            <line x1="3" y1="6" x2="3.01" y2="6"/>
                            <line x1="3" y1="12" x2="3.01" y2="12"/>
                            <line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg>
                    </span>
                    <span class="queue-badge-label">Queue</span>
                    <span class="queue-badge-count" id="queue-badge-count"><?= $navQueueTotal ?></span>
                    <span class="queue-badge-pulse" id="queue-badge-pulse" aria-hidden="true"></span>
                </div>

                <!-- Settings Gear Button -->
                <button type="button" class="settings-gear-btn" id="settings-gear-btn"
                        aria-label="Open settings" title="Settings"
                        aria-haspopup="dialog" aria-expanded="false" aria-controls="settings-panel">
                    <svg class="settings-gear-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33
                                 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33
                                 l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2
                                 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83
                                 l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0
                                 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4
                                 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                </button>

                <div class="backend-status" id="backend-status" style="display:none;"></div>
                <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false" aria-controls="mobile-nav">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>

        </div>
    </div>
</header>

<!-- ===================== SETTINGS PANEL ===================== -->
<div id="settings-panel" class="settings-panel" role="dialog" aria-modal="false" aria-label="Settings" hidden>
    <div class="settings-panel-inner">

        <!-- Header -->
        <div class="settings-panel-header">
            <div class="settings-panel-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0
                             0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65
                             1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65
                             1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82
                             l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2
                             2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83
                             2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65
                             1.65 0 0 0-1.51 1z"/>
                </svg>
                Settings
            </div>
            <button class="settings-panel-close" id="settings-panel-close" aria-label="Close settings">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <!-- Sound FX -->
        <div class="settings-section">
            <div class="settings-section-label">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
                </svg>
                Sound Effects
            </div>
            <button type="button" class="settings-sfx-toggle robot-sfx-toggle" id="settings-sfx-btn"
                    aria-label="Toggle sound effects">
                <span class="material-symbols-rounded sfx-btn-icon" aria-hidden="true">volume_up</span>
                <span class="settings-sfx-label">
                    <span class="sfx-btn-text">SFX: ON</span>
                    <span class="settings-sfx-sub">Tactile UI audio feedback</span>
                </span>
                <span class="settings-toggle-track" aria-hidden="true">
                    <span class="settings-toggle-thumb"></span>
                </span>
            </button>
        </div>

        <!-- Graphics Quality -->
        <div class="settings-section">
            <div class="settings-section-label">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/>
                    <line x1="12" y1="17" x2="12" y2="21"/>
                </svg>
                Graphics Quality
                <span class="settings-auto-badge" id="gfx-auto-badge">AUTO</span>
            </div>
            <p class="settings-section-sub">Controls cursor trail, background effects &amp; animations.</p>
            <div class="settings-gfx-grid" id="gfx-preset-grid" role="radiogroup" aria-label="Graphics quality preset">
                <button type="button" class="gfx-preset-btn" data-preset="ultra" role="radio" aria-checked="false" id="gfx-ultra">
                    <span class="gfx-preset-icon" aria-hidden="true">✦</span>
                    <span class="gfx-preset-name">Ultra</span>
                    <span class="gfx-preset-desc">Full effects + glow</span>
                </button>
                <button type="button" class="gfx-preset-btn" data-preset="high" role="radio" aria-checked="false" id="gfx-high">
                    <span class="gfx-preset-icon" aria-hidden="true">▲</span>
                    <span class="gfx-preset-name">High</span>
                    <span class="gfx-preset-desc">Trail, no shadows</span>
                </button>
                <button type="button" class="gfx-preset-btn" data-preset="lite" role="radio" aria-checked="false" id="gfx-lite">
                    <span class="gfx-preset-icon" aria-hidden="true">◈</span>
                    <span class="gfx-preset-name">Lite</span>
                    <span class="gfx-preset-desc">Minimal animations</span>
                </button>
                <button type="button" class="gfx-preset-btn" data-preset="off" role="radio" aria-checked="false" id="gfx-off">
                    <span class="gfx-preset-icon" aria-hidden="true">◻</span>
                    <span class="gfx-preset-name">Off</span>
                    <span class="gfx-preset-desc">No effects at all</span>
                </button>
            </div>
            <div class="settings-gfx-detail" id="gfx-detail-text"></div>
        </div>

        <!-- Device info row -->
        <div class="settings-device-row" id="settings-device-row">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/>
                <line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <span id="settings-device-info">Detecting device…</span>
        </div>

    </div>
</div>
<!-- Settings backdrop -->
<div id="settings-backdrop" class="settings-backdrop" hidden></div>


<!-- Mobile nav drawer -->
<div class="mobile-nav" id="mobile-nav" role="navigation" aria-label="Mobile navigation">
    <a class="mobile-nav-link" href="<?= $navBase ?>index.php#upload" id="mnl-upload">
        <span class="mnl-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
        </span>
        <div>
            <div style="font-weight:600;">New Exam</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:1px;">Upload a PDF to generate questions</div>
        </div>
    </a>
    <a class="mobile-nav-link" href="<?= $navBase ?>index.php#exams" id="mnl-exams">
        <span class="mnl-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
        </span>
        <div>
            <div style="font-weight:600;">My Exams</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:1px;">View and manage your generated exams</div>
        </div>
    </a>
    <?php if (!HTML_EXAM_LOCKED): ?>
    <a class="mobile-nav-link" href="<?= $navBase ?>html_exam.php" id="mnl-html">
        <span class="mnl-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
        </span>
        <div>
            <div style="font-weight:600;">HTML Exam</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:1px;">Upload a self-contained HTML exam to take</div>
        </div>
    </a>
    <?php endif; ?>
    <div class="mobile-nav-divider"></div>
    <a class="mobile-nav-link" href="<?= $navBase ?>index.php#backend" id="mnl-backend">
        <span class="mnl-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
        </span>
        <div>
            <div style="font-weight:600;">System Status</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:1px;">Check backend dependencies &amp; AI limits</div>
        </div>
    </a>
</div>
