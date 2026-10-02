/**
 * thesis.js — Thesis Title Maker Frontend Logic
 * All interactions, API calls, and UI state management
 */
'use strict';

/* ===== STATE ===== */
const State = {
  currentStep: 1,
  location: '',
  field: '',
  level: 'undergraduate',
  framework: 'mixed',
  titleCount: 8,
  keywords: [],
  selectedProblem: null,
  problems: [],
  titles: [],
  favorites: JSON.parse(localStorage.getItem('thesis_favorites') || '[]'),
  analyzeModal: { open: false, titleId: null },
};

/* ===== DOM SHORTCUTS ===== */
const $ = (id) => document.getElementById(id);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

/* ===== TOAST SYSTEM ===== */
const toastStack = (() => {
  let el = document.querySelector('.toast-stack');
  if (!el) {
    el = document.createElement('div');
    el.className = 'toast-stack';
    document.body.appendChild(el);
  }
  return el;
})();

function showToast(msg, type = 'info', duration = 3200) {
  const icons = { success: '✓', error: '✕', info: 'ℹ', gold: '⭐' };
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.innerHTML = `<span>${icons[type] || 'ℹ'}</span><span>${msg}</span>`;
  toastStack.appendChild(t);
  setTimeout(() => {
    t.classList.add('hide');
    setTimeout(() => t.remove(), 350);
  }, duration);
}

/* ===== STEP NAVIGATION ===== */
function goToStep(n, validate = true) {
  if (n === 2 && validate) {
    if (!State.location.trim()) { showToast('Please enter a location (country or city).', 'error'); return; }
    if (!State.field.trim()) { showToast('Please select an academic field.', 'error'); return; }
  }
  if (n === 3 && validate) {
    if (!State.selectedProblem) { showToast('Please select a research problem first.', 'error'); return; }
  }

  State.currentStep = n;

  // Update wizard UI
  $$('.step-item').forEach((item, i) => {
    const stepNum = i + 1;
    item.classList.toggle('active',    stepNum === n);
    item.classList.toggle('completed', stepNum < n);
  });

  // Show/hide panels
  $$('.step-panel').forEach(p => p.classList.remove('active'));
  const panel = $(`step-panel-${n}`);
  if (panel) panel.classList.add('active');

  window.scrollTo({ top: 0, behavior: 'smooth' });

  // Auto-trigger fetches
  if (n === 2 && State.problems.length === 0) fetchProblems();
  if (n === 3 && State.titles.length === 0) fetchTitles();
}

