(function () {
  "use strict";

  const dz = document.getElementById("dropzone");
  const fileInput = document.getElementById("pdf-file");
  const dzHint = document.getElementById("dz-hint");
  const submitBtn = document.getElementById("submit-btn");
  const statusArea = document.getElementById("status-area");
  const statusText = document.getElementById("status-text");
  const titleInput = document.getElementById("title-input");
  const form = document.getElementById("upload-form");

  let selectedFile = null;
  let pollTimer = null;

  function setStatus(html, show) {
    if (show === undefined) show = true;
    statusArea.hidden = !show;
    if (show) statusText.innerHTML = html;
  }

  const dzIdle = document.getElementById("dz-idle-state");
  const dzFileSel = document.getElementById("dz-file-selected");
  const dfsName = document.getElementById("dfs-name");
  const dfsSize = document.getElementById("dfs-size");
  const dfsRemove = document.getElementById("dfs-remove-btn");

  function setSubmitBtnState(disabled, labelText) {
    if (!submitBtn) return;
    submitBtn.disabled = disabled;
    var textSpan = submitBtn.querySelector(".btn-text") || submitBtn.querySelector("span:not(.material-symbols-rounded):not(.btn-synth-icon):not(.btn-icon)");
    if (textSpan) {
      textSpan.textContent = labelText;
    } else {
      submitBtn.textContent = labelText;
    }
  }

  /* ============================================================
     ROBOT GAME SFX AUDIO ENGINE & PROCEDURAL TACTILE SYNTH
     ============================================================ */
  var audioCtx = null;
  var sfxEnabled = true;
  try {
    var storedSfx = localStorage.getItem("synthexam_robot_sfx");
    sfxEnabled = storedSfx !== "0";
  } catch (e) {}

  function getAudioCtx() {
    if (!audioCtx) {
      var AC = window.AudioContext || window.webkitAudioContext;
      if (AC) audioCtx = new AC();
    }
    if (audioCtx && audioCtx.state === "suspended") {
      audioCtx.resume().catch(function () {});
    }
    return audioCtx;
  }

  // Soft high-speed keystroke for typewriter animation & typing in inputs
  function playRobotKeyClick(isSpace) {
    if (!sfxEnabled) return;
    try {
      var ctx = getAudioCtx();
      if (!ctx) return;
      var now = ctx.currentTime;
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();

      var freq = isSpace ? 520 : (840 + Math.random() * 340);
      osc.type = "sine";
      osc.frequency.setValueAtTime(freq, now);

      var dur = isSpace ? 0.02 : 0.014;
      gain.gain.setValueAtTime(0.035, now);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + dur);

      osc.connect(gain);
      gain.connect(ctx.destination);

      osc.start(now);
      osc.stop(now + dur);
    } catch (e) {}
  }

  // Crisp, satisfying mechanical tactile switch sound for button & interactive clicks
  function playTactileClickSound(pitchVariation) {
    if (!sfxEnabled) return;
    try {
      var ctx = getAudioCtx();
      if (!ctx) return;
      var now = ctx.currentTime;

      // Layer 1: Crisp high mechanical snap (triangle wave)
      var snap = ctx.createOscillator();
      var snapGain = ctx.createGain();
      var snapFreq = (pitchVariation || 1750) + (Math.random() * 200 - 100);
      snap.type = "triangle";
      snap.frequency.setValueAtTime(snapFreq, now);
      snap.frequency.exponentialRampToValueAtTime(340, now + 0.032);

      snapGain.gain.setValueAtTime(0.08, now);
      snapGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.035);

      snap.connect(snapGain);
      snapGain.connect(ctx.destination);
      snap.start(now);
      snap.stop(now + 0.038);

      // Layer 2: Deep solid bottom-out thud (sine wave)
      var thud = ctx.createOscillator();
      var thudGain = ctx.createGain();
      thud.type = "sine";
      thud.frequency.setValueAtTime(260 + Math.random() * 40, now);
      thud.frequency.exponentialRampToValueAtTime(70, now + 0.04);

      thudGain.gain.setValueAtTime(0.06, now);
      thudGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.042);

      thud.connect(thudGain);
      thudGain.connect(ctx.destination);
      thud.start(now);
      thud.stop(now + 0.045);
    } catch (e) {}
  }

  function updateAllSfxButtonsUI() {
    var allToggles = document.querySelectorAll(".robot-sfx-toggle");
    allToggles.forEach(function (btn) {
      var icon = btn.querySelector(".sfx-btn-icon, .robot-sfx-icon");
      var text = btn.querySelector(".sfx-btn-text, .robot-sfx-text");
      if (sfxEnabled) {
        btn.classList.remove("is-muted");
        if (icon) icon.textContent = "volume_up";
        if (text) text.textContent = "SFX: ON";
        btn.setAttribute("title", "Sound effects active (Click to mute)");
      } else {
        btn.classList.add("is-muted");
        if (icon) icon.textContent = "volume_off";
        if (text) text.textContent = "SFX: OFF";
        btn.setAttribute("title", "Sound effects muted (Click to enable)");
      }
    });
  }

  function setSfxState(enabled, showToastNotice) {
    sfxEnabled = enabled;
    try {
      localStorage.setItem("synthexam_robot_sfx", sfxEnabled ? "1" : "0");
    } catch (e) {}
    updateAllSfxButtonsUI();
    if (sfxEnabled) {
      playTactileClickSound(2100);
      if (showToastNotice && typeof showStudioToast === "function") {
        showStudioToast("Sound Effects Enabled", "success", 2000);
      }
    } else {
      if (showToastNotice && typeof showStudioToast === "function") {
        showStudioToast("Sound Effects Muted", "info", 2000);
      }
    }
  }

  function typewriteWithCursor(element, text, speed, onComplete, withSfx) {
    if (!element) return;
    speed = speed || 24;
    if (element._typeTimer) clearTimeout(element._typeTimer);

    element.innerHTML = '<span class="tw-chars"></span><span class="typewriter-cursor" aria-hidden="true"></span>';
    var charsSpan = element.querySelector(".tw-chars");
    var i = 0;

    function step() {
      if (i < text.length) {
        var char = text.charAt(i);
        if (charsSpan) charsSpan.textContent += char;
        if (withSfx) {
          playRobotKeyClick(char === " ");
        }
        i++;
        var delay = speed + (Math.random() * 12 - 6);
        if (char === "," || char === ";") delay += 70;
        else if (char === "." || char === "!" || char === "?") delay += 110;
        else if (char === "\u2014" || char === "\u2022") delay += 60;
        element._typeTimer = setTimeout(step, Math.max(10, delay));
      } else {
        var cursor = element.querySelector(".typewriter-cursor");
        if (cursor) {
          setTimeout(function () {
            if (cursor && cursor.parentNode) {
              cursor.style.transition = "opacity 0.3s ease";
              cursor.style.opacity = "0";
              setTimeout(function () {
                if (cursor && cursor.parentNode) cursor.remove();
              }, 300);
            }
          }, 1000);
        }
        if (onComplete) onComplete();
      }
    }
    step();
  }

  // Global window references for universal accessibility
  window.playRobotKeyClick = playRobotKeyClick;
  window.playTactileClickSound = playTactileClickSound;
  window.typewriteWithCursor = typewriteWithCursor;
  window.setSfxState = setSfxState;
  window.toggleSynthExamSfx = function () { setSfxState(!sfxEnabled, true); };

  function updateDropzoneUI() {
    setSubmitBtnState(!selectedFile, "Synthesize Interactive Exam");
    if (selectedFile) {
      if (dzIdle) dzIdle.style.display = "none";
      if (dzFileSel) dzFileSel.style.display = "flex";
      if (dfsName) dfsName.textContent = selectedFile.name;
      if (dfsSize) dfsSize.textContent = (selectedFile.size / 1024 / 1024).toFixed(2) + " MB";
      if (dzHint) dzHint.textContent = selectedFile.name + " (" + (selectedFile.size / 1024 / 1024).toFixed(2) + " MB)";
    } else {
      if (dzIdle) dzIdle.style.display = "flex";
      if (dzFileSel) dzFileSel.style.display = "none";
      if (dzHint) dzHint.textContent = "";
    }
  }

  if (dfsRemove) {
    dfsRemove.addEventListener("click", function (e) {
      e.stopPropagation();
      e.preventDefault();
      selectedFile = null;
      if (fileInput) fileInput.value = "";
      updateDropzoneUI();
      if (fileInput) fileInput.click();
    });
  }

  if (fileInput) {
    fileInput.addEventListener("change", function () {
      selectedFile = fileInput.files[0] || null;
      updateDropzoneUI();
    });
  }

  if (dz) {
    dz.addEventListener("click", function (e) {
      if (e.target && (e.target.closest("#dfs-remove-btn") || e.target.closest(".dfs-remove-btn"))) {
        return;
      }
      fileInput.click();
    });
    ["dragover", "dragenter"].forEach(function (ev) {
      dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add("drag"); });
    });
    ["dragleave", "drop"].forEach(function (ev) {
      dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove("drag"); });
    });
    dz.addEventListener("drop", function (e) {
      const f = e.dataTransfer.files[0];
      if (f && f.type === "application/pdf") {
        selectedFile = f;
        fileInput.files = undefined;
        const dt = new DataTransfer();
        dt.items.add(f);
        fileInput.files = dt.files;
        updateDropzoneUI();
      } else if (f) {
        alert("Please drop a PDF file.");
      }
    });
  }

  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (!selectedFile) return;
      const fd = new FormData();
      fd.append("pdf", selectedFile);
      fd.append("title", titleInput ? titleInput.value.trim() : "");

      setSubmitBtnState(true, "Uploading\u2026");
      setStatus("Uploading PDF\u2026");

      fetch("api/generate.php", { method: "POST", body: fd })
        .then(function (r) {
          return r.text().then(function (text) {
            try {
              return JSON.parse(text);
            } catch (e) {
              var cleanText = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
              throw new Error(cleanText || "Server returned an invalid response");
            }
          });
        })
        .then(function (res) {
          if (!res.ok) { throw new Error(res.error || "Upload failed"); }
          try {
            localStorage.setItem("active_exam_job", res.id);
            if (!window.location.search.includes("job=" + encodeURIComponent(res.id))) {
              history.replaceState(null, '', '?job=' + encodeURIComponent(res.id));
            }
          } catch (e) {}
          setStatus("Job created. <code>" + res.id + "</code>");
          startPolling(res.id);
        })
        .catch(function (err) {
          setSubmitBtnState(!selectedFile, "Synthesize Interactive Exam");
          setStatus("<span style='color:var(--red)'>" + String(err.message).replace(/</g, "&lt;") + "</span>");
        });
    });
  }

  let trackedJobId = null;

  function isActiveStatus(s) {
    return s === "queued" || s === "running";
  }

  function escapeHtml(str) {
    return String(str || '').replace(/[&<>"']/g, function (m) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
    });
  }

  function formatEta(seconds) {
    if (typeof seconds !== "number" || !isFinite(seconds) || seconds <= 0) return "\u2026";
    var min = Math.max(1, Math.round(seconds / 60));
    var label = min < 60 ? min + " min" : Math.max(1, Math.round(min / 60)) + " hr";
    return "~" + label + " left";
  }

  function cancelJob(id) {
    if (!confirm("Cancel this generation? It cannot be resumed after this.")) return Promise.resolve(false);
    try {
      localStorage.removeItem("active_exam_job");
      sessionStorage.removeItem("active_exam_job");
      history.replaceState(null, '', window.location.pathname);
    } catch (e) {}
    if (pollTimer) clearInterval(pollTimer);
    if (statusArea) statusArea.hidden = true;
    setSubmitBtnState(!selectedFile, "Synthesize Interactive Exam");
    if (!id) return Promise.resolve(true);
    return fetch("api/generate.php?op=cancel&id=" + encodeURIComponent(id), { method: "POST" })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        try {
          localStorage.removeItem("active_exam_job");
          sessionStorage.removeItem("active_exam_job");
        } catch (e) {}
        return true;
      })
      .catch(function () { return true; });
  }

  var DEFAULT_PHASES = [
    { step: 1, id: "preparation", label: "Preparation", sub: "Text extraction" },
    { step: 2, id: "planning", label: "Outline", sub: "Chapter analysis" },
    { step: 3, id: "generating", label: "Questions", sub: "Generating questions" },
    { step: 4, id: "assembly", label: "Assembly", sub: "Reviewer & key" },
    { step: 5, id: "ready", label: "Ready", sub: "Exam complete" }
  ];

  function renderPhaseStepper(stepperEl, currentStep, phases, doneChapters, totalChapters) {
    if (!stepperEl) return;
    var list = (phases && phases.length) ? phases : DEFAULT_PHASES;
    var html = '';
    list.forEach(function (p, idx) {
      var s = p.step || (idx + 1);
      var stateClass = '';
      var iconContent = '';
      if (s < currentStep) {
        stateClass = 'is-completed';
        iconContent = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
      } else if (s === currentStep) {
        stateClass = 'is-active';
        iconContent = s;
      } else {
        stateClass = 'is-pending';
        iconContent = s;
      }

      var sub = p.sub || '';
      if (s === 3 && totalChapters > 0) {
        sub = (doneChapters || 0) + '/' + totalChapters + ' ch';
      }

      html += '<div class="step-item ' + stateClass + '">';
      if (idx > 0) {
        html += '<div class="step-connector"></div>';
      }
      html += '<div class="step-circle">' + iconContent + '</div>';
      html += '<div class="step-label">' + escapeHtml(p.label) + '</div>';
      if (sub) {
        html += '<div class="step-sublabel">' + escapeHtml(sub) + '</div>';
      }
      html += '</div>';
    });
    stepperEl.innerHTML = html;
  }

  function applyRowStatus(id, status, etaSeconds, step, stepLabel, doneCh, totalCh) {
    var row = document.querySelector('tr[data-id="' + id + '"]');
    if (!row) return;
    var badge = row.querySelector(".badge");
    if (badge) {
      badge.textContent = status;
      badge.className = "badge badge-" + status;
      badge.dataset.status = status;
    }
    var phaseMini = row.querySelector("[data-phase-mini]");
    if (phaseMini) {
      if (isActiveStatus(status) && step && stepLabel) {
        var chText = (step === 3 && totalCh) ? " (" + (doneCh || 0) + "/" + totalCh + ")" : "";
        phaseMini.innerHTML = '<span class="phase-mini-pill">Phase ' + step + ': ' + escapeHtml(stepLabel) + chText + '</span>';
      } else {
        phaseMini.innerHTML = '';
      }
    }
    var etaEl = row.querySelector("[data-eta]");
    if (etaEl) etaEl.textContent = isActiveStatus(status) ? formatEta(etaSeconds) : "";
  }

  function renderTrackedStatus(id, res) {
    if (statusArea) statusArea.hidden = false;
    setSubmitBtnState(true, "Generating Exam\u2026");

    var step = res.step || 1;
    var percent = typeof res.percent === "number" ? res.percent : (step * 20);
    var label = res.step_label || (step === 1 ? "Preparation" : step === 2 ? "Chapter Outline" : step === 3 ? "Question Generation" : step === 4 ? "Assembly" : "Ready");
    var sub = res.step_sub || (res.status === "queued" ? "Queued in background worker" : "Generating interactive exam");

    var phaseBadgeEl = document.getElementById("status-phase-badge");
    if (phaseBadgeEl) phaseBadgeEl.textContent = "Phase " + step + " of 5";

    var etaPillEl = document.getElementById("status-eta-pill");
    if (etaPillEl) {
      etaPillEl.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="display:inline;vertical-align:-1px;margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>' + escapeHtml(res.eta_seconds ? formatEta(res.eta_seconds) : "Calculating ETA\u2026");
    }

    var headingEl = document.getElementById("status-phase-heading");
    if (headingEl) headingEl.textContent = label;

    var subEl = document.getElementById("status-phase-sub");
    if (subEl) subEl.textContent = sub;

    var stepperEl = document.getElementById("phase-stepper");
    renderPhaseStepper(stepperEl, step, res.phases, res.done_chapters, res.total_chapters);

    var progressBar = document.getElementById("phase-progress-bar");
    if (progressBar) progressBar.style.width = Math.max(3, Math.min(100, percent)) + "%";

    var percentText = document.getElementById("status-percent-text");
    if (percentText) percentText.textContent = Math.round(percent) + "%";

    var liveText = document.getElementById("status-text");
    if (liveText) {
      var nextMsg = res.message || (res.status === "queued" ? "Queued — starting shortly…" : "Generating with opencode…");
      if (liveText.dataset.lastMsg !== nextMsg) {
        liveText.dataset.lastMsg = nextMsg;
        typewriteWithCursor(liveText, nextMsg, 18);
      }
    }

    var cancelBtn = document.getElementById("cancel-tracked-btn");
    if (cancelBtn) {
      cancelBtn.onclick = function () {
        cancelBtn.disabled = true;
        cancelBtn.textContent = "Cancelling\u2026";
        cancelJob(trackedJobId).then(function (ok) {
          if (ok) {
            window.location.href = window.location.pathname;
          } else {
            cancelBtn.disabled = false;
            cancelBtn.textContent = "Cancel";
          }
        });
      };
    }

    // Render live token usage & rate limits
    var tokenLiveBanner = document.getElementById("token-live-banner");
    var tokenLiveCount = document.getElementById("token-live-count");
    var tokenLiveRateText = document.getElementById("token-live-rate-text");
    var tokenLiveTpmBar = document.getElementById("token-live-tpm-bar");
    var tokenLiveResetTag = document.getElementById("token-live-reset-tag");

    var tokData = res.token_usage || null;
    var totalTok = res.total_tokens || (tokData ? tokData.total_tokens : 0);

    if (tokenLiveBanner) {
      if (totalTok > 0 || (tokData && tokData.ratelimit)) {
        tokenLiveBanner.style.display = "flex";
        if (tokenLiveCount) tokenLiveCount.textContent = Number(totalTok).toLocaleString();
        if (tokData && tokData.ratelimit) {
          var rl = tokData.ratelimit;
          var rem = rl.remaining_tokens !== undefined ? rl.remaining_tokens : 8000;
          var lim = rl.limit_tokens !== undefined ? rl.limit_tokens : 8000;
          var pct = Math.max(0, Math.min(100, Math.round((rem / lim) * 100)));
          if (tokenLiveRateText) tokenLiveRateText.textContent = Number(rem).toLocaleString() + " / " + Number(lim).toLocaleString() + " TPM (" + pct + "%)";
          if (tokenLiveTpmBar) {
            tokenLiveTpmBar.style.width = pct + "%";
            tokenLiveTpmBar.style.backgroundColor = pct < 20 ? "var(--red)" : pct < 50 ? "var(--amber)" : "var(--green)";
          }
          if (tokenLiveResetTag) tokenLiveResetTag.textContent = rl.reset_tokens ? "Resets in " + rl.reset_tokens : "Ready";
          if (res.quota) updateQuotaUI(res.quota);
          else updateQuotaUI(tokData);
        }
      } else {
        tokenLiveBanner.style.display = "none";
      }
    }

    if (res.quota) updateQuotaUI(res.quota);

    applyRowStatus(id, res.status, res.eta_seconds, step, label, res.done_chapters, res.total_chapters);
  }

  function refreshActiveRows() {
    var rows = Array.prototype.slice.call(document.querySelectorAll("tr[data-id]"));
    rows.forEach(function (row) {
      var id = row.dataset.id;
      var badge = row.querySelector(".badge");
      if (!badge || !isActiveStatus(badge.dataset.status)) return;
      fetch("api/status.php?id=" + encodeURIComponent(id))
        .then(function (resp) { return resp.json(); })
        .then(function (res) {
          if (isActiveStatus(res.status)) {
            applyRowStatus(id, res.status, res.eta_seconds, res.step, res.step_label, res.done_chapters, res.total_chapters);
            if (res.quota) updateQuotaUI(res.quota);
          } else {
            window.location.reload();
          }
        })
        .catch(function () { /* transient, keep polling */ });
    });
  }

  function startPolling(id) {
    if (!id) return;
    trackedJobId = id;
    try {
      localStorage.setItem("active_exam_job", id);
      if (!window.location.search.includes("job=" + encodeURIComponent(id))) {
        history.replaceState(null, '', '?job=' + encodeURIComponent(id));
      }
    } catch (e) {}
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(function () { poll(id); }, 2000);
    poll(id);
  }

  function poll(id) {
    fetch("api/status.php?id=" + encodeURIComponent(id))
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.ok) {
          if (pollTimer) clearInterval(pollTimer);
          try {
            localStorage.removeItem("active_exam_job");
            history.replaceState(null, '', window.location.pathname);
          } catch (e) {}
          if (statusArea) statusArea.hidden = true;
          setSubmitBtnState(!selectedFile, "Synthesize Interactive Exam");
          return;
        }

        if (res.status === "queued" || res.status === "running") {
          renderTrackedStatus(id, res);
        } else if (res.status === "done") {
          if (pollTimer) clearInterval(pollTimer);
          try {
            localStorage.removeItem("active_exam_job");
            history.replaceState(null, '', window.location.pathname);
          } catch (e) {}
          var headingEl = document.getElementById("status-phase-heading");
          if (headingEl) headingEl.textContent = "Exam Ready!";
          var liveText = document.getElementById("status-text");
          if (liveText) liveText.textContent = "Exam ready! Opening it now\u2026";
          var progressBar = document.getElementById("phase-progress-bar");
          if (progressBar) progressBar.style.width = "100%";
          var percentText = document.getElementById("status-percent-text");
          if (percentText) percentText.textContent = "100%";
          setTimeout(function () {
            window.location.href = "exam.php?id=" + encodeURIComponent(id);
          }, 800);
        } else if (res.status === "cancelled") {
          if (pollTimer) clearInterval(pollTimer);
          try {
            localStorage.removeItem("active_exam_job");
            history.replaceState(null, '', window.location.pathname);
          } catch (e) {}
          if (statusArea) statusArea.hidden = true;
          setSubmitBtnState(!selectedFile, "Synthesize Interactive Exam");
        } else if (res.status === "error") {
          if (pollTimer) clearInterval(pollTimer);
          try {
            localStorage.removeItem("active_exam_job");
            history.replaceState(null, '', window.location.pathname);
          } catch (e) {}
          if (statusArea) statusArea.hidden = false;
          var headingEl = document.getElementById("status-phase-heading");
          if (headingEl) headingEl.textContent = "Generation Failed";
          var liveText = document.getElementById("status-text");
          var errMsg = res.error || res.message || "Unknown error";
          if (liveText) liveText.innerHTML = "<span style='color:var(--red)'>Error: " + escapeHtml(errMsg) + "</span>";
          submitBtn.disabled = !selectedFile;
          submitBtn.textContent = "Generate Exam";
        }
      })
      .catch(function () { /* transient network error, keep polling */ });
  }

  // Bind the top card cancel button immediately
  var cancelTrackedBtn = document.getElementById("cancel-tracked-btn");
  if (cancelTrackedBtn) {
    cancelTrackedBtn.addEventListener("click", function () {
      var targetId = trackedJobId || jobToTrack || (typeof AUTO_POLL_JOB !== "undefined" ? AUTO_POLL_JOB : null);
      if (!targetId) {
        try { targetId = localStorage.getItem("active_exam_job"); } catch (e) {}
      }
      cancelTrackedBtn.disabled = true;
      cancelTrackedBtn.textContent = "Cancelling…";
      cancelJob(targetId).then(function (ok) {
        if (ok) {
          window.location.href = window.location.pathname;
        } else {
          cancelTrackedBtn.disabled = false;
          cancelTrackedBtn.textContent = "Cancel";
        }
      });
    });
  }

  // auto-track a job when arriving with ?job=..., or from active_exam_job in localStorage, or from active table row
  var jobToTrack = (typeof AUTO_POLL_JOB !== "undefined" && AUTO_POLL_JOB) ? AUTO_POLL_JOB : (window.AUTO_POLL_JOB || null);
  if (!jobToTrack) {
    try {
      jobToTrack = localStorage.getItem("active_exam_job");
    } catch (e) {}
  }
  if (!jobToTrack) {
    var activeRow = document.querySelector('tr[data-id] .badge[data-status="running"], tr[data-id] .badge[data-status="queued"]');
    if (activeRow) {
      var tr = activeRow.closest('tr[data-id]');
      if (tr && tr.dataset.id) {
        jobToTrack = tr.dataset.id;
      }
    }
  }
  if (jobToTrack) {
    startPolling(jobToTrack);
  }

  // live ETA + cancel for every active row on the table
  document.querySelectorAll("[data-cancel]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      cancelJob(btn.dataset.cancel).then(function (ok) { if (ok) window.location.reload(); });
    });
  });
  refreshActiveRows();
  setInterval(refreshActiveRows, 4000);

  const backendEl = document.getElementById("backend-status");
  var pingAttempts = 0;
  var pingTimer = null;

  function renderBackend(online, misconfigured) {
    if (!backendEl) return;
    backendEl.innerHTML = online
      ? '<span class="dot online"></span>Backend online'
      : '<span class="dot offline"></span>' + (misconfigured ? "Backend misconfigured" : "Backend unreachable");
  }

  // Live Quota Animation & Real-Time Cooldown Countdown Ticker
  var liveQuotaState = null;
  var liveTickerTimer = null;

  function parseGroqDuration(str) {
    if (!str) return 0;
    var s = String(str).trim();
    var totalSec = 0;
    var hMatch = s.match(/(\d+(?:\.\d+)?)h/);
    if (hMatch) totalSec += parseFloat(hMatch[1]) * 3600;
    var mMatch = s.match(/(\d+(?:\.\d+)?)m(?!s)/);
    if (mMatch) totalSec += parseFloat(mMatch[1]) * 60;
    var sMatch = s.match(/(\d+(?:\.\d+)?)s/);
    if (sMatch) totalSec += parseFloat(sMatch[1]);
    var msMatch = s.match(/(\d+(?:\.\d+)?)ms/);
    if (msMatch) totalSec += parseFloat(msMatch[1]) / 1000;
    if (totalSec === 0) {
      var num = parseFloat(s);
      if (!isNaN(num)) totalSec = num;
    }
    return totalSec;
  }

  function formatDuration(sec) {
    if (sec <= 0) return "0s";
    if (sec < 1) return (sec * 1000).toFixed(0) + "ms";
    if (sec < 60) return sec.toFixed(1) + "s";
    var m = Math.floor(sec / 60);
    var s = (sec % 60).toFixed(0);
    if (m < 60) return m + "m " + (s < 10 ? "0" + s : s) + "s";
    var h = Math.floor(m / 60);
    m = m % 60;
    return h + "h " + m + "m " + (s < 10 ? "0" + s : s) + "s";
  }

  function tickLiveQuota() {
    if (!liveQuotaState) return;
    var now = Date.now();
    var elapsedSec = Math.max(0, (now - liveQuotaState.localReceivedAt) / 1000);

    // 1. Cooldown & Token Linear Refill
    var remCd = Math.max(0, liveQuotaState.totalResetTokensSec - elapsedSec);
    var currentTokens = liveQuotaState.remainingTokens;

    if (liveQuotaState.totalResetTokensSec > 0 && remCd > 0) {
      var fraction = Math.min(1, elapsedSec / liveQuotaState.totalResetTokensSec);
      currentTokens = Math.min(liveQuotaState.limitTokens, Math.round(liveQuotaState.remainingTokens + (liveQuotaState.limitTokens - liveQuotaState.remainingTokens) * fraction));
    } else {
      currentTokens = liveQuotaState.limitTokens;
      remCd = 0;
    }

    // 2. RPM Cooldown
    var remRpmCd = Math.max(0, liveQuotaState.totalResetRequestsSec - elapsedSec);
    var currentRequests = (remRpmCd <= 0) ? liveQuotaState.limitRequests : liveQuotaState.remainingRequests;
    if (remRpmCd <= 0) {
      remRpmCd = 0;
    }

    // Update DOM: TPM Cooldown & Values
    var tpmResetEl = document.getElementById("quota-tpm-reset");
    if (tpmResetEl) {
      tpmResetEl.textContent = remCd > 0 ? "Cooldown: " + formatDuration(remCd) : "Cooldown: 0s";
    }

    var tpmRemEl = document.getElementById("quota-tpm-remaining");
    if (tpmRemEl) {
      tpmRemEl.textContent = Number(currentTokens).toLocaleString() + " / " + Number(liveQuotaState.limitTokens).toLocaleString();
    }

    var tpmPct = Math.max(0, Math.min(100, Math.round((currentTokens / liveQuotaState.limitTokens) * 100)));
    var tpmPctEl = document.getElementById("quota-tpm-percent");
    if (tpmPctEl) tpmPctEl.textContent = tpmPct + "%";

    var tpmFillEl = document.getElementById("quota-tpm-fill");
    if (tpmFillEl) {
      tpmFillEl.style.width = tpmPct + "%";
      tpmFillEl.className = "quota-progress-fill " + (tpmPct < 20 ? "quota-red" : tpmPct < 50 ? "quota-amber" : "quota-green");
    }

    // Update Header Badge
    var htbRemaining = document.getElementById("htb-remaining");
    if (htbRemaining) {
      htbRemaining.textContent = Number(currentTokens).toLocaleString();
    }

    var htbDot = document.getElementById("htb-dot");
    if (htbDot) {
      htbDot.className = "htb-dot " + (tpmPct < 10 ? "offline" : "online");
    }

    var htbBadge = document.getElementById("header-token-badge");
    if (htbBadge) {
      var cdTag = remCd > 0 ? " · Cooldown: " + formatDuration(remCd) : "";
      htbBadge.title = "AI Token Limit: " + Number(currentTokens).toLocaleString() + " / " + Number(liveQuotaState.limitTokens).toLocaleString() + " TPM (" + tpmPct + "% available)" + cdTag;
    }

    // Update DOM: RPM Reset & Values
    var rpmResetEl = document.getElementById("quota-rpm-reset");
    if (rpmResetEl) {
      rpmResetEl.textContent = remRpmCd > 0 ? "Reset: " + formatDuration(remRpmCd) : "Reset: 0s";
    }

    var rpmRemEl = document.getElementById("quota-rpm-remaining");
    if (rpmRemEl) {
      rpmRemEl.textContent = Number(currentRequests).toLocaleString() + " / " + Number(liveQuotaState.limitRequests).toLocaleString();
    }

    var rpmPct = Math.max(0, Math.min(100, Math.round((currentRequests / liveQuotaState.limitRequests) * 100)));
    var rpmPctEl = document.getElementById("quota-rpm-percent");
    if (rpmPctEl) rpmPctEl.textContent = rpmPct + "%";

    var rpmFillEl = document.getElementById("quota-rpm-fill");
    if (rpmFillEl) rpmFillEl.style.width = rpmPct + "%";

    if (backendEl) {
      var tpmK = (currentTokens / 1000).toFixed(1) + "k";
      backendEl.innerHTML = '<span class="dot online"></span>' +
        '<span title="Groq LPU Engine ready &middot; ' + Number(currentTokens).toLocaleString() + ' tokens available">Groq LPU &middot; ' + tpmK + ' TPM</span>';
    }
  }

  function updateQuotaUI(quotaData) {
    if (!quotaData) return;
    var q = quotaData.quota || quotaData;
    var rl = q.ratelimit || {};
    
    var provEl = document.getElementById("quota-provider-name");
    var modelEl = document.getElementById("quota-model-name");
    var statusPill = document.getElementById("quota-status-pill");
    
    if (provEl && q.provider) {
      var p = String(q.provider);
      provEl.textContent = p.includes("Groq") ? "Groq LPU Engine" : p;
    }
    if (modelEl && q.model) {
      var m = String(q.model);
      if (!m.startsWith("groq/") && (q.provider || "").includes("Groq")) {
        m = "groq/" + m;
      }
      modelEl.textContent = m;
    }
    if (statusPill && q.status) {
      var s = String(q.status);
      statusPill.textContent = s.includes("Active") ? "Active" : s;
      statusPill.className = "badge-pill " + (s.includes("Rate") ? "badge-amber" : "badge-green");
    }

    var remTpm = rl.remaining_tokens !== undefined ? rl.remaining_tokens : 8000;
    var limTpm = rl.limit_tokens !== undefined ? rl.limit_tokens : 8000;
    var remRpm = rl.remaining_requests !== undefined ? rl.remaining_requests : 1000;
    var limRpm = rl.limit_requests !== undefined ? rl.limit_requests : 1000;

    var lifeEl = document.getElementById("quota-lifetime-tokens");
    var lifeVal = quotaData.lifetime_tokens !== undefined ? quotaData.lifetime_tokens : q.lifetime_tokens;
    if (lifeEl && lifeVal !== undefined) {
      lifeEl.textContent = Number(lifeVal).toLocaleString();
    }

    // Calculate how much cooldown has already elapsed since updated_at
    var resetTokSec = parseGroqDuration(rl.reset_tokens);
    var resetReqSec = parseGroqDuration(rl.reset_requests);

    var serverUpdatedAt = q.updated_at ? Date.parse(q.updated_at) : Date.now();
    var secondsSinceUpdate = Math.max(0, (Date.now() - serverUpdatedAt) / 1000);
    var adjustedResetTokSec = Math.max(0, resetTokSec - secondsSinceUpdate);
    var adjustedResetReqSec = Math.max(0, resetReqSec - secondsSinceUpdate);

    // Initial linear refill if seconds have elapsed
    if (resetTokSec > 0 && secondsSinceUpdate > 0 && adjustedResetTokSec > 0) {
      var initialFraction = Math.min(1, secondsSinceUpdate / resetTokSec);
      remTpm = Math.min(limTpm, Math.round(remTpm + (limTpm - remTpm) * initialFraction));
    } else if (resetTokSec > 0 && adjustedResetTokSec === 0) {
      remTpm = limTpm;
    }

    liveQuotaState = {
      localReceivedAt: Date.now(),
      remainingTokens: remTpm,
      limitTokens: limTpm,
      totalResetTokensSec: adjustedResetTokSec,
      remainingRequests: remRpm,
      limitRequests: limRpm,
      totalResetRequestsSec: adjustedResetReqSec
    };

    if (!liveTickerTimer) {
      liveTickerTimer = setInterval(tickLiveQuota, 100);
    }
    tickLiveQuota();
  }

  var refreshQuotaBtn = document.getElementById("refresh-quota-btn");
  if (refreshQuotaBtn) {
    refreshQuotaBtn.addEventListener("click", function () {
      refreshQuotaBtn.disabled = true;
      var origHtml = refreshQuotaBtn.innerHTML;
      refreshQuotaBtn.innerHTML = '<span class="spinner-sm" style="width:12px;height:12px;border-width:2px;display:inline-block;vertical-align:middle;margin-right:6px;"></span> <span>Refreshing…</span>';
      fetch("api/limits.php?refresh=1")
        .then(function (r) { return r.json(); })
        .then(function (d) {
          updateQuotaUI(d);
          refreshQuotaBtn.disabled = false;
          refreshQuotaBtn.innerHTML = origHtml;
        })
        .catch(function () {
          refreshQuotaBtn.disabled = false;
          refreshQuotaBtn.innerHTML = origHtml;
        });
    });
  }

  function pingBackend() {
    fetch("api/status.php?ping=1")
      .then(function (r) {
        if (!r.ok) throw new Error("http " + r.status);
        return r.json();
      })
      .then(function (res) {
        renderBackend(!!(res.opencode && res.php), true);
        if (res.quota) updateQuotaUI(res);
      })
      .catch(function () {
        pingAttempts++;
        if (pingAttempts >= 5) {
          renderBackend(false, false);
        }
      });
  }

  // Pre-seed live countdown from initial server-rendered HTML immediately on load
  var initRemEl = document.getElementById("quota-tpm-remaining");
  var initCdEl = document.getElementById("quota-tpm-reset");
  var initRpmRemEl = document.getElementById("quota-rpm-remaining");
  var initRpmResetEl = document.getElementById("quota-rpm-reset");

  if (initRemEl && initCdEl) {
    var parts = initRemEl.textContent.split("/");
    var curT = parts[0] ? parseInt(parts[0].replace(/,/g, ""), 10) : 8000;
    var limT = parts[1] ? parseInt(parts[1].replace(/,/g, ""), 10) : 8000;
    var curCdSec = parseGroqDuration(initCdEl.textContent.replace("Cooldown:", ""));

    var rpmParts = initRpmRemEl ? initRpmRemEl.textContent.split("/") : [];
    var curR = rpmParts[0] ? parseInt(rpmParts[0].replace(/,/g, ""), 10) : 1000;
    var limR = rpmParts[1] ? parseInt(rpmParts[1].replace(/,/g, ""), 10) : 1000;
    var curRpmSec = initRpmResetEl ? parseGroqDuration(initRpmResetEl.textContent.replace("Reset:", "")) : 0;

    liveQuotaState = {
      localReceivedAt: Date.now(),
      remainingTokens: isNaN(curT) ? 8000 : curT,
      limitTokens: isNaN(limT) ? 8000 : limT,
      totalResetTokensSec: curCdSec,
      remainingRequests: isNaN(curR) ? 1000 : curR,
      limitRequests: isNaN(limR) ? 1000 : limR,
      totalResetRequestsSec: curRpmSec
    };

    if (!liveTickerTimer) {
      liveTickerTimer = setInterval(tickLiveQuota, 100);
    }
    tickLiveQuota();
  }

  // Live polling: immediate ping on load, then every 3 seconds for real-time reactivity
  pingBackend();
  pingTimer = setInterval(pingBackend, 3000);

  // Resume or speed up polling when user switches back to this tab
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden) {
      pingBackend();
      if (pingTimer) clearInterval(pingTimer);
      pingTimer = setInterval(pingBackend, 3000);
    } else {
      if (pingTimer) clearInterval(pingTimer);
      pingTimer = setInterval(pingBackend, 10000);
    }
  });

