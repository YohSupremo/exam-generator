/**
 * nav.js — behaviour for the shared SynthExam navbar (lib/site_header.php).
 *
 * The exam generator loads the full site.js, which already contains these
 * handlers. Pages that only include the shared navbar (e.g. the thesis maker)
 * load this compact version instead, so the header behaves identically
 * without pulling in exam-specific upload/polling code.
 */
(function () {
  'use strict';

  var API_BASE = document.body.dataset.apiBase || 'api/';

  /* ── Sticky header shadow ─────────────────────────────────────────────── */
  var header = document.getElementById('site-header');
  if (header) {
    var onScroll = function () {
      header.classList.toggle('scrolled', window.scrollY > 8);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ── Hamburger / mobile drawer ────────────────────────────────────────── */
  var burger = document.getElementById('hamburger');
  var drawer = document.getElementById('mobile-nav');
  if (burger && drawer) {
    burger.addEventListener('click', function () {
      var open = drawer.classList.toggle('open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
    });
    drawer.querySelectorAll('.mobile-nav-link').forEach(function (link) {
      link.addEventListener('click', function () {
        drawer.classList.remove('open');
        burger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      });
    });
    document.addEventListener('click', function (e) {
      if (drawer.classList.contains('open') &&
          !drawer.contains(e.target) && !burger.contains(e.target)) {
        drawer.classList.remove('open');
        burger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      }
    });
  }

  /* ── Settings panel ───────────────────────────────────────────────────── */
  var panel = document.getElementById('settings-panel');
  var backdrop = document.getElementById('settings-backdrop');
  var gear = document.getElementById('settings-gear-btn');
  var closeBtn = document.getElementById('settings-panel-close');
  var panelOpen = false;

  function openPanel() {
    if (!panel) return;
    panelOpen = true;
    panel.hidden = false;
    if (backdrop) backdrop.hidden = false;
    if (gear) {
      gear.setAttribute('aria-expanded', 'true');
      var icon = gear.querySelector('.settings-gear-icon');
      if (icon) icon.classList.add('is-spinning');
      var r = gear.getBoundingClientRect();
      panel.style.top = (r.bottom + 8) + 'px';
      panel.style.right = (window.innerWidth - r.right) + 'px';
    }
    setTimeout(function () { panel.classList.add('is-open'); }, 10);
  }

  function closePanel() {
    if (!panel) return;
    panelOpen = false;
    panel.classList.remove('is-open');
    if (gear) {
      gear.setAttribute('aria-expanded', 'false');
      var icon = gear.querySelector('.settings-gear-icon');
      if (icon) icon.classList.remove('is-spinning');
    }
    setTimeout(function () {
      panel.hidden = true;
      if (backdrop) backdrop.hidden = true;
    }, 220);
  }

  if (gear) gear.addEventListener('click', function (e) {
    e.stopPropagation();
    if (panelOpen) closePanel(); else openPanel();
  });
  if (closeBtn) closeBtn.addEventListener('click', closePanel);
  if (backdrop) backdrop.addEventListener('click', closePanel);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && panelOpen) closePanel();
  });

  /* ── Sound FX toggle ──────────────────────────────────────────────────── */
  var sfxOn = true;
  try { sfxOn = localStorage.getItem('synthexam_robot_sfx') !== '0'; } catch (e) {}
  function renderSfx() {
    document.querySelectorAll('.robot-sfx-toggle').forEach(function (btn) {
      var icon = btn.querySelector('.sfx-btn-icon, .robot-sfx-icon');
      var text = btn.querySelector('.sfx-btn-text, .robot-sfx-text');
      btn.classList.toggle('is-muted', !sfxOn);
      if (icon) icon.textContent = sfxOn ? 'volume_up' : 'volume_off';
      if (text) text.textContent = sfxOn ? 'SFX: ON' : 'SFX: OFF';
      btn.title = sfxOn ? 'Sound effects active (Click to mute)' : 'Sound effects muted (Click to enable)';
    });
  }
  renderSfx();
  document.querySelectorAll('.robot-sfx-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      sfxOn = !sfxOn;
      try { localStorage.setItem('synthexam_robot_sfx', sfxOn ? '1' : '0'); } catch (e) {}
      renderSfx();
    });
  });

  /* ── Graphics quality presets ─────────────────────────────────────────── */
  var PRESETS = {
    ultra: { label: 'Ultra', detail: 'Full cursor trail, glow shadows, ambient blur and all animations enabled.' },
    high:  { label: 'High',  detail: 'Cursor trail, no glow shadows, full ambient effects and animations.' },
    lite:  { label: 'Lite',  detail: 'No cursor trail. Reduced ambient blur and simplified splash.' },
    off:   { label: 'Off',   detail: 'All visual effects disabled. Best for low-end or shared devices.' }
  };
  var GFX_KEY = 'synthexam_gfx_preset';

  function detectPreset() {
    var cores = navigator.hardwareConcurrency || 4;
    var mem = navigator.deviceMemory || 4;
    var touch = window.matchMedia && window.matchMedia('(hover: none)').matches;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced || touch) return 'lite';
    if (cores <= 2 || mem <= 1) return 'off';
    if (cores <= 4 || mem <= 2) return 'lite';
    if (cores <= 6 || mem <= 4) return 'high';
    return 'ultra';
  }

  function applyPreset(name, manual) {
    if (!PRESETS[name]) return;
    var html = document.documentElement;
    html.classList.remove('gfx-ultra', 'gfx-high', 'gfx-lite', 'gfx-off');
    html.classList.add('gfx-' + name);
    document.querySelectorAll('.gfx-preset-btn').forEach(function (btn) {
      var active = btn.dataset.preset === name;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-checked', active ? 'true' : 'false');
    });
    var detail = document.getElementById('gfx-detail-text');
    if (detail) detail.textContent = PRESETS[name].detail;
    var autoBadge = document.getElementById('gfx-auto-badge');
    if (autoBadge) autoBadge.style.display = manual ? 'none' : '';
    if (manual) {
      try { localStorage.setItem(GFX_KEY, name); } catch (e) {}
    }
    window.synthExamGfxPreset = name;
  }

  var gfxGrid = document.getElementById('gfx-preset-grid');
  if (gfxGrid) {
    gfxGrid.addEventListener('click', function (e) {
      var btn = e.target.closest('.gfx-preset-btn');
      if (btn) applyPreset(btn.dataset.preset, true);
    });
  }
  var stored = null;
  try { stored = localStorage.getItem(GFX_KEY); } catch (e) {}
  var auto = detectPreset();
  applyPreset(stored && PRESETS[stored] ? stored : auto, !!stored);

  var devInfo = document.getElementById('settings-device-info');
  if (devInfo) {
    var cores = navigator.hardwareConcurrency || '?';
    var mem = navigator.deviceMemory ? navigator.deviceMemory + ' GB RAM' : '';
    var touch = window.matchMedia && window.matchMedia('(hover: none)').matches ? 'Touch device' : '';
    devInfo.textContent = cores + ' CPU cores' + (mem ? ' · ' + mem : '') +
      (touch ? ' · ' + touch : '') + ' — Auto: ' + PRESETS[auto].label;
  }

  /* ── Live badge data (queue / token / backend) ────────────────────────── */
  var queueBadge = document.getElementById('queue-badge');
  var queueCount = document.getElementById('queue-badge-count');
  var backendEl = document.getElementById('backend-status');
  var htbRemaining = document.getElementById('htb-remaining');
  var htbLimit = document.getElementById('htb-limit');
  var htbBadge = document.getElementById('header-token-badge');

  function json(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('http ' + r.status);
      return r.json();
    });
  }

  function updateQueue() {
    json(API_BASE + 'queue.php').then(function (res) {
      var total = Number(res.total || 0);
      if (queueCount) queueCount.textContent = total;
      if (queueBadge) {
        queueBadge.classList.toggle('queue-badge--active', total > 0);
        queueBadge.classList.toggle('queue-badge--idle', total === 0);
        queueBadge.title = total > 0 ? total + ' job(s) in queue' : 'No jobs in queue';
      }
    }).catch(function () {});
  }

  function updateQuota(res) {
    var rl = res && res.quota && res.quota.ratelimit;
    if (!rl) return;
    var rem = Number(rl.remaining_tokens || 0);
    var lim = Number(rl.limit_tokens || 0);
    if (htbRemaining) htbRemaining.textContent = rem.toLocaleString();
    if (htbLimit) htbLimit.textContent = lim.toLocaleString();
    if (htbBadge) htbBadge.title = 'AI Rate Limit: ' + rem.toLocaleString() + ' / ' + lim.toLocaleString() + ' TPM';
  }

  json(API_BASE + 'status.php?ping=1').then(function (res) {
    if (backendEl) {
      var ok = !!(res.opencode && res.php);
      backendEl.innerHTML = ok
        ? '<span class="dot online"></span>Backend online'
        : '<span class="dot offline"></span>Backend unreachable';
    }
    updateQuota(res);
    updateQueue();
  }).catch(function () {
    if (backendEl) backendEl.innerHTML = '<span class="dot offline"></span>Backend unreachable';
    updateQueue();
  });

  setInterval(updateQueue, 8000);
})();
