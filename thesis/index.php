<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Thesis Title Maker — AI-Powered Research Topic Generator</title>
  <meta name="description" content="Generate compelling thesis titles based on current research problems in your country or city. Features location-based problem discovery, multiple academic fields, and title quality analysis.">
  <link rel="stylesheet" href="../assets/site.css">
  <link rel="stylesheet" href="assets/thesis.css">
</head>
<body>

<div class="thesis-app">

  <!-- ===== HEADER ===== -->
  <header class="thesis-header">
    <div class="thesis-brand">
      <div class="brand-icon-wrap">🎓</div>
      <div>
        <div class="brand-name">Thesis Title Maker</div>
        <div class="brand-tagline">AI-powered research topic discovery · Location-aware · Multi-field</div>
      </div>
    </div>
    <div style="display:flex; align-items:center; gap:12px;">
      <div class="live-users-badge" id="live-users-badge" title="Live active users on website">
        <span class="live-dot" aria-hidden="true"></span>
        <span class="live-users-count" id="live-users-count">1</span>
        <span class="live-users-label">online</span>
      </div>
      <a href="../index.php" class="back-link">← Back to Exam Generator</a>
    </div>
  </header>

  <!-- ===== STEP WIZARD ===== -->
  <nav class="step-wizard" aria-label="Steps">
    <div class="step-item active" id="wizard-step-1">
      <div class="step-bubble">1</div>
      <span class="step-label">Your Details</span>
    </div>
    <div class="step-item" id="wizard-step-2">
      <div class="step-bubble">2</div>
      <span class="step-label">Research Problems</span>
    </div>
    <div class="step-item" id="wizard-step-3">
      <div class="step-bubble">3</div>
      <span class="step-label">Thesis Titles</span>
    </div>
  </nav>

  <!-- ========================================================
       STEP 1 — Research Profile
       ======================================================== -->
  <section id="step-panel-1" class="step-panel active">
    <h2 class="section-title">Set Up Your Research Profile</h2>
    <p class="section-sub">Tell us your location and field so we can find the most relevant, current problems to base your thesis on.</p>

    <div class="form-grid">

      <!-- Location -->
      <div class="form-group">
        <label class="form-label" for="input-location">Country or City</label>
        <input
          type="text"
          id="input-location"
          class="form-input"
          placeholder="e.g. Philippines, Manila, Cebu, Indonesia..."
          list="location-suggestions"
          autocomplete="off"
        >
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
        <label class="form-label" for="select-field">Academic Field</label>
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
        <label class="form-label">Academic Level</label>
        <div class="seg-group">
          <button type="button" class="seg-btn active" data-level="undergraduate">🎓 Undergraduate (Bachelor's)</button>
          <button type="button" class="seg-btn" data-level="masters">📘 Master's Thesis</button>
          <button type="button" class="seg-btn" data-level="phd">🔬 Ph.D. Dissertation</button>
        </div>
      </div>

      <!-- Framework Preference -->
      <div class="form-group full-width">
        <label class="form-label">Title Framework Preference</label>
        <div class="seg-group" style="flex-wrap:wrap;">
          <button type="button" class="seg-btn" data-framework="correlational">Correlational</button>
          <button type="button" class="seg-btn" data-framework="comparative">Comparative</button>
          <button type="button" class="seg-btn" data-framework="developmental">Developmental</button>
          <button type="button" class="seg-btn" data-framework="descriptive">Descriptive</button>
          <button type="button" class="seg-btn" data-framework="experimental">Experimental</button>
          <button type="button" class="seg-btn active" data-framework="mixed">✦ Mixed (Recommended)</button>
        </div>
      </div>

      <!-- Title Count -->
      <div class="form-group">
        <label class="form-label">Number of Titles to Generate</label>
        <div class="range-row">
          <input type="range" id="slider-count" class="range-slider" min="3" max="6" value="6">
          <span id="slider-count-val" class="range-val">6</span>
        </div>
      </div>

      <!-- Keywords -->
      <div class="form-group">
        <label class="form-label">Seed Keywords <span style="color:var(--text-muted);font-weight:400;font-size:11px;">(optional, press Enter or comma)</span></label>
        <div class="chip-input-wrap" id="chip-wrap" onclick="document.getElementById('keyword-input').focus()">
          <input type="text" id="keyword-input" class="chip-text-input" placeholder="e.g. mobile app, elderly, rural..." maxlength="30">
        </div>
      </div>

    </div>

    <div class="btn-row">
      <button type="button" id="btn-next-1" class="btn-primary">
        Find Research Problems <span style="font-size:16px;">→</span>
      </button>
      <span style="font-size:12px; color:var(--text-muted);">AI will scan current issues in your location</span>
    </div>
  </section>

  <!-- ========================================================
       STEP 2 — Research Problem Selection
       ======================================================== -->
  <section id="step-panel-2" class="step-panel">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:8px;">
      <div>
        <h2 class="section-title">Current Research Problems</h2>
        <p class="section-sub">Select the problem that resonates most with you — your thesis titles will be built around it.</p>
      </div>
      <div style="display:flex; gap:8px; flex-wrap:wrap; padding-top:4px;">
        <div class="seg-group" style="padding:3px; gap:3px;">
          <button type="button" class="seg-btn active" data-search-mode="ai" title="Use AI knowledge base">🤖 AI</button>
          <button type="button" class="seg-btn" data-search-mode="news" title="Pull from live news (requires GNews API key)">📰 Live News</button>
        </div>
        <button type="button" id="btn-refresh-problems" class="btn-secondary" style="padding:8px 16px; font-size:13px;">
          🔄 Refresh
        </button>
      </div>
    </div>

    <!-- Selected problem bar -->
    <div id="selected-problem-bar" style="display:none; align-items:center; gap:10px; background:rgba(124,92,191,0.1); border:1px solid rgba(124,92,191,0.3); border-radius:10px; padding:11px 16px; margin-bottom:20px;">
      <span style="color:var(--accent-purple-light); font-size:14px;">✓</span>
      <span id="selected-problem-text" style="font-size:13.5px; font-weight:600; color:var(--text-primary); flex:1;"></span>
      <span style="font-size:12px; color:var(--text-muted);">Selected</span>
    </div>

    <div id="problems-spinner" style="display:none; justify-content:center; align-items:center; gap:10px; padding:12px 0; margin-bottom:8px;">
      <div class="spinner"></div>
      <span style="font-size:13px; color:var(--text-secondary);">Searching for problems in your location…</span>
    </div>

    <div class="problems-grid" id="problems-grid">
      <!-- Populated by JS -->
    </div>

    <div class="btn-row">
      <button type="button" id="btn-back-2" class="btn-secondary">← Back</button>
      <button type="button" id="btn-next-2" class="btn-primary">
        <span>Generate Thesis Titles</span>
        <span style="font-size:16px;">→</span>
      </button>
    </div>
  </section>

  <!-- ========================================================
       STEP 3 — Thesis Titles Results
       ======================================================== -->
  <section id="step-panel-3" class="step-panel">
    <div class="results-header">
      <div>
        <h2 class="section-title">Your Thesis Title Suggestions</h2>
        <div id="results-meta" class="results-meta">Generating…</div>
      </div>
      <div class="results-actions">
        <button type="button" id="btn-back-3" class="btn-secondary">← Back</button>
        <button type="button" id="btn-regen" class="btn-secondary" title="Generate a new set">🔄 Regenerate</button>
        <button type="button" id="btn-export-all" class="btn-primary btn-gold">⬇ Export All</button>
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
      <div style="font-family:'Outfit',sans-serif; font-size:16px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">🔍 Analyze Your Own Title</div>
      <div style="font-size:13px; color:var(--text-secondary); margin-bottom:16px;">Already have a thesis title? Paste it below to get a detailed quality report and improvement suggestions.</div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <input type="text" id="analyze-custom-input" class="form-input" placeholder="Paste your thesis title here…" style="flex:1; min-width:200px;">
        <button type="button" id="btn-analyze-custom" class="btn-primary" style="white-space:nowrap;">Analyze Title</button>
      </div>
    </div>

    <!-- Saved Favorites -->
    <div class="favorites-panel" id="favorites-panel">
      <div class="favorites-header">
        <div class="favorites-title">⭐ Saved Favorites</div>
        <button type="button" id="btn-export-favorites" class="btn-secondary" style="font-size:12px; padding:6px 14px;">⬇ Export</button>
      </div>
      <div class="favorites-list" id="favorites-list">
        <div class="fav-empty">No favorites saved yet. Star a title to save it here.</div>
      </div>
    </div>
  </section>

</div><!-- /.thesis-app -->

<!-- ===== ANALYZE MODAL ===== -->
<div class="modal-overlay" id="analyze-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title-label">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title" id="modal-title-label">Title Quality Report</div>
      <button class="modal-close" onclick="closeAnalyzeModal()" aria-label="Close">✕</button>
    </div>
    <div class="modal-body" id="analyze-modal-content">
      <!-- Populated by JS -->
    </div>
  </div>
</div>

<script src="assets/thesis.js"></script>
<script src="../assets/presence.js"></script>
</body>
</html>