/* ── Deletion Confirmation Card & Toast Notification System ── */
var pendingDeleteId = null;

function showStudioToast(message, type, duration) {
  type = type || "info";
  duration = duration || 3500;
  var container = document.getElementById("studio-toast-container");
  if (!container) {
    container = document.createElement("div");
    container.id = "studio-toast-container";
    container.className = "studio-toast-container";
    container.setAttribute("aria-live", "polite");
    document.body.appendChild(container);
  }

  var toast = document.createElement("div");
  toast.className = "studio-toast toast-" + type;

  var iconSvg = "";
  if (type === "success") {
    iconSvg = '<svg class="toast-icon toast-icon-success" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
  } else if (type === "error") {
    iconSvg = '<svg class="toast-icon toast-icon-error" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
  } else {
    iconSvg = '<svg class="toast-icon toast-icon-info" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
  }

  toast.innerHTML = iconSvg + '<div class="toast-content">' + message + '</div>';
  container.appendChild(toast);

  function dismiss() {
    toast.classList.add("toast-dismissing");
    setTimeout(function () {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 220);
  }

  toast.addEventListener("click", dismiss);
  setTimeout(dismiss, duration);
}

function hideDeleteModal() {
  var modal = document.getElementById("delete-modal");
  if (!modal) return;
  modal.classList.add("is-dismissing");
  setTimeout(function () {
    modal.style.display = "none";
    modal.classList.remove("is-dismissing");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("delete-modal-open");
    pendingDeleteId = null;
  }, 220);
}

function deleteExam(id) {
  pendingDeleteId = id;
  var modal = document.getElementById("delete-modal");
  if (!modal) {
    if (!confirm("Delete this exam and its generated data?")) return;
    executeDeleteExam(id);
    return;
  }

  var row = document.querySelector('tr[data-id="' + id + '"]');
  var examTitle = "Exam #" + id;
  if (row) {
    var tEl = row.querySelector(".exam-title-cell");
    if (tEl && tEl.textContent.trim()) {
      examTitle = tEl.textContent.trim();
    }
  }

  var nameEl = document.getElementById("delete-target-name");
  if (nameEl) nameEl.textContent = examTitle;

  var confirmBtn = document.getElementById("delete-modal-confirm-btn");
  if (confirmBtn) {
    confirmBtn.disabled = false;
    var btnText = confirmBtn.querySelector(".btn-text");
    if (btnText) btnText.textContent = "Delete Exam";
  }

  modal.style.display = "flex";
  modal.classList.remove("is-dismissing");
  modal.setAttribute("aria-hidden", "false");
  document.body.classList.add("delete-modal-open");
}

function executeDeleteExam(id) {
  var confirmBtn = document.getElementById("delete-modal-confirm-btn");
  if (confirmBtn) {
    confirmBtn.disabled = true;
    var btnText = confirmBtn.querySelector(".btn-text");
    if (btnText) btnText.textContent = "Deleting…";
  }

  fetch("api/generate.php?op=delete&id=" + encodeURIComponent(id), { method: "POST" })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (res.ok) {
        hideDeleteModal();
        showStudioToast("Exam deleted successfully", "success", 2400);

        var row = document.querySelector('tr[data-id="' + id + '"]');
        if (row) {
          row.style.transition = "all 0.35s cubic-bezier(0.16, 1, 0.3, 1)";
          row.style.opacity = "0";
          row.style.transform = "translateX(24px) scale(0.98)";
          setTimeout(function () {
            location.reload();
          }, 500);
        } else {
          setTimeout(function () { location.reload(); }, 500);
        }
      } else {
        if (confirmBtn) {
          confirmBtn.disabled = false;
          var bText = confirmBtn.querySelector(".btn-text");
          if (bText) bText.textContent = "Delete Exam";
        }
        hideDeleteModal();
        showStudioToast(res.error || "Delete failed", "error", 4500);
      }
    })
    .catch(function () {
      if (confirmBtn) {
        confirmBtn.disabled = false;
        var bText = confirmBtn.querySelector(".btn-text");
        if (bText) bText.textContent = "Delete Exam";
      }
      hideDeleteModal();
      showStudioToast("Network error: Delete failed", "error", 4500);
    });
}
window.deleteExam = deleteExam;
window.showStudioToast = showStudioToast;

