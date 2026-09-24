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
      liveText.textContent = res.message || (res.status === "queued" ? "Queued \u2014 starting shortly\u2026" : "Generating with opencode\u2026");
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
})();

function deleteExam(id) {
  if (!confirm("Delete this exam and its generated data?")) return;
  fetch("api/generate.php?op=delete&id=" + encodeURIComponent(id), { method: "POST" })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (res.ok) location.reload();
      else alert(res.error || "Delete failed");
    })
    .catch(function () { alert("Delete failed"); });
}

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

  /* ── First-Time Opening Splash Intro Controller ── */
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

    var statusText = document.getElementById("intro-status-text");
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
    }, { once: true });

    // Staged status updates & auto-dismiss
    setTimeout(function () {
      if (!dismissed && statusText) {
        statusText.textContent = "Loading Groq LPU Models\u2026";
      }
    }, 750);

    setTimeout(function () {
      if (!dismissed && statusText) {
        statusText.textContent = "Studio Ready";
        statusText.style.color = "#34d399";
      }
    }, 1350);

    setTimeout(function () {
      if (!dismissed) {
        dismissSplash();
      }
    }, 1750);
  })();
})();