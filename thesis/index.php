<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="dark">
  <meta name="theme-color" content="#09090b">
  <title>Thesis Title Maker — SynthExam</title>
  <meta name="description" content="Generate thesis titles from current research problems in your area. Location-aware discovery, multiple academic fields, and title quality analysis.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..900;1,14..32,300..900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
  <link rel="stylesheet" href="../assets/site.css?v=<?= filemtime(__DIR__ . '/../assets/site.css') ?>">
  <link rel="stylesheet" href="assets/thesis.css?v=<?= filemtime(__DIR__ . '/assets/thesis.css') ?>">
</head>
<body>

<!-- Ambient lighting — same treatment as the exam generator -->
<div class="ambient-glow ambient-top-left" aria-hidden="true"></div>
<div class="ambient-glow ambient-top-right" aria-hidden="true"></div>

<!-- ===================== NAVBAR ===================== -->
<header class="site-header" id="site-header">
  <div class="container header-inner">

    <a class="brand" href="../index.php" aria-label="SynthExam home">
      <div class="brand-logo-wrap">
        <svg class="brand-logo-svg" width="38" height="38" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="thBrandBg" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#27272a"/>
              <stop offset="50%" stop-color="#18181b"/>
              <stop offset="100%" stop-color="#09090b"/>
            </linearGradient>
            <linearGradient id="thBrandStroke" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#ffffff"/>
              <stop offset="50%" stop-color="#a1a1aa"/>
              <stop offset="100%" stop-color="#52525b"/>
            </linearGradient>
            <linearGradient id="thFacetTop" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#ffffff"/>
              <stop offset="100%" stop-color="#d4d4d8"/>
            </linearGradient>
            <linearGradient id="thFacetLeft" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#a1a1aa"/>
              <stop offset="100%" stop-color="#52525b"/>
            </linearGradient>
            <linearGradient id="thFacetRight" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#71717a"/>
              <stop offset="100%" stop-color="#27272a"/>
            </linearGradient>
          </defs>
          <rect x="1" y="1" width="38" height="38" rx="11" fill="url(#thBrandBg)" stroke="url(#thBrandStroke)" stroke-width="1.3"/>
          <path d="M20 9L29 14.2L20 19.4L11 14.2L20 9Z" fill="url(#thFacetTop)"/>
          <path d="M11 14.2L20 19.4V29.8L11 24.6V14.2Z" fill="url(#thFacetLeft)"/>
          <path d="M20 19.4L29 14.2V24.6L20 29.8V19.4Z" fill="url(#thFacetRight)"/>
          <path d="M20 9L20 19.4M11 14.2L20 19.4L29 14.2M20 19.4L20 29.8" stroke="rgba(255,255,255,0.45)" stroke-width="0.8" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M20 12L20.9 14.1L23 15L20.9 15.9L20 18L19.1 15.9L17 15L19.1 14.1L20 12Z" fill="#ffffff"/>
        </svg>
      </div>
      <div class="brand-text">
        <div class="brand-name-row">
          <span class="brand-name">Synth<span class="brand-name-gradient">Exam</span></span>
          <span class="brand-ai-badge">Thesis</span>
        </div>
        <span class="brand-sub">Research Title Studio</span>
      </div>
    </a>

    <nav class="site-nav" aria-label="Main navigation">
      <a class="nav-link" href="../index.php#upload">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <span>Exam Generator</span>
      </a>
      <a class="nav-link is-current" href="index.php" aria-current="page">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <span>Thesis Maker</span>
      </a>
      <a class="nav-link" href="../index.php#backend">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
        <span>System Health</span>
      </a>
    </nav>

    <div class="header-actions">
      <div class="live-users-badge" id="live-users-badge" title="Live active users on website">
        <span class="live-dot" aria-hidden="true"></span>
        <span class="live-users-count" id="live-users-count">1</span>
        <span class="live-users-label">online</span>
      </div>
    </div>

  </div>
</header>