// Bind modal controls
(function initDeleteModalControls() {
  function bind() {
    var cancelBtn = document.getElementById("delete-modal-cancel-btn");
    var closeBtn = document.getElementById("delete-modal-close-btn");
    var backdrop = document.getElementById("delete-modal-backdrop");
    var confirmBtn = document.getElementById("delete-modal-confirm-btn");

    if (cancelBtn) cancelBtn.addEventListener("click", hideDeleteModal);
    if (closeBtn) closeBtn.addEventListener("click", hideDeleteModal);
    if (backdrop) backdrop.addEventListener("click", hideDeleteModal);

    if (confirmBtn) {
      confirmBtn.addEventListener("click", function () {
        if (pendingDeleteId) {
          executeDeleteExam(pendingDeleteId);
        }
      });
    }

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && document.body.classList.contains("delete-modal-open")) {
        hideDeleteModal();
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bind);
  } else {
    bind();
  }
})();

/* ── Hamburger / mobile nav ── */
(function () {
  var btn = document.getElementById("hamburger");
  var drawer = document.getElementById("mobile-nav");
  var header = document.getElementById("site-header");

  if (btn && drawer) {
    btn.addEventListener("click", function () {
      var open = drawer.classList.toggle("open");
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      document.body.style.overflow = open ? "hidden" : "";
    });

    /* Close when any mobile nav link is clicked */
    drawer.querySelectorAll(".mobile-nav-link").forEach(function (link) {
      link.addEventListener("click", function () {
        drawer.classList.remove("open");
        btn.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
      });
    });

    /* Close on outside click */
    document.addEventListener("click", function (e) {
      if (drawer.classList.contains("open") &&
          !drawer.contains(e.target) && !btn.contains(e.target)) {
        drawer.classList.remove("open");
        btn.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
      }
    });
  }

  /* Scroll shadow on header */
  if (header) {
    var onScroll = function () {
      header.classList.toggle("scrolled", window.scrollY > 8);
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }
})();

  /* ── First-Time Robot Tactical Boot Interface Controller ── */
  (function initIntroSplash() {
    var splash = document.getElementById("intro-splash");
    if (!splash) return;

    var forceIntro = window.location.search.includes("intro=1");
    var hasSeen = false;
    try {
      hasSeen = !!sessionStorage.getItem("aem_intro_seen");
    } catch (e) {}

    if (hasSeen && !forceIntro) {
      splash.style.display = "none";
      return;
    }

    var bootBody = document.getElementById("robot-boot-body");
    var statVal = document.getElementById("robot-boot-stat-val");
    var progressLabel = document.getElementById("intro-progress-label");
    var progressPercent = document.getElementById("intro-progress-percent");
    var loaderFill = document.getElementById("intro-loader-fill");

    var dismissed = false;

    function dismissSplash() {
      if (dismissed) return;
      dismissed = true;
      try {
        sessionStorage.setItem("aem_intro_seen", "1");
      } catch (e) {}
      splash.classList.add("is-dismissing");
      setTimeout(function () {
        splash.style.display = "none";
      }, 700);
    }

    splash.addEventListener("click", dismissSplash);
    document.addEventListener("keydown", function (e) {
      if (!dismissed && (e.key === "Escape" || e.key === " " || e.key === "Enter")) {
        dismissSplash();
      }
    });

    var bootSteps = [
      {
        prompt: "01",
        text: "INITIALIZING QUANTUM NEURAL KERNEL v2.0... [OK]",
        progress: 25,
        status: "KERNEL_OK",
        label: "MOUNTING COGNITIVE COMPILER..."
      },
      {
        prompt: "02",
        text: "SYNCHRONIZING GROQ LPU HARDWARE ACCELERATOR... [LPU 940 T/s]",
        progress: 54,
        status: "LPU_ONLINE",
        label: "CALIBRATING BLOOM TAXONOMY ENGINE..."
      },
      {
        prompt: "03",
        text: "CALIBRATING MULTI-TIER DISTRACTOR SYNTHESIZER... [CALIBRATED]",
        progress: 82,
        status: "SYNTH_READY",
        label: "INITIALIZING STUDIO SUBSYSTEMS..."
      },
      {
        prompt: "04",
        text: "ALL COGNITIVE PROTOCOLS ACTIVE. LAUNCHING SYNTHEXAM STUDIO.",
        progress: 100,
        status: "READY",
        label: "SYSTEM READY // ACCESS GRANTED"
      }
    ];

    var currentStep = 0;

    function runNextStep() {
      if (dismissed) return;
      if (currentStep >= bootSteps.length) {
        if (statVal) {
          statVal.textContent = "READY";
          statVal.classList.add("is-ready");
        }
        // Give the user time to appreciate the completed state
        setTimeout(function () {
          dismissSplash();
        }, 850);
        return;
      }

      var step = bootSteps[currentStep];
      var lineDiv = document.createElement("div");
      lineDiv.className = "boot-line";

      var promptSpan = document.createElement("span");
      promptSpan.className = "boot-prompt";
      promptSpan.textContent = ">> [" + step.prompt + "]";

      var textSpan = document.createElement("span");
      textSpan.className = "boot-text";

      var cursorSpan = document.createElement("span");
      cursorSpan.className = "boot-cursor";

      lineDiv.appendChild(promptSpan);
      lineDiv.appendChild(textSpan);
      lineDiv.appendChild(cursorSpan);
      if (bootBody) bootBody.appendChild(lineDiv);

      var fullText = step.text;
      var charIdx = 0;
      var typeSpeed = 16; // Smooth crisp robot typewriter speed

      function typeChar() {
        if (dismissed) return;
        if (charIdx < fullText.length) {
          textSpan.textContent += fullText.charAt(charIdx);
          charIdx++;
          if (typeof playRobotKeyClick === "function" && window.synthExamSfxEnabled) {
            playRobotKeyClick();
          }
          setTimeout(typeChar, typeSpeed);
        } else {
          cursorSpan.remove();
          if (loaderFill) loaderFill.style.width = step.progress + "%";
          if (progressPercent) progressPercent.textContent = step.progress + "%";
          if (progressLabel) progressLabel.textContent = step.label;
          if (statVal) statVal.textContent = step.status;

          currentStep++;
          setTimeout(runNextStep, 260);
        }
      }

      typeChar();
    }

    setTimeout(runNextStep, 350);
  })();

  // ============ Feature Accordion ============
  function initFeatureAccordion() {
    var accordion = document.getElementById("feature-accordion");
    if (!accordion) return;

    var items = accordion.querySelectorAll(".feature-acc-item");
    items.forEach(function (item) {
      var trigger = item.querySelector(".feature-acc-trigger");
      if (!trigger) return;

      trigger.addEventListener("click", function () {
        var isOpen = item.classList.contains("is-open");

        // Close other items (classic accordion behavior)
        items.forEach(function (other) {
          if (other !== item) {
            other.classList.remove("is-open");
            var otherTrigger = other.querySelector(".feature-acc-trigger");
            if (otherTrigger) otherTrigger.setAttribute("aria-expanded", "false");
          }
        });

        // Toggle current item
        if (isOpen) {
          item.classList.remove("is-open");
          trigger.setAttribute("aria-expanded", "false");
        } else {
          item.classList.add("is-open");
          trigger.setAttribute("aria-expanded", "true");
        }
      });
    });
  }
  initFeatureAccordion();

  // ============ Terms & Disclaimer Controller ============
  function initTermsDisclaimer() {
    var modal = document.getElementById("terms-modal");
    if (!modal) return;

    var checkbox = document.getElementById("terms-agree-checkbox");
    var acceptBtn = document.getElementById("terms-accept-btn");
    var footerLink = document.getElementById("footer-terms-link");
    var closeBtn = document.getElementById("terms-close-btn");

    var STORAGE_KEY = "aem_terms_disclaimer_accepted";
    var forceTerms = window.location.search.includes("terms=1");
    var hasAccepted = false;
    try {
      hasAccepted = !!localStorage.getItem(STORAGE_KEY);
    } catch (e) {}

    function showTermsModal() {
      modal.style.display = "flex";
      modal.setAttribute("aria-hidden", "false");
      document.body.classList.add("terms-modal-open");
      if (hasAccepted) {
        if (checkbox) checkbox.checked = true;
        if (acceptBtn) acceptBtn.disabled = false;
        if (closeBtn) closeBtn.style.display = "flex";
      } else {
        if (checkbox) checkbox.checked = false;
        if (acceptBtn) acceptBtn.disabled = true;
        if (closeBtn) closeBtn.style.display = "none";
      }
    }

    function hideTermsModal() {
      modal.classList.add("is-dismissing");
      setTimeout(function () {
        modal.style.display = "none";
        modal.classList.remove("is-dismissing");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("terms-modal-open");
      }, 240);
    }

    if (checkbox && acceptBtn) {
      checkbox.addEventListener("change", function () {
        acceptBtn.disabled = !checkbox.checked;
      });

      acceptBtn.addEventListener("click", function () {
        if (!checkbox.checked) return;
        try {
          localStorage.setItem(STORAGE_KEY, "1");
        } catch (e) {}
        hasAccepted = true;
        hideTermsModal();
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        if (hasAccepted) {
          hideTermsModal();
        }
      });
    }

    if (footerLink) {
      footerLink.addEventListener("click", function (e) {
        e.preventDefault();
        showTermsModal();
      });
    }

    // Auto-show upon loading if not yet accepted, or if ?terms=1
    if (!hasAccepted || forceTerms) {
      var splash = document.getElementById("intro-splash");
      var splashActive = splash && splash.style.display !== "none" && !document.documentElement.classList.contains("intro-skipped");

      if (splashActive) {
        setTimeout(function () {
          showTermsModal();
        }, 1900);
      } else {
        setTimeout(function () {
          showTermsModal();
        }, 120);
      }
    }
  }

  /* ── Collapsible Exams Table Controller (Show first 5, collapse the rest) ── */
  function initExamsCollapsible() {
    var toggleBtn = document.getElementById("exams-toggle-btn");
    if (!toggleBtn) return;

    var extraRows = document.querySelectorAll(".exam-row-extra");
    if (!extraRows.length) return;

    var count = parseInt(toggleBtn.getAttribute("data-count"), 10) || extraRows.length;
    var textEl = toggleBtn.querySelector(".exams-toggle-text");
    var badgeEl = toggleBtn.querySelector(".exams-toggle-badge");

    // If an active job (queued or running) is within extra rows, auto-expand so user can track it immediately
    var hasActiveExtraRow = false;
    extraRows.forEach(function (row) {
      if (row.querySelector('.badge[data-status="running"], .badge[data-status="queued"]')) {
        hasActiveExtraRow = true;
      }
    });

    function setExpanded(expanded) {
      extraRows.forEach(function (row) {
        row.classList.toggle("is-collapsed", !expanded);
      });
      toggleBtn.setAttribute("aria-expanded", expanded ? "true" : "false");
      if (textEl) {
        textEl.textContent = expanded ? "Show Less" : ("Show " + count + " More " + (count === 1 ? "Exam" : "Exams"));
      }
      if (badgeEl) {
        badgeEl.style.display = expanded ? "none" : "inline-flex";
      }
    }

    if (hasActiveExtraRow) {
      setExpanded(true);
    }

    toggleBtn.addEventListener("click", function () {
      var isExpanded = toggleBtn.getAttribute("aria-expanded") === "true";
      setExpanded(!isExpanded);
    });
  }
  initExamsCollapsible();

  /* ============================================================
     ROBOT GAME TELEMETRY CONSOLE CONTROLLER
     ============================================================ */
  function initRobotConsole() {
    var textEl = document.getElementById("robot-typewriter-text");
    var barEl = document.getElementById("robot-terminal-bar");
    updateAllSfxButtonsUI();

    if (!textEl) return;

    var ROBOT_LINES = [
      "Ingestion protocol active \u2014 Upload PDF to initiate cognitive parsing.",
      "Neural core LPU engine standby \u2014 High-throughput inference ready.",
      "Chapter topology mapping \u2014 Recognizing structured curricular blocks.",
      "Deep distractor auditor online \u2014 Rigorous rationale extraction.",
      "Dual testing runtimes ready \u2014 Tutor mode and timed examination secured.",
      "Zero external telemetry \u2014 100% private local execution verified."
    ];

    var currentLineIndex = 0;
    var isRunning = true;
    var timer = null;

    function nextLine() {
      if (!isRunning) return;
      var targetText = ROBOT_LINES[currentLineIndex];
      currentLineIndex = (currentLineIndex + 1) % ROBOT_LINES.length;

      var charIdx = 0;
      textEl.textContent = "";

      function typeChar() {
        if (!isRunning) return;
        if (charIdx < targetText.length) {
          var ch = targetText.charAt(charIdx);
          textEl.textContent += ch;
          charIdx++;
          var jitter = 22 + Math.random() * 16;
          if (ch === " " || ch === "\u2014" || ch === "\u2022") jitter += 30;
          timer = setTimeout(typeChar, jitter);
        } else {
          // Pause on complete sentence
          timer = setTimeout(eraseLine, 3200);
        }
      }

      function eraseLine() {
        if (!isRunning) return;
        var current = textEl.textContent;
        if (current.length > 0) {
          textEl.textContent = current.slice(0, -1);
          timer = setTimeout(eraseLine, 10);
        } else {
          timer = setTimeout(nextLine, 300);
        }
      }

      typeChar();
    }

    if (barEl) {
      barEl.addEventListener("click", function () {
        if (timer) clearTimeout(timer);
        currentLineIndex = (currentLineIndex + 1) % ROBOT_LINES.length;
        textEl.textContent = "";
        nextLine();
      });
    }

    // Start cycling
    setTimeout(nextLine, 400);

    // Also typewriter the studio description on load once
    var descEl = document.getElementById("studio-robot-desc");
    if (descEl && !descEl.dataset.typed) {
      descEl.dataset.typed = "1";
      var originalDesc = descEl.textContent.trim();
      typewriteWithCursor(descEl, originalDesc, 14);
    }

    // Typewrite / decode section titles on page load
    var secTitles = document.querySelectorAll(".robot-typewrite-title");
    secTitles.forEach(function (el, idx) {
      var orig = el.getAttribute("data-original") || el.textContent.trim();
      el.dataset.typed = "1";
      setTimeout(function () {
        typewriteWithCursor(el, orig, 22);
      }, 700 + idx * 450);
    });

    // Global SFX toggle handler & tactile click feedback on all interactive elements
    document.addEventListener("click", function (e) {
      // 1. Toggle SFX if clicking any SFX button
      var sfxToggle = e.target.closest(".robot-sfx-toggle, #robot-sfx-btn, #header-sfx-btn");
      if (sfxToggle) {
        e.preventDefault();
        e.stopPropagation();
        setSfxState(!sfxEnabled, true);
        return;
      }

      // 2. Play tactile click sound on any interactive UI element
      var interactive = e.target.closest(
        "button, a, input, select, textarea, label, [role='button'], " +
        ".dropzone, .feature-acc-trigger, .exams-toggle-btn, .dfs-remove-btn, " +
        ".custom-modal-cancel-btn, .custom-modal-close-btn, .nav-link, .print-btn, .download-btn"
      );
      if (interactive) {
        playTactileClickSound();
      }
    });
  } // close initRobotConsole

  /* ============================================================
     CYBER SNAKE / POINTER TRAIL ANIMATION  (performance-optimised)
     ============================================================ */
  function initPointerSnake() {
    var canvas = document.getElementById("pointer-snake-canvas");
    if (!canvas) return;

    // Skip on touch / coarse-pointer devices (already handled by CSS but guard here too)
    if (window.matchMedia && window.matchMedia("(hover: none)").matches) return;

    // ── Low-end device detection ─────────────────────────────────────────────
    // hardwareConcurrency ≤ 2 or deviceMemory ≤ 1 GB → disable trail entirely
    // hardwareConcurrency ≤ 4 → lite mode (fewer segments, no glow shadow)
    var cores  = navigator.hardwareConcurrency || 4;
    var mem    = navigator.deviceMemory        || 4;  // GB, only in Chromium
    if (cores <= 2 || mem <= 1) {
      canvas.style.display = "none";
      return;   // Don't even start the RAF loop
    }
    var isLite = (cores <= 4 || mem <= 2);

    var ctx = canvas.getContext("2d");
    if (!ctx) return;

    // ── Canvas sizing – cap DPR at 1.5 to halve pixel-fill on hi-DPI screens ─
    var width, height;
    var MAX_DPR = isLite ? 1 : 1.5;

    function resizeCanvas() {
      width  = window.innerWidth;
      height = window.innerHeight;
      var dpr = Math.min(window.devicePixelRatio || 1, MAX_DPR);
      canvas.width  = Math.floor(width  * dpr);
      canvas.height = Math.floor(height * dpr);
      canvas.style.width  = width  + "px";
      canvas.style.height = height + "px";
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    window.addEventListener("resize", resizeCanvas);
    resizeCanvas();

    // Fewer segments in lite mode (saves ~50% of all per-segment math)
    var NUM_SEGMENTS   = isLite ? 8 : 14;
    var MAX_SEGMENT_DIST = 5;
    var segments = [];
    for (var s = 0; s < NUM_SEGMENTS; s++) {
      segments.push({ x: -200, y: -200 });
    }

    var mouseX = -200, mouseY = -200;
    var isInitialized = false;
    var isVisible     = false;
    var isHovering    = false;
    var hoverProgress = 0;
    var clickPulse    = 0;
    var isIdle        = false;
    var idleAlpha     = 1;
    var idleTimer     = null;
    var rafId         = null;

    // ── Throttled mousemove: only record position, skip duplicate coords ─────
    var lastMX = -200, lastMY = -200;
    window.addEventListener("mousemove", function (e) {
      var nx = e.clientX, ny = e.clientY;
      // Ignore sub-pixel jitter
      if (Math.abs(nx - lastMX) < 1 && Math.abs(ny - lastMY) < 1) return;
      lastMX = mouseX = nx;
      lastMY = mouseY = ny;

      if (!isInitialized) {
        for (var i = 0; i < NUM_SEGMENTS; i++) {
          segments[i].x = mouseX;
          segments[i].y = mouseY;
        }
        isInitialized = true;
      }

      if (!isVisible) {
        isVisible = true;
        canvas.classList.add("is-active");
        // Resume RAF loop when mouse re-enters
        if (!rafId) rafId = requestAnimationFrame(updateSnake);
      }

      isIdle = false;
      clearTimeout(idleTimer);
      idleTimer = setTimeout(function () { isIdle = true; }, 1600);
    }, { passive: true });

    document.addEventListener("mouseleave", function () {
      isVisible = false;
      canvas.classList.remove("is-active");
    });

    document.addEventListener("mouseenter", function () {
      isVisible = true;
      canvas.classList.add("is-active");
      if (!rafId) rafId = requestAnimationFrame(updateSnake);
    });

    document.addEventListener("mouseover", function (e) {
      var target = e.target.closest(
        "button, a, input, select, textarea, label, [role='button'], .card, .feature-acc-trigger, .dropzone, .terms-checkbox-custom, .count-pill"
      );
      isHovering = !!target;
    }, { passive: true });

    window.addEventListener("mousedown", function () { clickPulse = 1.0; });

    // ── Pre-computed per-segment constants (avoid per-frame division) ─────────
    var SEG_T     = new Float32Array(NUM_SEGMENTS);
    var SEG_INV_T = new Float32Array(NUM_SEGMENTS);
    for (var k = 0; k < NUM_SEGMENTS; k++) {
      SEG_T[k]     = k / (NUM_SEGMENTS - 1);
      SEG_INV_T[k] = 1 - SEG_T[k];
    }

    function updateSnake() {
      rafId = null; // cleared — will be re-scheduled below if still active

      // ── State updates always run (even when not drawing) so fades finish ──
      if (isVisible && isInitialized) {
        segments[0].x += (mouseX - segments[0].x) * 0.75;
        segments[0].y += (mouseY - segments[0].y) * 0.75;

        for (var i = 1; i < NUM_SEGMENTS; i++) {
          segments[i].x += (segments[i - 1].x - segments[i].x) * 0.62;
          segments[i].y += (segments[i - 1].y - segments[i].y) * 0.62;

          var dx   = segments[i].x - segments[i - 1].x;
          var dy   = segments[i].y - segments[i - 1].y;
          var dist = Math.sqrt(dx * dx + dy * dy);
          if (dist > MAX_SEGMENT_DIST) {
            var ratio = MAX_SEGMENT_DIST / dist;
            segments[i].x = segments[i - 1].x + dx * ratio;
            segments[i].y = segments[i - 1].y + dy * ratio;
          }
        }

        hoverProgress += ((isHovering ? 1 : 0) - hoverProgress) * 0.15;
      }

      // Idle alpha fades even after mouse leaves so the trail disappears cleanly
      if (isIdle || !isVisible) {
        idleAlpha = Math.max(0, idleAlpha - 0.025);
      } else {
        idleAlpha = Math.min(1, idleAlpha + 0.08);
      }

      if (clickPulse > 0.01) { clickPulse *= 0.88; } else { clickPulse = 0; }

      // ── If fully faded AND mouse left — stop the loop entirely ───────────
      if (idleAlpha < 0.005 && !isVisible) {
        ctx.clearRect(0, 0, width, height);
        return; // No reschedule — RAF stays dormant until next mouseenter
      }

      ctx.clearRect(0, 0, width, height);

      if (idleAlpha > 0.005 && isInitialized) {
        var r = Math.round(52  + (16  - 52)  * hoverProgress);
        var g = Math.round(211 + (185 - 211) * hoverProgress);
        var b = Math.round(153 + (129 - 153) * hoverProgress);

        // ── 1. Outer comet trail – single gradient-like path per segment ─────
        //    No save/restore in loop. shadowBlur intentionally REMOVED from
        //    the segment loop (biggest GPU drain). Shadow only on the nucleus.
        ctx.lineCap  = "round";
        ctx.lineJoin = "round";
        ctx.shadowBlur = 0; // ensure no leftover shadow state

        for (var j = 0; j < NUM_SEGMENTS - 1; j++) {
          var inv_t      = SEG_INV_T[j];
          var trailWidth = inv_t * (5.5 + clickPulse * 2.5) + 0.8;
          var trailAlpha = Math.pow(inv_t, 1.25) * 0.8 * idleAlpha;

          ctx.beginPath();
          ctx.moveTo(segments[j].x, segments[j].y);
          ctx.lineTo(segments[j + 1].x, segments[j + 1].y);
          ctx.lineWidth   = trailWidth;
          ctx.strokeStyle = "rgba(" + r + "," + g + "," + b + "," + trailAlpha + ")";
          ctx.stroke();
        }

        // ── 2. Inner white-hot core (lite mode skips this pass) ────────────
        if (!isLite) {
          for (var m = 0; m < NUM_SEGMENTS - 1; m++) {
            var cinv_t    = SEG_INV_T[m];
            var coreWidth = cinv_t * (2.2 + clickPulse * 1.2) + 0.4;
            var coreAlpha = Math.pow(cinv_t, 1.6) * 0.95 * idleAlpha;

            ctx.beginPath();
            ctx.moveTo(segments[m].x, segments[m].y);
            ctx.lineTo(segments[m + 1].x, segments[m + 1].y);
            ctx.lineWidth   = coreWidth;
            ctx.strokeStyle = "rgba(255,255,255," + coreAlpha + ")";
            ctx.stroke();
          }
        }

        // ── 3. Nucleus — shadow only here (one draw, not 28) ───────────────
        ctx.beginPath();
        ctx.arc(segments[0].x, segments[0].y, 2.5 + clickPulse * 1.5, 0, Math.PI * 2);
        ctx.fillStyle  = "rgba(255,255,255," + (0.98 * idleAlpha) + ")";
        if (!isLite) {
          ctx.shadowBlur  = 10;
          ctx.shadowColor = "#ffffff";
        }
        ctx.fill();
        ctx.shadowBlur = 0; // reset so it doesn't bleed into next frame

        // ── 4. Click shockwave (lite mode skips) ──────────────────────────
        if (!isLite && clickPulse > 0.05) {
          var shockRadius = 5 + (1 - clickPulse) * 22;
          ctx.beginPath();
          ctx.arc(segments[0].x, segments[0].y, shockRadius, 0, Math.PI * 2);
          ctx.strokeStyle = "rgba(255,255,255," + (clickPulse * 0.85 * idleAlpha) + ")";
          ctx.lineWidth   = 1.5;
          ctx.stroke();
        }
      }

      rafId = requestAnimationFrame(updateSnake);
    }

    // Start only on first real mouse move (listener above) to avoid idle RAF
    // For immediate first frame if cursor is already on page:
    if (isVisible) rafId = requestAnimationFrame(updateSnake);
  }

  window.initPointerSnake = initPointerSnake;

  initRobotConsole();
  initPointerSnake();
  initTermsDisclaimer();
})();