/* ===== STEP 1: FORM BINDING ===== */
function initStep1() {
  // Location input
  const locInput = $('input-location');
  locInput.addEventListener('input', () => { State.location = locInput.value; });

  // Field select
  const fieldSel = $('select-field');
  fieldSel.addEventListener('change', () => { State.field = fieldSel.value; });

  // Level segmented control
  $$('[data-level]').forEach(btn => {
    btn.addEventListener('click', () => {
      $$('[data-level]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      State.level = btn.dataset.level;
    });
  });

  // Framework segmented control
  $$('[data-framework]').forEach(btn => {
    btn.addEventListener('click', () => {
      $$('[data-framework]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      State.framework = btn.dataset.framework;
    });
  });

  // Title count slider
  const slider = $('slider-count');
  const sliderVal = $('slider-count-val');
  slider.addEventListener('input', () => {
    State.titleCount = parseInt(slider.value);
    sliderVal.textContent = State.titleCount;
  });

  // Keywords chip input
  const chipInput = $('keyword-input');
  chipInput.addEventListener('keydown', e => {
    if ((e.key === 'Enter' || e.key === ',') && chipInput.value.trim()) {
      e.preventDefault();
      addKeyword(chipInput.value.trim().replace(',', ''));
      chipInput.value = '';
    }
    if (e.key === 'Backspace' && chipInput.value === '' && State.keywords.length > 0) {
      removeKeyword(State.keywords[State.keywords.length - 1]);
    }
  });

  // Next button
  $('btn-next-1').addEventListener('click', () => {
    State.location = locInput.value.trim();
    State.field    = fieldSel.value;
    if (!State.location) { showToast('Enter a country or city.', 'error'); return; }
    if (!State.field) { showToast('Select an academic field.', 'error'); return; }
    goToStep(2, false);
  });
}

function addKeyword(word) {
  if (!word || State.keywords.includes(word) || State.keywords.length >= 6) return;
  State.keywords.push(word);
  renderKeywords();
}

function removeKeyword(word) {
  State.keywords = State.keywords.filter(k => k !== word);
  renderKeywords();
}

function renderKeywords() {
  const wrap = $('chip-wrap');
  const input = $('keyword-input');
  // Clear existing chips (keep the input)
  $$('.keyword-chip', wrap).forEach(c => c.remove());
  State.keywords.forEach(kw => {
    const chip = document.createElement('span');
    chip.className = 'keyword-chip';
    chip.innerHTML = `${kw}<span class="chip-remove" data-kw="${kw}">×</span>`;
    chip.querySelector('.chip-remove').addEventListener('click', () => removeKeyword(kw));
    wrap.insertBefore(chip, input);
  });
}

/* ===== STEP 2: FETCH PROBLEMS ===== */
async function fetchProblems(forceMode) {
  const grid      = $('problems-grid');
  const spinner   = $('problems-spinner');
  const modeEl    = document.querySelector('[data-search-mode].active');
  const mode      = forceMode || modeEl?.dataset.searchMode || 'ai';

  // Show loading skeletons
  grid.innerHTML = Array(6).fill(`
    <div class="problem-card" style="pointer-events:none;">
      <div class="skeleton skeleton-line" style="width:70%; height:16px; margin-bottom:12px;"></div>
      <div class="skeleton skeleton-line" style="width:100%; height:12px;"></div>
      <div class="skeleton skeleton-line" style="width:85%;"></div>
      <div class="skeleton skeleton-line" style="width:50%; margin-top:8px;"></div>
    </div>
  `).join('');
  if (spinner) spinner.style.display = 'flex';

  try {
    const res = await fetch('../thesis/api/problems.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        location: State.location,
        field:    State.field,
        mode:     mode,
      }),
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Server error');

    State.problems = data.problems;
    renderProblems();
    showToast(`Found ${data.problems.length} research problems in ${data.location}`, 'success');
  } catch (err) {
    grid.innerHTML = `<div style="grid-column:1/-1; text-align:center; color:var(--accent-red); padding:40px 0;">
      <div style="font-size:28px;margin-bottom:10px;">⚠</div>
      <div style="font-weight:600; margin-bottom:6px;">Failed to load problems</div>
      <div style="font-size:13px; color:var(--text-secondary);">${err.message}</div>
      <button onclick="fetchProblems()" style="margin-top:16px;" class="btn-secondary">Try Again</button>
    </div>`;
    showToast(err.message, 'error');
  } finally {
    if (spinner) spinner.style.display = 'none';
  }
}

function renderProblems() {
  const grid = $('problems-grid');
  if (!State.problems.length) {
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:40px 0;">No problems returned. Try again.</div>';
    return;
  }

  grid.innerHTML = State.problems.map((p, i) => {
    const sevClass  = p.severity  === 'high' ? 'badge-severity-high' : 'badge-severity-medium';
    const trendClass= p.trend     === 'rising' ? 'badge-trend-rising' : 'badge-trend-stable';
    const kws = (p.keywords || []).map(k => `<span class="kw-tag">${k}</span>`).join('');

    return `
    <div class="problem-card" data-idx="${i}" onclick="selectProblem(${i})">
      <div class="problem-select-indicator" id="prob-indicator-${i}"></div>
      <div class="problem-card-header">
        <div class="problem-title">${p.title}</div>
        <div class="problem-badges">
          <span class="badge ${sevClass}">${p.severity || 'medium'}</span>
          <span class="badge ${trendClass}">${p.trend || 'stable'}</span>
        </div>
      </div>
      <div class="problem-desc">${p.description}</div>
      <div class="problem-keywords">${kws}</div>
    </div>`;
  }).join('');
}

function selectProblem(idx) {
  State.selectedProblem = State.problems[idx];

  // Update UI selection
  $$('.problem-card').forEach((card, i) => {
    card.classList.toggle('selected', i === idx);
    const indicator = $(`prob-indicator-${i}`);
    if (indicator) indicator.textContent = i === idx ? '✓' : '';
  });

  // Update selected summary bar
  const bar = $('selected-problem-bar');
  if (bar) {
    bar.style.display = 'flex';
    const txt = $('selected-problem-text');
    if (txt) txt.textContent = State.selectedProblem.title;
  }

  showToast('Problem selected! Click Generate Titles to continue.', 'gold');
}

function initStep2() {
  // Search mode toggle
  $$('[data-search-mode]').forEach(btn => {
    btn.addEventListener('click', () => {
      $$('[data-search-mode]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });

  // Refresh button
  const refreshBtn = $('btn-refresh-problems');
  if (refreshBtn) refreshBtn.addEventListener('click', () => {
    State.problems = [];
    State.selectedProblem = null;
    const modeEl = document.querySelector('[data-search-mode].active');
    fetchProblems(modeEl?.dataset.searchMode || 'ai');
  });

  // Next button
  const nextBtn = $('btn-next-2');
  if (nextBtn) nextBtn.addEventListener('click', () => {
    if (!State.selectedProblem) { showToast('Select a research problem first.', 'error'); return; }
    State.titles = []; // reset so new ones are fetched
    goToStep(3, false);
  });

  // Back button
  const backBtn = $('btn-back-2');
  if (backBtn) backBtn.addEventListener('click', () => goToStep(1, false));
}

/* ===== STEP 3: GENERATE TITLES ===== */
async function fetchTitles() {
  const list    = $('titles-list');
  const spinner = $('titles-spinner');

  list.innerHTML = Array(State.titleCount).fill(`
    <div class="title-card">
      <div class="skeleton skeleton-line" style="width:80%; height:18px; margin-bottom:14px;"></div>
      <div class="skeleton skeleton-line" style="width:40%; height:13px; margin-bottom:10px;"></div>
      <div class="skeleton skeleton-line" style="width:95%; height:12px;"></div>
    </div>
  `).join('');
  if (spinner) spinner.style.display = 'flex';

  const body = {
    problem:   State.selectedProblem?.title + '. ' + (State.selectedProblem?.description || ''),
    field:     State.field,
    location:  State.location,
    framework: State.framework,
    count:     State.titleCount,
    keywords:  State.keywords,
    level:     State.level,
  };

  try {
    const res  = await fetch('../thesis/api/generate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Server error');

    State.titles = data.titles;
    renderTitles();
    showToast(`${State.titles.length} title suggestions generated!`, 'success');

    const meta = $('results-meta');
    if (meta) meta.textContent = `${State.titles.length} titles · ${State.field} · ${State.location} · via ${data.model}`;
  } catch (err) {
    list.innerHTML = `<div style="text-align:center; color:var(--accent-red); padding:40px 0;">
      <div style="font-size:28px;margin-bottom:10px;">⚠</div>
      <div style="font-weight:600; margin-bottom:6px;">Failed to generate titles</div>
      <div style="font-size:13px; color:var(--text-secondary);">${err.message}</div>
      <button onclick="fetchTitles()" style="margin-top:16px;" class="btn-secondary">Retry</button>
    </div>`;
    showToast(err.message, 'error');
  } finally {
    if (spinner) spinner.style.display = 'none';
  }
}

function renderTitles() {
  const list = $('titles-list');
  if (!State.titles.length) {
    list.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:40px 0;">No titles generated. Try again.</div>';
    return;
  }

  list.innerHTML = State.titles.map((t, i) => {
    const novScore  = t.novelty_score || 5;
    const stars     = Array(10).fill(null).map((_, si) =>
      `<span class="novelty-star ${si < novScore ? 'lit' : ''}">★</span>`
    ).join('');
    const isFav     = State.favorites.some(f => f.title === t.title);
    const vars      = t.variables || {};
    const varChips  = [
      vars.independent ? `<span class="var-chip iv">IV: ${vars.independent}</span>` : '',
      vars.dependent   ? `<span class="var-chip dv">DV: ${vars.dependent}</span>`   : '',
      vars.moderating && vars.moderating !== 'null' ? `<span class="var-chip mv">MOD: ${vars.moderating}</span>` : '',
    ].filter(Boolean).join('');

    return `
    <div class="title-card ${isFav ? 'favorited' : ''}" id="title-card-${i}">
      <div class="title-card-top">
        <div class="title-num">${String(i+1).padStart(2,'0')}</div>
        <div class="title-text" id="title-text-${i}">${t.title}</div>
        <div class="title-actions">
          <button class="icon-btn ${isFav ? 'active' : ''}" title="Favorite" onclick="toggleFavorite(${i})" id="fav-btn-${i}">⭐</button>
          <button class="icon-btn" title="Copy title" onclick="copyTitle(${i})">📋</button>
        </div>
      </div>
      <div class="title-meta-row">
        <span class="framework-pill">${t.framework || 'General'}</span>
        <div class="novelty-stars" title="Novelty: ${novScore}/10">${stars}</div>
        <span style="font-size:11px; color:var(--text-muted);">${novScore}/10 novelty</span>
      </div>
      ${t.rationale ? `<div class="title-rationale">${t.rationale}</div>` : ''}
      ${varChips ? `<div class="title-variables">${varChips}</div>` : ''}
      <button class="btn-analyze-title" onclick="openAnalyzeModal(${i})">🔍 Analyze this title</button>
    </div>`;
  }).join('');
}

function toggleFavorite(idx) {
  const title = State.titles[idx];
  if (!title) return;
  const existsAt = State.favorites.findIndex(f => f.title === title.title);
  if (existsAt >= 0) {
    State.favorites.splice(existsAt, 1);
    showToast('Removed from favorites', 'info');
    const card = $(`title-card-${idx}`);
    const btn  = $(`fav-btn-${idx}`);
    if (card) card.classList.remove('favorited');
    if (btn)  btn.classList.remove('active');
  } else {
    State.favorites.push({ title: title.title, framework: title.framework, field: State.field, location: State.location, saved_at: new Date().toISOString() });
    showToast('Added to favorites! ⭐', 'gold');
    const card = $(`title-card-${idx}`);
    const btn  = $(`fav-btn-${idx}`);
    if (card) card.classList.add('favorited');
    if (btn)  btn.classList.add('active');
  }
  localStorage.setItem('thesis_favorites', JSON.stringify(State.favorites));
  renderFavorites();
}

function copyTitle(idx) {
  const el = $(`title-text-${idx}`);
  if (!el) return;
  navigator.clipboard.writeText(el.textContent.trim())
    .then(() => showToast('Title copied to clipboard!', 'success'))
    .catch(() => showToast('Copy failed. Please copy manually.', 'error'));
}

function initStep3() {
  // Regenerate button
  const regenBtn = $('btn-regen');
  if (regenBtn) regenBtn.addEventListener('click', () => {
    State.titles = [];
    fetchTitles();
  });

  // Back button
  const backBtn = $('btn-back-3');
  if (backBtn) backBtn.addEventListener('click', () => goToStep(2, false));

  // Export all button
  const exportBtn = $('btn-export-all');
  if (exportBtn) exportBtn.addEventListener('click', exportAllTitles);

  // Custom title analyze input
  const analyzeInput = $('analyze-custom-input');
  const analyzeBtn   = $('btn-analyze-custom');
  if (analyzeBtn && analyzeInput) {
    analyzeBtn.addEventListener('click', () => {
      const t = analyzeInput.value.trim();
      if (!t) { showToast('Enter a thesis title to analyze.', 'error'); return; }
      analyzeTitle(t, null);
    });
  }
}

function exportAllTitles() {
  if (!State.titles.length) { showToast('No titles to export.', 'error'); return; }
  const header = `THESIS TITLE SUGGESTIONS\n${'='.repeat(50)}\nField: ${State.field}\nLocation: ${State.location}\nProblem: ${State.selectedProblem?.title || ''}\nGenerated: ${new Date().toLocaleString()}\n${'='.repeat(50)}\n\n`;
  const body   = State.titles.map((t, i) => `${i+1}. ${t.title}\n   Framework: ${t.framework || 'N/A'} | Novelty: ${t.novelty_score || '?'}/10\n   ${t.rationale || ''}\n`).join('\n');
  const blob   = new Blob([header + body], { type: 'text/plain' });
  const url    = URL.createObjectURL(blob);
  const a      = document.createElement('a');
  a.href       = url;
  a.download   = `thesis_titles_${State.field.replace(/\s/g,'_')}_${Date.now()}.txt`;
  a.click();
  URL.revokeObjectURL(url);
  showToast('Titles exported as .txt!', 'success');
}

/* ===== FAVORITES PANEL ===== */
function renderFavorites() {
  const list = $('favorites-list');
  if (!list) return;
  if (!State.favorites.length) {
    list.innerHTML = '<div class="fav-empty">No favorites saved yet. Star a title to save it here.</div>';
    return;
  }
  list.innerHTML = State.favorites.map((f, i) => `
    <div class="fav-item">
      <div class="fav-text">${f.title}</div>
      <span class="fav-remove" onclick="removeFavorite(${i})" title="Remove">×</span>
    </div>
  `).join('');
}

function removeFavorite(idx) {
  State.favorites.splice(idx, 1);
  localStorage.setItem('thesis_favorites', JSON.stringify(State.favorites));
  renderFavorites();
  showToast('Removed from favorites', 'info');
}

/* ===== ANALYZE MODAL ===== */
function openAnalyzeModal(idx) {
  const title = State.titles[idx];
  if (!title) return;
  analyzeTitle(title.title, idx);
}

async function analyzeTitle(titleText, idx) {
  const modal   = $('analyze-modal');
  const content = $('analyze-modal-content');

  modal.classList.add('open');
  content.innerHTML = `
    <div style="text-align:center; padding: 40px 0;">
      <div class="spinner" style="margin:0 auto 16px; width:32px; height:32px; border-width:3px;"></div>
      <div style="color:var(--text-secondary); font-size:13px;">Analyzing title quality...</div>
    </div>`;

  try {
    const res  = await fetch('../thesis/api/analyze.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: titleText, field: State.field, level: State.level }),
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);

    const a = data.analysis;
    const scoreItems = ['specificity','researchability','novelty','clarity','scope'].map(key => {
      const val = a.scores[key] || 0;
      const pct = Math.round((val / 20) * 100);
      return `
        <div class="score-item">
          <div class="score-label">${key}</div>
          <div class="score-bar-wrap"><div class="score-bar-fill" style="width:${pct}%"></div></div>
          <div class="score-num">${val}/20</div>
        </div>`;
    }).join('');

    const suggestions = (a.suggestions || []).map(s => `<li>${s}</li>`).join('');
    const alts = (a.alternatives || []).map(alt => `
      <div class="alt-title-item" onclick="navigator.clipboard.writeText(this.textContent.trim()).then(()=>showToast('Copied!','success'))" title="Click to copy">
        ${alt}
      </div>`).join('');

    content.innerHTML = `
      <div class="total-score-display">
        <div class="total-score-num">${a.scores.total || '—'}</div>
        <div class="total-score-grade">${a.grade || ''}</div>
        <div class="total-score-verdict">${a.verdict || ''}</div>
      </div>
      <div class="score-grid">${scoreItems}</div>
      ${suggestions ? `
        <div style="margin-bottom:16px;">
          <div style="font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:8px;">Improvement Suggestions</div>
          <ul class="suggestion-list">${suggestions}</ul>
        </div>` : ''}
      ${alts ? `
        <div>
          <div style="font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:8px;">Improved Versions (click to copy)</div>
          <div class="alt-titles">${alts}</div>
        </div>` : ''}`;
  } catch (err) {
    content.innerHTML = `<div style="text-align:center; color:var(--accent-red); padding:32px 0;">
      <div style="font-size:26px;margin-bottom:8px;">⚠</div>
      <div>${err.message}</div>
    </div>`;
    showToast(err.message, 'error');
  }
}

function closeAnalyzeModal() {
  $('analyze-modal').classList.remove('open');
}

/* ===== INIT ===== */
document.addEventListener('DOMContentLoaded', () => {
  initStep1();
  initStep2();
  initStep3();
  renderFavorites();

  // Wizard step item clicks (allow going back)
  $$('.step-item').forEach((item, i) => {
    item.style.cursor = 'pointer';
    item.addEventListener('click', () => {
      const stepNum = i + 1;
      if (stepNum < State.currentStep) goToStep(stepNum, false);
    });
  });

  // Modal close
  $('analyze-modal').addEventListener('click', e => {
    if (e.target === $('analyze-modal')) closeAnalyzeModal();
  });

  // Export favorites
  const exportFavBtn = $('btn-export-favorites');
  if (exportFavBtn) {
    exportFavBtn.addEventListener('click', () => {
      if (!State.favorites.length) { showToast('No favorites to export.', 'error'); return; }
      const text = State.favorites.map((f, i) => `${i+1}. ${f.title}\n   Field: ${f.field} | Location: ${f.location}`).join('\n\n');
      const blob = new Blob([text], { type: 'text/plain' });
      const a    = document.createElement('a');
      a.href     = URL.createObjectURL(blob);
      a.download = 'thesis_favorites.txt';
      a.click();
      showToast('Favorites exported!', 'success');
    });
  }
});