<main class="container thesis-app">

  <!-- ===== STEP WIZARD ===== -->
  <nav class="step-wizard" aria-label="Steps">
    <div class="step-item active" id="wizard-step-1">
      <div class="step-bubble">1</div>
      <span class="step-label">Research profile</span>
    </div>
    <div class="step-item" id="wizard-step-2">
      <div class="step-bubble">2</div>
      <span class="step-label">Research problems</span>
    </div>
    <div class="step-item" id="wizard-step-3">
      <div class="step-bubble">3</div>
      <span class="step-label">Thesis titles</span>
    </div>
  </nav>

  <!-- ========================================================
       STEP 1 — Research Profile
       ======================================================== -->
  <section id="step-panel-1" class="step-panel active">
    <div class="card thesis-card">
      <div class="section-tag-wrapper">
        <span class="section-tag"><span class="section-tag-dot"></span> Thesis builder / research profile</span>
      </div>
      <h1 class="card-main-title">Set up your research profile</h1>
      <p class="card-desc">Tell us where you are and what you study. We look for current problems in that context, then build titles around the one you choose.</p>

      <div class="form-grid">

        <!-- Location -->
        <div class="form-group">
          <label class="form-label" for="input-location">Country or city</label>
          <div class="input-glow-wrap">
            <input
              type="text"
              id="input-location"
              class="form-input"
              placeholder="e.g. Philippines, Manila, Cebu..."
              list="location-suggestions"
              autocomplete="off"
            >
            <span class="material-symbols-rounded input-leading-symbol" aria-hidden="true">location_on</span>
          </div>
          <datalist id="location-suggestions">
            <option value="Philippines">
            <option value="Manila, Philippines">
            <option value="Cebu, Philippines">
            <option value="Davao, Philippines">
            <option value="Indonesia">
            <option value="Malaysia">
            <option value="Thailand">
            <option value="Vietnam">
            <option value="United States">
            <option value="United Kingdom">
            <option value="India">
            <option value="Nigeria">
            <option value="Bangladesh">
          </datalist>
        </div>

        <!-- Field of Study -->
        <div class="form-group">
          <label class="form-label" for="select-field">Academic field</label>
          <select id="select-field" class="form-select">
            <option value="">— Select a field —</option>
            <optgroup label="Technology">
              <option value="Computer Science">Computer Science / IT</option>
              <option value="Software Engineering">Software Engineering</option>
              <option value="Information Systems">Information Systems</option>
              <option value="Data Science / AI">Data Science / AI / Machine Learning</option>
              <option value="Cybersecurity">Cybersecurity</option>
            </optgroup>
            <optgroup label="Health &amp; Medicine">
              <option value="Nursing">Nursing</option>
              <option value="Medicine">Medicine / Medical Technology</option>
              <option value="Public Health">Public Health</option>
              <option value="Pharmacy">Pharmacy</option>
            </optgroup>
            <optgroup label="Education">
              <option value="Education">Education / Teaching</option>
              <option value="Educational Technology">Educational Technology</option>
              <option value="Special Education">Special Education</option>
            </optgroup>
            <optgroup label="Business &amp; Management">
              <option value="Business Administration">Business Administration</option>
              <option value="Accounting">Accounting / Finance</option>
              <option value="Marketing">Marketing</option>
              <option value="Human Resource Management">Human Resource Management</option>
            </optgroup>
            <optgroup label="Engineering">
              <option value="Civil Engineering">Civil Engineering</option>
              <option value="Electrical Engineering">Electrical Engineering</option>
              <option value="Mechanical Engineering">Mechanical Engineering</option>
              <option value="Environmental Engineering">Environmental Engineering</option>
            </optgroup>
            <optgroup label="Social Sciences">
              <option value="Psychology">Psychology</option>
              <option value="Sociology">Sociology</option>
              <option value="Political Science">Political Science</option>
              <option value="Social Work">Social Work</option>
              <option value="Communication">Communication / Media Studies</option>
            </optgroup>
            <optgroup label="Natural Sciences">
              <option value="Biology">Biology / Biological Sciences</option>
              <option value="Environmental Science">Environmental Science</option>
              <option value="Agriculture">Agriculture</option>
              <option value="Chemistry">Chemistry</option>
            </optgroup>
          </select>
        </div>

        <!-- Academic Level -->
        <div class="form-group full-width">
          <span class="form-label">Academic level</span>
          <div class="seg-group">
            <button type="button" class="seg-btn active" data-level="undergraduate"><span class="material-symbols-rounded" aria-hidden="true">school</span> Undergraduate</button>
            <button type="button" class="seg-btn" data-level="masters"><span class="material-symbols-rounded" aria-hidden="true">menu_book</span> Master's thesis</button>
            <button type="button" class="seg-btn" data-level="phd"><span class="material-symbols-rounded" aria-hidden="true">science</span> Ph.D. dissertation</button>
          </div>
        </div>

        <!-- Framework Preference -->
        <div class="form-group full-width">
          <span class="form-label">Title framework</span>
          <div class="seg-group">
            <button type="button" class="seg-btn" data-framework="correlational">Correlational</button>
            <button type="button" class="seg-btn" data-framework="comparative">Comparative</button>
            <button type="button" class="seg-btn" data-framework="developmental">Developmental</button>
            <button type="button" class="seg-btn" data-framework="descriptive">Descriptive</button>
            <button type="button" class="seg-btn" data-framework="experimental">Experimental</button>
            <button type="button" class="seg-btn active" data-framework="mixed"><span class="material-symbols-rounded" aria-hidden="true">auto_awesome</span> Mixed (recommended)</button>
          </div>
        </div>

        <!-- Title Count -->
        <div class="form-group">
          <label class="form-label" for="slider-count">Number of titles</label>
          <div class="range-row">
            <input type="range" id="slider-count" class="range-slider" min="3" max="6" value="6">
            <span id="slider-count-val" class="range-val">6</span>
          </div>
        </div>

        <!-- Keywords -->
        <div class="form-group">
          <label class="form-label" for="keyword-input">Seed keywords <span class="hint">optional · Enter or comma to add</span></label>
          <div class="chip-input-wrap" id="chip-wrap" onclick="document.getElementById('keyword-input').focus()">
            <input type="text" id="keyword-input" class="chip-text-input" placeholder="e.g. mobile app, elderly, rural..." maxlength="30">
          </div>
        </div>

      </div>

      <div class="btn-row">
        <button type="button" id="btn-next-1" class="btn-primary">
          Find research problems
          <span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span>
        </button>
        <span class="hint">Searches current issues in your location</span>
      </div>
    </div>
  </section>

  <!-- ========================================================
       STEP 2 — Research Problem Selection
       ======================================================== -->
  <section id="step-panel-2" class="step-panel">
    <div class="card thesis-card">
      <div class="section-tag-wrapper">
        <span class="section-tag"><span class="section-tag-dot"></span> Research signals / choose a problem</span>
      </div>
      <div class="section-header-row">
        <div>
          <h2 class="section-title">Current research problems</h2>
          <p class="section-sub">Choose the problem you want to work on. Your titles will be built around it.</p>
        </div>
        <div class="results-actions">
          <div class="seg-group" style="padding:3px; gap:3px;">
            <button type="button" class="seg-btn active" data-search-mode="ai" title="Use AI knowledge base"><span class="material-symbols-rounded" aria-hidden="true">smart_toy</span> AI</button>
            <button type="button" class="seg-btn" data-search-mode="news" title="Pull from live news (requires GNews API key)"><span class="material-symbols-rounded" aria-hidden="true">newspaper</span> Live news</button>
          </div>
          <button type="button" id="btn-refresh-problems" class="btn-secondary" style="padding:8px 14px; font-size:13px;">
            <span class="material-symbols-rounded" aria-hidden="true">refresh</span> Refresh
          </button>
        </div>
      </div>

      <!-- Selected problem bar -->
      <div id="selected-problem-bar" class="selected-problem-bar" style="display:none;">
        <span class="material-symbols-rounded" aria-hidden="true">check_circle</span>
        <span id="selected-problem-text" class="selected-problem-text"></span>
        <span class="selected-problem-state">Selected</span>
      </div>

      <div id="problems-spinner" style="display:none; justify-content:center; align-items:center; gap:10px; padding:16px 0;">
        <div class="spinner"></div>
        <span style="font-size:13px; color:var(--text-secondary);">Searching for problems in your location…</span>
      </div>

      <div class="problems-grid" id="problems-grid">
        <!-- Populated by JS -->
      </div>

      <div class="btn-row">
        <button type="button" id="btn-back-2" class="btn-secondary"><span class="material-symbols-rounded" aria-hidden="true">arrow_back</span> Back</button>
        <button type="button" id="btn-next-2" class="btn-primary">
          <span>Generate thesis titles</span>
          <span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span>
        </button>
      </div>
    </div>
  </section>

  <!-- ========================================================
       STEP 3 — Thesis Titles Results
       ======================================================== -->
  <section id="step-panel-3" class="step-panel">
    <div class="card thesis-card">
      <div class="section-tag-wrapper">
        <span class="section-tag"><span class="section-tag-dot"></span> Thesis builder / generated titles</span>
      </div>
      <div class="results-header">
        <div>
          <h2 class="section-title">Your thesis title suggestions</h2>
          <div id="results-meta" class="results-meta">Generating…</div>
        </div>
        <div class="results-actions">
          <button type="button" id="btn-back-3" class="btn-secondary"><span class="material-symbols-rounded" aria-hidden="true">arrow_back</span> Back</button>
          <button type="button" id="btn-regen" class="btn-secondary" title="Generate a new set"><span class="material-symbols-rounded" aria-hidden="true">refresh</span> Regenerate</button>
          <button type="button" id="btn-export-all" class="btn-primary btn-gold"><span class="material-symbols-rounded" aria-hidden="true">download</span> Export all</button>
        </div>
      </div>

      <div id="titles-spinner" style="display:none; justify-content:center; align-items:center; gap:10px; padding:20px 0;">
        <div class="spinner"></div>
        <span style="font-size:13px; color:var(--text-secondary);">Generating title suggestions with AI…</span>
      </div>

      <div class="titles-list" id="titles-list">
        <!-- Populated by JS -->
      </div>

      <!-- Custom Title Analyzer -->
      <div class="analyze-panel">
        <div class="analyze-panel-title"><span class="material-symbols-rounded" aria-hidden="true">analytics</span> Analyze a title you already have</div>
        <div class="analyze-panel-desc">Paste a thesis title to get a quality report and concrete improvement suggestions.</div>
        <div class="analyze-row">
          <input type="text" id="analyze-custom-input" class="form-input" placeholder="Paste your thesis title here…">
          <button type="button" id="btn-analyze-custom" class="btn-primary" style="white-space:nowrap;">Analyze title</button>
        </div>
      </div>

      <!-- Saved Favorites -->
      <div class="favorites-panel" id="favorites-panel">
        <div class="favorites-header">
          <div class="favorites-title"><span class="material-symbols-rounded" aria-hidden="true">star</span> Saved favorites</div>
          <button type="button" id="btn-export-favorites" class="btn-secondary" style="font-size:12px; padding:6px 14px;"><span class="material-symbols-rounded" aria-hidden="true">download</span> Export</button>
        </div>
        <div class="favorites-list" id="favorites-list">
          <div class="fav-empty">No favorites saved yet. Star a title to save it here.</div>
        </div>
      </div>
    </div>
  </section>

</main>

<!-- ===== ANALYZE MODAL ===== -->
<div class="modal-overlay" id="analyze-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title-label">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title" id="modal-title-label">Title quality report</div>
      <button class="modal-close" onclick="closeAnalyzeModal()" aria-label="Close"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
    </div>
    <div class="modal-body" id="analyze-modal-content">
      <!-- Populated by JS -->
    </div>
  </div>
</div>

<script src="assets/thesis.js"></script>
<script src="../assets/presence.js"></script>
<script>
  // Match the exam generator's sticky-header scroll treatment.
  (function () {
    const header = document.getElementById('site-header');
    const update = () => header.classList.toggle('scrolled', window.scrollY > 12);
    update();
    window.addEventListener('scroll', update, { passive: true });
  })();
</script>
</body>
</html>
