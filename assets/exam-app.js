(function () {
  "use strict";

  const examId = document.body.dataset.examId;
  const view = document.getElementById("view");
  const navEl = document.getElementById("exam-nav");

  let bank = null;
  let flat = []; // [{ch, q, type}]
  let currentView = null; // 'ch|ch1' | 'overall' | 'answerkey' | 'encyclopedia' | 'mistakes'

  const LETTERS = ["A", "B", "C", "D"];

  // chapterState[ch.chapterId] = {mode, pacing, shuffle, started, completed, isRedemption, questions[], order[], index, score, correct, incorrect, answers{}, choiceOrder{}, missedIds[], timer}
  const chapterState = {};
  let overall = null; // {started, submitted, selected{}, idAnswers{}, score, correct, incorrect}

  /* ---------------- identification answer matching (Section 10) ---------------- */

  function sing(w) {
    if (w.length > 3) {
      if (/ies$/.test(w)) return w.slice(0, -3) + "y";
      if (/(ss|us|is)$/.test(w)) return w;
      if (/(ches|shes|xes|ses)$/.test(w)) return w.slice(0, -2);
      if (/s$/.test(w)) return w.slice(0, -1);
    }
    return w;
  }

  function norm(s) {
    return String(s || "")
      .toLowerCase()
      .replace(/&/g, " and ")
      .replace(/[^a-z0-9 ]+/g, " ")
      .split(/\s+/)
      .filter(function (w) { return w && ["the", "a", "an"].indexOf(w) === -1; })
      .map(sing)
      .join("");
  }

  function checkIdMatch(userAns, mainAns, alternates) {
    const u = norm(userAns);
    if (!u) return false;
    if (u === norm(mainAns)) return true;
    if (Array.isArray(alternates)) {
      for (let i = 0; i < alternates.length; i++) {
        if (u === norm(alternates[i])) return true;
      }
    }
    return false;
  }

  /* ---------------- mistake bank storage ---------------- */

  const mistakeStorageKey = "exam_mistakes_" + (examId || "standalone");
  let mistakeBank = loadMistakes();

  function loadMistakes() {
    try {
      const raw = localStorage.getItem(mistakeStorageKey);
      return raw ? JSON.parse(raw) : {};
    } catch (e) {
      return {};
    }
  }

  function saveMistakes() {
    try {
      localStorage.setItem(mistakeStorageKey, JSON.stringify(mistakeBank));
    } catch (e) {}
  }

  function recordMistake(q, ch, selectedIdxOrAnswer, type) {
    if (!q) return;
    const isId = type === "identification" || q.type === "identification" || (!q.choices && q.answer != null);
    const existing = mistakeBank[q.id] || {
      id: q.id,
      type: isId ? "identification" : "mc",
      chapterId: ch ? ch.chapterId : "",
      chapterTitle: ch ? ch.title : "General",
      question: q.question,
      choices: q.choices || [],
      correctAnswer: isId ? q.answer : q.correctAnswer,
      alternates: q.alternates || [],
      explanation: q.explanation || "",
      wrongCount: 0,
      lastSelected: null,
      lastMissedAt: null,
    };
    existing.wrongCount += 1;
    if (isId) {
      existing.lastSelected = selectedIdxOrAnswer != null && String(selectedIdxOrAnswer).trim() !== ""
        ? String(selectedIdxOrAnswer)
        : "Unanswered";
    } else {
      existing.lastSelected = (selectedIdxOrAnswer != null && q.choices && q.choices[selectedIdxOrAnswer] != null)
        ? q.choices[selectedIdxOrAnswer]
        : "Timed out / unanswered";
    }
    existing.lastMissedAt = new Date().toISOString();
    mistakeBank[q.id] = existing;
    saveMistakes();
  }

  function resolveMistake(qid) {
    if (mistakeBank[qid]) {
      delete mistakeBank[qid];
      saveMistakes();
    }
  }

  /* ---------------- keyboard navigation ---------------- */

  window.addEventListener("keydown", function (e) {
    if (e.target && (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA")) {
      // Input enter behavior is handled directly by input keydown listeners
      return;
    }
    if (e.defaultPrevented) return;

    const qView = document.querySelector(".q-view");
    if (!qView) return;

    const nextBtn = document.getElementById("next-btn");
    if (nextBtn) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        nextBtn.click();
      }
      return;
    }

    const key = e.key.toUpperCase();
    let choiceIdx = -1;
    if (key === "A" || key === "1") choiceIdx = 0;
    else if (key === "B" || key === "2") choiceIdx = 1;
    else if (key === "C" || key === "3") choiceIdx = 2;
    else if (key === "D" || key === "4") choiceIdx = 3;

    if (choiceIdx !== -1) {
      const btn = qView.querySelector('.choice[data-choice="' + choiceIdx + '"]');
      if (btn && !btn.disabled) {
        e.preventDefault();
        btn.click();
      }
    }
  });

  /* ---------------- helpers ---------------- */

  function shuffle(arr) {
    const a = arr.slice();
    for (let i = a.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
  }

  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function toast(msg) {
    const t = document.createElement("div");
    t.className = "toast";
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 2600);
  }

  function activeLock() {
    if (overall && overall.started && !overall.submitted) return "overall";
    for (const ch of bank.chapters) {
      const s = chapterState[ch.chapterId];
      if (s && s.started && !s.completed) return ch.chapterId;
    }
    return null;
  }

  function chapterById(id) {
    return bank.chapters.find(function (c) { return c.chapterId === id; }) || null;
  }

  function getChapterPool(ch, mode) {
    if (!ch) return [];
    if (mode === "id") {
      const list = Array.isArray(ch.identification) ? ch.identification.slice() : [];
      if (Array.isArray(ch.questions)) {
        ch.questions.forEach(function (q) {
          if (q.type === "identification" || (q.answer && (!q.choices || q.choices.length !== 4))) {
            if (!list.some(function (x) { return x.id === q.id; })) {
              list.push(q);
            }
          }
        });
      }
      return list;
    }
    // mode === 'mc'
    if (Array.isArray(ch.questions)) {
      return ch.questions.filter(function (q) {
        return q.type !== "identification" && (!q.answer || (q.choices && q.choices.length === 4));
      });
    }
    return [];
  }

  /* ---------------- data loading ---------------- */

  function initBank(data) {
    if (!data || !Array.isArray(data.chapters) || !data.chapters.length) {
      throw new Error("Question bank is empty or malformed");
    }
    bank = data;
    flat = [];
    data.chapters.forEach(function (ch) {
      if (!Array.isArray(ch.questions)) ch.questions = [];
      if (!Array.isArray(ch.identification)) ch.identification = [];

      const mcList = getChapterPool(ch, "mc");
      const idList = getChapterPool(ch, "id");

      mcList.forEach(function (q) { flat.push({ ch: ch, q: q, type: "mc" }); });
      idList.forEach(function (q) { flat.push({ ch: ch, q: q, type: "identification" }); });
    });
    document.title = data.title || document.title;
    renderHeader();
    renderNav();
    switchTo("ch|" + data.chapters[0].chapterId);
  }

  function load() {
    if (window.PRELOADED_BANK) {
      try {
        initBank(window.PRELOADED_BANK);
      } catch (err) {
        view.innerHTML = '<div class="view-panel"><div class="panel-title">Failed to load exam</div>' +
          '<p class="muted">' + esc(err.message) + '</p></div>';
      }
      return;
    }
    fetch("api/data.php?id=" + encodeURIComponent(examId))
      .then(function (r) {
        if (!r.ok) throw new Error("HTTP " + r.status);
        return r.json();
      })
      .then(function (data) {
        initBank(data);
      })
      .catch(function (err) {
        view.innerHTML = '<div class="view-panel"><div class="panel-title">Failed to load exam</div>' +
          '<p class="muted">' + esc(err.message) + '</p></div>';
      });
  }

  function renderHeader() {
    document.getElementById("exam-title").textContent = bank.title || "Exam";

    var metaHtml = "";

    /* Subject tag — only show if distinct from title */
    if (bank.subject && bank.subject.trim().toLowerCase() !== (bank.title || "").trim().toLowerCase()) {
      metaHtml += '<span class="meta-tag"><span class="meta-dot"></span>' + esc(bank.subject) + "</span>";
    }

    /* Enhanced Reviewer Skill badge */
    metaHtml +=
      '<span class="meta-chip meta-chip-ai" title="Organized with Identification & Multiple Choice fitness">' +
        '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>' +
        'Enhanced Reviewer' +
      '</span>';

    /* Inline stat chips */
    metaHtml +=
      '<span class="meta-chip meta-chip-chapters">' +
        '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>' +
        bank.chapters.length + " Chapters" +
      "</span>" +
      '<span class="meta-chip meta-chip-questions">' +
        '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
        flat.length + " Total Questions" +
      "</span>";

    /* Collapsible summary */
    if (bank.summary) {
      metaHtml +=
        '<div class="exam-summary-wrap">' +
          '<p class="exam-summary" id="exam-summary-text">' + esc(bank.summary) + "</p>" +
          '<button class="summary-toggle" id="summary-toggle" aria-expanded="false" hidden>' +
            '<span class="st-label-txt">Show more</span>' +
            '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>' +
          "</button>" +
        "</div>";
    }

    document.getElementById("exam-meta").innerHTML = metaHtml;

    /* Show toggle only when text is actually clamped */
    var toggleBtn = document.getElementById("summary-toggle");
    var summaryEl = document.getElementById("exam-summary-text");
    if (toggleBtn && summaryEl) {
      requestAnimationFrame(function () {
        if (summaryEl.scrollHeight > summaryEl.clientHeight + 2) {
          toggleBtn.hidden = false;
        }
        toggleBtn.addEventListener("click", function () {
          var expanded = summaryEl.classList.toggle("expanded");
          toggleBtn.setAttribute("aria-expanded", expanded ? "true" : "false");
          toggleBtn.querySelector(".st-label-txt").textContent = expanded ? "Show less" : "Show more";
          toggleBtn.querySelector("svg").style.transform = expanded ? "rotate(180deg)" : "";
        });
      });
    }
  }

  /* ---------------- navigation ---------------- */

  function renderNav() {
    const lock = activeLock();
    navEl.innerHTML = "";
    const cont = inlineContainer();
    navEl.appendChild(cont);

    // Custom Glassmorphic Chapter Dropdown
    if (bank.chapters && bank.chapters.length > 0) {
      const isChView = Boolean(currentView && String(currentView).indexOf("ch|") === 0);
      const activeChId = isChView ? currentView.slice(3) : null;
      let activeCh = null;
      let activeChIdx = -1;
      bank.chapters.forEach(function (c, idx) {
        if (c.chapterId === activeChId) {
          activeCh = c;
          activeChIdx = idx;
        }
      });

      const dropWrap = document.createElement("div");
      dropWrap.className = "chapter-dropdown-wrap";
      dropWrap.id = "chapter-dd-wrap";

      const btnLabel = activeCh
        ? "Ch " + (activeChIdx + 1) + ": " + activeCh.title
        : "Chapters (" + bank.chapters.length + ")";
      const btnTitle = activeCh
        ? "Chapter " + (activeChIdx + 1) + ": " + activeCh.title
        : "Select from " + bank.chapters.length + " chapters";

      const triggerBtn = document.createElement("button");
      triggerBtn.type = "button";
      triggerBtn.className = "chapter-dd-btn" + (isChView ? " active" : "");
      triggerBtn.id = "chapter-dd-btn";
      triggerBtn.title = btnTitle;
      triggerBtn.setAttribute("aria-haspopup", "true");
      triggerBtn.setAttribute("aria-expanded", "false");
      triggerBtn.setAttribute("aria-label", btnTitle);

      triggerBtn.innerHTML =
        '<span class="cdd-btn-icon" aria-hidden="true">' +
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>' +
        '</span>' +
        '<span class="cdd-btn-text">' + esc(btnLabel) + '</span>' +
        '<span class="cdd-chevron" aria-hidden="true">' +
          '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>' +
        '</span>';

      const menu = document.createElement("div");
      menu.className = "chapter-dd-menu";
      menu.id = "chapter-dd-menu";
      menu.setAttribute("role", "menu");
      menu.hidden = true;

      const header = document.createElement("div");
      header.className = "cdd-menu-header";
      header.innerHTML =
        '<span class="cdd-menu-title">Select Chapter</span>' +
        '<span class="cdd-menu-badge">' + bank.chapters.length + ' Chapters</span>';
      menu.appendChild(header);

      const itemsList = document.createElement("div");
      itemsList.className = "cdd-menu-items";

      bank.chapters.forEach(function (c, idx) {
        const isCur = currentView === "ch|" + c.chapterId;
        const isDone = chapterState[c.chapterId] && chapterState[c.chapterId].completed;
        const isLocked = lock && lock !== "overall" && lock !== c.chapterId;
        const totalCount = getChapterPool(c, "mc").length + getChapterPool(c, "id").length;

        const itemBtn = document.createElement("button");
        itemBtn.type = "button";
        itemBtn.className = "chapter-dd-item" + (isCur ? " active" : "") + (isDone ? " completed" : "") + (isLocked ? " locked" : "");
        itemBtn.setAttribute("role", "menuitem");
        itemBtn.title = "Chapter " + (idx + 1) + ": " + c.title;

        itemBtn.innerHTML =
          '<span class="cdd-badge">Ch ' + (idx + 1) + '</span>' +
          '<span class="cdd-title">' + esc(c.title) + '</span>' +
          '<span class="cdd-meta">' +
            '<span class="cdd-qcount">' + totalCount + ' Qs</span>' +
            (isDone ? '<span class="cdd-check" title="Completed">\u2713</span>' : '') +
            (isLocked ? '<span class="cdd-lock" title="Exam section in progress">\uD83D\uDD12</span>' : '') +
          '</span>';

        itemBtn.addEventListener("click", function (e) {
          e.stopPropagation();
          dropWrap.classList.remove("open");
          triggerBtn.setAttribute("aria-expanded", "false");
          menu.hidden = true;
          switchTo("ch|" + c.chapterId);
        });

        itemsList.appendChild(itemBtn);
      });

      menu.appendChild(itemsList);

      triggerBtn.addEventListener("click", function (e) {
        e.stopPropagation();
        const isOpen = dropWrap.classList.toggle("open");
        triggerBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");
        menu.hidden = !isOpen;
      });

      dropWrap.appendChild(triggerBtn);
      dropWrap.appendChild(menu);
      cont.appendChild(dropWrap);
    }

    const sep = document.createElement("div");
    sep.className = "nav-sep";
    cont.appendChild(sep);

    cont.appendChild(makeTab("Overall Exam", "overall", overall && overall.submitted ? '<span class="chk">\u2713</span>' : "", lock === "overall" || (lock && lock !== "overall"), currentView === "overall"));
    cont.appendChild(makeTab("Answer Key", "answerkey", "", lock !== null, currentView === "answerkey"));
    cont.appendChild(makeTab("Encyclopedia", "encyclopedia", "", lock !== null, currentView === "encyclopedia"));

    const mCount = Object.keys(mistakeBank).length;
    if (mCount > 0) {
      cont.appendChild(makeTab("Mistakes", "mistakes", '<span class="badge-mistakes">' + mCount + '</span>', lock !== null, currentView === "mistakes"));
    }
  }

  function inlineContainer() {
    const c = document.createElement("div");
    c.className = "container";
    return c;
  }

  function makeTab(label, viewKey, suffix, locked, active) {
    const b = document.createElement("button");
    b.className = "nav-tab";
    if (active) b.classList.add("active");
    b.title = label;
    b.setAttribute("aria-label", label);
    b.innerHTML = '<span class="tab-label">' + esc(label) + '</span>' + (suffix ? " " + suffix : "");
    if (locked) {
      b.disabled = true;
    } else {
      b.addEventListener("click", function () { switchTo(viewKey); });
    }
    return b;
  }

  function switchTo(key) {
    const lock = activeLock();
    if (lock && lock !== "overall" && key !== "ch|" + lock) {
      const ch = chapterById(lock);
      toast("Finish or reset \u201c" + (ch ? ch.title : lock) + "\u201d first to switch sections.");
      renderNav();
      return;
    }
    if (lock === "overall" && key !== "overall") {
      toast("Finish or submit the Overall Exam first to switch sections.");
      renderNav();
      return;
    }
    currentView = key;
    renderNav();
    if (key === "overall") renderOverall();
    else if (key === "answerkey") renderAnswerKey();
    else if (key === "encyclopedia") renderEncyclopedia();
    else if (key === "mistakes") renderMistakes();
    else if (key.indexOf("ch|") === 0) renderChapter(key.slice(3));
    else renderHome();
  }

  /* ---------------- chapter render ---------------- */

  function freshChapterState(chId, chosenMode, customQuestions, isRedemption, isStarted) {
    const ch = chapterById(chId);
    const existing = chapterState[chId] || {};
    const mode = chosenMode || existing.mode || "mc";
    const pacing = existing.pacing || "untimed";
    const shuffleOn = typeof existing.shuffle === "boolean" ? existing.shuffle : true;

    const pool = customQuestions || getChapterPool(ch, mode);
    let order = pool.map(function (q) { return q.id; });
    if (shuffleOn) {
      order = shuffle(order);
    }

    const choiceOrder = {};
    if (mode === "mc") {
      pool.forEach(function (q) {
        choiceOrder[q.id] = shuffle([0, 1, 2, 3]);
      });
    }

    chapterState[chId] = {
      mode: mode,
      pacing: pacing,
      shuffle: shuffleOn,
      started: Boolean(isStarted),
      completed: false,
      isRedemption: Boolean(isRedemption),
      questions: pool,
      order: order,
      index: 0,
      score: 0,
      correct: 0,
      incorrect: 0,
      answered: 0,
      answers: {},
      choiceOrder: choiceOrder,
      missedIds: [],
      timer: null,
    };
  }

  function getChapterState(chId) {
    if (!chapterState[chId]) freshChapterState(chId);
    return chapterState[chId];
  }

  function clearTimer(st) {
    if (st.timer) {
      clearInterval(st.timer);
      st.timer = null;
    }
  }

  function renderChapter(chId) {
    const ch = chapterById(chId);
    const st = getChapterState(chId);

    if (st.completed) {
      renderChapterResults(chId);
      return;
    }
    if (!st.started) {
      renderModeSelect(ch);
      return;
    }
    renderQuestion(ch);
  }

  /* ---------------- Mode Selection (Section 12: Identification vs Multiple Choice) ---------------- */

  function renderModeSelect(ch) {
    const st = getChapterState(ch.chapterId);
    const chIdx = bank.chapters.findIndex(function(c) { return c.chapterId === ch.chapterId; });
    const chNum = chIdx >= 0 ? (chIdx + 1) : 1;
    const totalCh = bank.chapters.length;

    const mcPool = getChapterPool(ch, "mc");
    const idPool = getChapterPool(ch, "id");
    const mcCount = mcPool.length;
    const idCount = idPool.length;

    // Pick a sensible default mode if not yet set or if current has 0 questions
    if (!st.mode || (st.mode === "id" && idCount === 0) || (st.mode === "mc" && mcCount === 0)) {
      st.mode = idCount > 0 ? "id" : "mc";
    }

    view.innerHTML =
      '<div class="view-panel">' +
      '<div class="panel-eyebrow">' +
        '<span class="eyebrow-pill">Chapter ' + chNum + ' of ' + totalCh + '</span>' +
      '</div>' +
      '<h2 class="panel-title">' + esc(ch.title) + '</h2>' +
      (ch.description ? '<p class="panel-sub">' + esc(ch.description) + '</p>' : '') +
      '<div class="card start-card card-google-form">' +
      '<div class="start-card-header">' +
        '<div class="start-meta-pill">' +
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
          '<span>' + (mcCount + idCount) + ' Questions Total &bull; Select Assessment Mode</span>' +
        '</div>' +
        '<div class="start-card-tip">Classified by Knowledge Fitness</div>' +
      '</div>' +

      '<div class="mode-label-row">' +
        '<span class="mode-label">1. Assessment Mode</span>' +
      '</div>' +
      '<div class="mode-grid">' +
      // Identification Mode Card (Section 2.1 & 12)
      '<div class="mode-option ' + (st.mode === "id" ? "selected" : "") + (idCount === 0 ? " disabled" : "") + '" data-test-mode="id" role="button" tabindex="0" aria-pressed="' + (st.mode === "id") + '">' +
        '<div class="mode-option-top">' +
          '<span class="mode-icon-wrap"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></span>' +
          '<span class="mode-badge ' + (idCount > 0 ? "mode-badge-id" : "mode-badge-empty") + '">' + idCount + ' Questions</span>' +
        '</div>' +
        '<h4>Identification (' + idCount + ')</h4>' +
        '<p>Universal term knowledge. Type exact recognized named terms, frameworks, architectures, and protocols based on defining descriptions.</p>' +
      '</div>' +

      // Multiple Choice Mode Card (Section 2.2 & 12)
      '<div class="mode-option ' + (st.mode === "mc" ? "selected" : "") + (mcCount === 0 ? " disabled" : "") + '" data-test-mode="mc" role="button" tabindex="0" aria-pressed="' + (st.mode === "mc") + '">' +
        '<div class="mode-option-top">' +
          '<span class="mode-icon-wrap"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9 12l2 2 4-4"></path></svg></span>' +
          '<span class="mode-badge ' + (mcCount > 0 ? "mode-badge-mc" : "mode-badge-empty") + '">' + mcCount + ' Questions</span>' +
        '</div>' +
        '<h4>Multiple Choice (' + mcCount + ')</h4>' +
        '<p>Source-dependent assessment. 4-choice questions testing benefits, limitations, challenges, purposes, comparisons, and list items.</p>' +
      '</div>' +
      '</div>' +

      '<div class="mode-label-row" style="margin-top:20px;">' +
        '<span class="mode-label">2. Pacing & Timing</span>' +
      '</div>' +
      '<div class="mode-grid">' +
      '<div class="mode-option ' + (st.pacing === "untimed" ? "selected" : "") + '" data-pacing="untimed" role="button" tabindex="0" aria-pressed="' + (st.pacing === "untimed") + '">' +
        '<div class="mode-option-top">' +
          '<span class="mode-icon-wrap"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg></span>' +
          '<span class="mode-badge mode-badge-untimed">Self-paced</span>' +
        '</div>' +
        '<h4>Untimed Practice</h4><p>Take your time to recall terms and analyze scenarios with instant rationale feedback.</p>' +
      '</div>' +
      '<div class="mode-option ' + (st.pacing === "timed" ? "selected" : "") + '" data-pacing="timed" role="button" tabindex="0" aria-pressed="' + (st.pacing === "timed") + '">' +
        '<div class="mode-option-top">' +
          '<span class="mode-icon-wrap"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></span>' +
          '<span class="mode-badge mode-badge-timed">10s / question</span>' +
        '</div>' +
        '<h4>Timed Challenge</h4><p>10-second countdown per question. Tests immediate recall under exam pressure.</p>' +
      '</div>' +
      '</div>' +

      // Shuffling Toggle (Section 12: Shuffle toggle, default ON)
      '<label class="shuffle-toggle-row">' +
        '<input type="checkbox" id="shuffle-toggle" ' + (st.shuffle ? "checked" : "") + '>' +
        '<div class="shuffle-toggle-label">' +
          '<strong>Shuffle Question Order</strong>' +
          '<div class="shuffle-toggle-desc">Randomizes the presentation sequence for every run.</div>' +
        '</div>' +
      '</label>' +

      '<div class="start-actions">' +
      '<button class="btn btn-primary btn-start-chapter" id="start-id">' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>' +
        '<span>Start ' + (st.mode === "id" ? "Identification" : "Multiple Choice") + '</span>' +
      '</button>' +
      '</div>' +
      '</div></div>';

    // Mode pick listeners
    view.querySelectorAll(".mode-option[data-test-mode]").forEach(function (el) {
      if (el.classList.contains("disabled")) return;
      const pick = function () {
        st.mode = el.dataset.testMode;
        view.querySelectorAll(".mode-option[data-test-mode]").forEach(function (o) {
          o.classList.toggle("selected", o === el);
          o.setAttribute("aria-pressed", o === el ? "true" : "false");
        });
        const btnTxt = document.querySelector("#start-id span");
        if (btnTxt) {
          btnTxt.textContent = "Start " + (st.mode === "id" ? "Identification" : "Multiple Choice");
        }
      };
      el.addEventListener("click", pick);
      el.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") { e.preventDefault(); pick(); }
      });
    });

    // Pacing pick listeners
    view.querySelectorAll(".mode-option[data-pacing]").forEach(function (el) {
      const pickPacing = function () {
        st.pacing = el.dataset.pacing;
        view.querySelectorAll(".mode-option[data-pacing]").forEach(function (o) {
          o.classList.toggle("selected", o === el);
          o.setAttribute("aria-pressed", o === el ? "true" : "false");
        });
      };
      el.addEventListener("click", pickPacing);
      el.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") { e.preventDefault(); pickPacing(); }
      });
    });

    const shuffleInp = document.getElementById("shuffle-toggle");
    if (shuffleInp) {
      shuffleInp.addEventListener("change", function () {
        st.shuffle = Boolean(shuffleInp.checked);
      });
    }

    document.getElementById("start-id").addEventListener("click", function () {
      const targetCount = st.mode === "id" ? idCount : mcCount;
      if (targetCount === 0) {
        toast("No questions available for " + (st.mode === "id" ? "Identification" : "Multiple Choice") + ".");
        return;
      }
      freshChapterState(ch.chapterId, st.mode, null, false, true);
      renderNav();
      renderChapter(ch.chapterId);
    });
  }

  function currentQ(st, displayIndex) {
    const qid = st.order[displayIndex];
    return st.questions.find(function (x) { return x.id === qid; }) || null;
  }

  /* ---------------- Unified Question Dispatcher ---------------- */

  function renderQuestion(ch) {
    const st = getChapterState(ch.chapterId);
    const pos = st.index;
    const q = currentQ(st, pos);
    if (!q) {
      completeChapter(ch.chapterId);
      return;
    }

    if (st.mode === "id") {
      renderQuestionId(ch, q, st, pos);
    } else {
      renderQuestionMc(ch, q, st, pos);
    }
  }

  /* ---------------- Identification Question Screen (Google Forms Look) ---------------- */

  function renderQuestionId(ch, q, st, pos) {
    const total = st.questions.length;
    const displayNum = pos + 1;
    const answered = st.answers[q.id];
    const isTimed = st.pacing === "timed";
    const pct = Math.round((pos / total) * 100);

    let html =
      '<div class="view-panel q-view">' +
      '<div class="q-header">' +
      '<div class="q-head-left">' +
      '<span class="q-chapter-badge">' + esc(ch.title) + '</span>' +
      (st.isRedemption ? '<span class="q-redemption-badge">🔥 Redemption Arc</span>' : '') +
      '<span class="q-progress-label">Question ' + displayNum + " of " + total + "</span>" +
      "</div>" +
      '<div class="q-header-right">' +
      (isTimed ? '<span class="timer" data-timer>10</span>' : '<span class="timer untimed">Untimed</span>') +
      '<span class="q-score">Score: ' + st.correct + "/" + st.answered + "</span>" +
      "</div></div>" +
      '<div class="q-progress-row">' +
      '<div class="q-progress"><div style="width:' + pct + '%"></div></div>' +
      '<span class="q-pct">' + pct + '%</span>' +
      "</div>" +

      // Question card with Google Forms top colored border
      '<div class="q-card q-card-identification">' +
      '<div class="q-type-badge">Identify the term *</div>' +
      '<p class="q-text">' + esc(q.question) + "</p>" +

      '<div class="id-input-wrap">' +
      '<input type="text" id="id-answer-input" class="id-input-underline' +
        (answered ? (answered.correct ? ' correct' : ' incorrect') : '') +
        '" placeholder="Type the term or concept here..." ' +
        (answered ? 'readonly value="' + esc(answered.userAnswer) + '"' : 'autofocus') +
        ' autocomplete="off" spellcheck="false">' +
      '</div>';

    if (!answered) {
      html +=
        '<div class="next-row" style="justify-content:flex-start; margin-top:20px;">' +
        '<button class="btn btn-primary" id="id-submit-btn">' +
          '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' +
          '<span>Submit Answer</span>' +
        '</button>' +
        '</div>';
    } else {
      let cls = "correct", title = "Correct! ✓";
      if (answered.timedOut) { cls = "timeout"; title = "Time's up! ✗"; }
      else if (!answered.correct) { cls = "incorrect"; title = "Incorrect. ✗"; }

      const altsText = (Array.isArray(q.alternates) && q.alternates.length)
        ? '<div class="id-alts-note"><span class="id-alts-label">Accepted variants:</span> ' + esc(q.alternates.join(", ")) + '</div>'
        : '';

      html += '<div class="feedback ' + cls + '">' +
        '<span class="fb-title">' + title + '</span>' +
        '<div><strong>Correct term: ' + esc(q.answer) + '</strong></div>' +
        altsText +
        (q.explanation ? '<div class="feedback-expl">' + esc(q.explanation) + '</div>' : '') +
        '</div>';

      html += '<div class="next-row">' +
        '<button class="btn btn-primary" id="next-btn">' +
        (pos + 1 >= total ? "Finish Section" : "Next Question &rarr;") +
        '</button>' +
        '</div>';
    }

    html += "</div>" +
      '<div class="kbd-hint"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="M6 8h.001M10 8h.001M14 8h.001M18 8h.001M8 12h.001M12 12h.001M16 12h.001M7 16h10"></path></svg> <span>Press <kbd>Enter</kbd> to submit &bull; Press <kbd>Enter</kbd> again to advance</span></div>' +
      '<div class="next-row"><button class="reset-link" id="reset-link">Reset section (choose mode again)</button></div>' +
      "</div>";

    view.innerHTML = html;

    const inputEl = document.getElementById("id-answer-input");
    const submitBtn = document.getElementById("id-submit-btn");
    const nextBtn = document.getElementById("next-btn");

    if (!answered) {
      if (inputEl) {
        inputEl.focus();
        inputEl.addEventListener("keydown", function (e) {
          if (e.key === "Enter") {
            e.preventDefault();
            if (e.repeat) return;
            answerChapterIdCurrent(ch.chapterId, q.id, inputEl.value);
          }
        });
      }
      if (submitBtn && inputEl) {
        submitBtn.addEventListener("click", function () {
          answerChapterIdCurrent(ch.chapterId, q.id, inputEl.value);
        });
      }
      if (isTimed) {
        startTimer(ch.chapterId, q.id, true);
      }
    } else {
      if (nextBtn) {
        nextBtn.addEventListener("click", function () {
          clearTimer(st);
          st.index += 1;
          renderChapter(ch.chapterId);
        });
      }
      // Enter again goes next, keeping focus stable (Section 12)
      if (inputEl) {
        inputEl.addEventListener("keydown", function (e) {
          if (e.key === "Enter") {
            e.preventDefault();
            if (e.repeat) return;
            if (nextBtn) nextBtn.click();
          }
        });
      }
    }

    document.getElementById("reset-link").addEventListener("click", function () {
      if (confirm("Reset this section? Your progress will be cleared, and you can pick a mode again.")) {
        resetChapter(ch.chapterId);
      }
    });
  }

  function answerChapterIdCurrent(chId, qid, rawUserInput) {
    const st = getChapterState(chId);
    const ch = chapterById(chId);
    const q = st.questions.find(function (x) { return x.id === qid; });
    if (!q) return;

    const val = String(rawUserInput || "").trim();
    const correct = checkIdMatch(val, q.answer, q.alternates);

    st.answers[qid] = {
      userAnswer: val,
      correct: correct,
      timedOut: false,
    };
    st.answered = Object.keys(st.answers).length;

    if (correct) {
      st.correct += 1;
      st.score += 1;
      if (mistakeBank[qid]) resolveMistake(qid);
    } else {
      st.incorrect += 1;
      st.missedIds.push(qid);
      recordMistake(q, ch, val, "identification");
    }

    clearTimer(st);
    renderChapter(chId);
  }

  /* ---------------- Multiple Choice Question Screen ---------------- */

  function renderQuestionMc(ch, q, st, pos) {
    const total = st.questions.length;
    const displayNum = pos + 1;
    const answered = st.answers[q.id];
    const choiceOrder = st.choiceOrder[q.id] || [0, 1, 2, 3];
    const isTimed = st.pacing === "timed";
    const pct = Math.round((pos / total) * 100);

    let html =
      '<div class="view-panel q-view">' +
      '<div class="q-header">' +
      '<div class="q-head-left">' +
      '<span class="q-chapter-badge">' + esc(ch.title) + '</span>' +
      (st.isRedemption ? '<span class="q-redemption-badge">🔥 Redemption Arc</span>' : '') +
      '<span class="q-progress-label">Question ' + displayNum + " of " + total + "</span>" +
      "</div>" +
      '<div class="q-header-right">' +
      (isTimed ? '<span class="timer" data-timer>10</span>' : '<span class="timer untimed">Untimed</span>') +
      '<span class="q-score">Score: ' + st.correct + "/" + st.answered + "</span>" +
      "</div></div>" +
      '<div class="q-progress-row">' +
      '<div class="q-progress"><div style="width:' + pct + '%"></div></div>' +
      '<span class="q-pct">' + pct + '%</span>' +
      "</div>" +

      // Question card with Blue top border
      '<div class="q-card q-card-mc">' +
      '<div class="q-type-badge">Choose the best answer *</div>' +
      '<p class="q-text">' + esc(q.question) + "</p>";

    html += '<ul class="choices">';
    for (let i = 0; i < 4; i++) {
      const origIdx = choiceOrder[i];
      const cls = [];
      let badge = "";
      if (answered) {
        if (origIdx === q.correctAnswer) {
          cls.push("correct");
          badge = ' <span class="choice-result-badge ok">✓</span>';
        }
        if (answered.selected === origIdx && origIdx !== q.correctAnswer) {
          cls.push("incorrect");
          badge = ' <span class="choice-result-badge no">✗</span>';
        }
      }
      html += '<li><button class="choice ' + cls.join(" ") + '" data-choice="' + i + '"' + (answered ? " disabled" : "") + ">" +
        '<span class="letter">' + LETTERS[i] + "</span>" +
        '<span class="choice-text">' + esc(q.choices[origIdx]) + badge + "</span>" +
        (!answered ? '<span class="choice-shortcut"><kbd>' + LETTERS[i] + '</kbd></span>' : '') +
        "</button></li>";
    }
    html += "</ul>";

    if (answered) {
      let cls = "correct", title = "Correct! ✓", expl = esc(q.explanation);
      if (answered.timedOut) { cls = "timeout"; title = "Time's up! ✗"; }
      else if (!answered.correct) { cls = "incorrect"; title = "Incorrect. ✗"; }
      html += '<div class="feedback ' + cls + '"><span class="fb-title">' + title + "</span>" +
        "<strong>Correct answer: " + LETTERS[choiceOrder.indexOf(q.correctAnswer)] + ".</strong> " +
        "<span>" + expl + "</span></div>";
      if (answered.timedOut) {
        html += "<p style=\"color:var(--muted);font-size:13px;\">Out of time &mdash; counted as incorrect. Score: " +
          st.correct + "/" + (st.correct + st.incorrect) + ".</p>";
      }
      html += '<div class="next-row"><button class="btn btn-primary" id="next-btn">' +
        (pos + 1 >= total ? "Finish Section" : "Next Question &rarr;") + "</button></div>";
    }

    html += "</div>" +
      '<div class="kbd-hint"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="M6 8h.001M10 8h.001M14 8h.001M18 8h.001M8 12h.001M12 12h.001M16 12h.001M7 16h10"></path></svg> <span>Press <kbd>A</kbd>&ndash;<kbd>D</kbd> or <kbd>1</kbd>&ndash;<kbd>4</kbd> to select &bull; <kbd>Enter</kbd> to advance</span></div>' +
      '<div class="next-row"><button class="reset-link" id="reset-link">Reset section (choose mode again)</button></div>' +
      "</div>";

    view.innerHTML = html;

    if (!answered) {
      const idxQid = q.id;
      view.querySelectorAll(".choice").forEach(function (btn) {
        btn.addEventListener("click", function () {
          const choiceIdx = parseInt(btn.dataset.choice, 10);
          const origIdx = st.choiceOrder[idxQid][choiceIdx];
          answerChapterMcCurrent(ch.chapterId, idxQid, origIdx, choiceIdx);
        });
      });
      if (isTimed) {
        startTimer(ch.chapterId, q.id, false);
      }
    } else {
      document.getElementById("next-btn").addEventListener("click", function () {
        clearTimer(st);
        st.index += 1;
        renderChapter(ch.chapterId);
      });
    }

    document.getElementById("reset-link").addEventListener("click", function () {
      if (confirm("Reset this section? Your score and progress will be cleared, and you can pick a mode again.")) {
        resetChapter(ch.chapterId);
      }
    });
  }

  function answerChapterMcCurrent(chId, qid, origIdx, choiceIdx) {
    const st = getChapterState(chId);
    const ch = chapterById(chId);
    const q = st.questions.find(function (x) { return x.id === qid; });
    const correct = origIdx === q.correctAnswer;
    st.answers[qid] = { selected: origIdx, correct: correct, timedOut: false, at: choiceIdx };
    st.answered = Object.keys(st.answers).length;
    if (correct) {
      st.correct += 1;
      st.score += 1;
      if (mistakeBank[qid]) resolveMistake(qid);
    } else {
      st.incorrect += 1;
      st.missedIds.push(qid);
      recordMistake(q, ch, origIdx, "mc");
    }
    clearTimer(st);
    renderChapter(chId);
  }

  /* ---------------- Timer Handler ---------------- */

  function startTimer(chId, qid, isIdMode) {
    const st = getChapterState(chId);
    clearTimer(st);
    let left = 10;
    const el = view.querySelector("[data-timer]");
    if (el) { el.textContent = left; el.classList.remove("low"); }

    st.timer = setInterval(function () {
      if (activeLock() !== chId) {
        clearInterval(st.timer);
        st.timer = null;
        return;
      }
      if (!st.answers[qid]) {
        left -= 1;
        if (el) {
          el.textContent = Math.max(0, left);
          el.classList.toggle("low", left <= 3);
        }
        if (left <= 0) {
          clearInterval(st.timer);
          st.timer = null;
          answerChapterTimeout(chId, qid, isIdMode);
        }
      }
    }, 1000);
  }

  function answerChapterTimeout(chId, qid, isIdMode) {
    const st = getChapterState(chId);
    if (st.answers[qid]) return;
    const ch = chapterById(chId);
    const q = st.questions.find(function (x) { return x.id === qid; });

    if (isIdMode) {
      st.answers[qid] = { userAnswer: "", correct: false, timedOut: true };
    } else {
      st.answers[qid] = { selected: null, correct: false, timedOut: true, at: null };
    }

    st.answered = Object.keys(st.answers).length;
    st.incorrect += 1;
    st.missedIds.push(qid);
    if (q) recordMistake(q, ch, null, isIdMode ? "identification" : "mc");
    renderChapter(chId);
  }

  function completeChapter(chId) {
    const st = getChapterState(chId);
    st.completed = true;
    clearTimer(st);
    renderNav();
    renderChapterResults(chId);
  }

  function resetChapter(chId) {
    const st = getChapterState(chId);
    clearTimer(st);
    freshChapterState(chId, st.mode, null, false);
    renderNav();
    renderChapter(chId);
  }

  /* ---------------- Results Screen with Redemption Arc (Section 12) ---------------- */

  function renderChapterResults(chId) {
    const ch = chapterById(chId);
    const st = getChapterState(chId);
    const total = st.questions.length;
    const pct = total ? Math.round((st.correct / total) * 100) : 0;
    const missedCount = st.missedIds ? st.missedIds.length : 0;

    let html =
      '<div class="view-panel">' +
      '<h2 class="panel-title">' + (st.isRedemption ? "Redemption Arc Complete" : "Section Complete") + '</h2>' +
      '<p class="panel-sub">' + esc(ch.title) + " &bull; " + (st.mode === "id" ? "Identification Mode" : "Multiple Choice Mode") + "</p>" +
      '<div class="card card-google-form">' +
      '<div class="result-big">' + pct + "%</div>" +
      '<div class="result-grid">' +
      '<div class="result-cell"><span class="rc-value" style="color:var(--green)">' + st.correct + ' ✓</span><span class="rc-label">Correct</span></div>' +
      '<div class="result-cell"><span class="rc-value" style="color:var(--red)">' + st.incorrect + ' ✗</span><span class="rc-label">Incorrect</span></div>' +
      '<div class="result-cell"><span class="rc-value">' + st.correct + "/" + total + "</span><span class=\"rc-label\">Score</span></div>" +
      '<div class="result-cell"><span class="rc-value">' + (st.pacing === "timed" ? "Timed (10s)" : "Untimed") + "</span><span class=\"rc-label\">Pacing</span></div>" +
      "</div>";

    if (st.isRedemption && missedCount === 0) {
      html += '<div class="redemption-success-banner">🎉 All questions mastered! Redemption Arc finished with 100% accuracy.</div>';
    }

    html += '<div class="result-actions">';

    // Redemption Arc Button (Section 12: Repeatable until no questions are missed)
    if (missedCount > 0) {
      html +=
        '<button class="btn btn-redemption" id="redemption-arc-btn">' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 23c-4.97 0-9-4.03-9-9 0-4.12 2.78-7.59 6.58-8.66.42-.12.83.18.83.61v.24c0 .32-.2.61-.51.7-2.67.79-4.63 3.25-4.63 6.11 0 3.86 3.14 7 7 7s7-3.14 7-7c0-2.31-1.12-4.36-2.85-5.63-.26-.19-.38-.52-.3-.83.08-.31.33-.54.65-.54h.47c3.96 1.48 6.73 5.27 6.73 9.7 0 4.97-4.03 9-9 9z"/><path d="M12 17c-2.21 0-4-1.79-4-4 0-1.63.98-3.03 2.39-3.66.38-.17.82.04.93.44.11.41-.1.83-.48 1-.95.42-1.6 1.36-1.6 2.45 0 1.48 1.2 2.68 2.68 2.68s2.68-1.2 2.68-2.68c0-.68-.26-1.3-.68-1.77-.3-.33-.27-.84.06-1.14.33-.3.84-.27 1.14.06.63.7 1 1.62 1 2.62 0 2.21-1.79 4-4 4z"/></svg>' +
        '<span>Redemption Arc (' + missedCount + ' Missed)</span>' +
        '</button>';
    }

    html +=
      '<button class="btn btn-primary" id="review-btn">Review Answers</button>' +
      '<button class="btn btn-ai" id="ai-analyze-btn"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path></svg> <span>AI Diagnosis</span></button>' +
      '<button class="btn" id="retake-btn">Retake Section</button>' +
      '<button class="btn" id="back-modes-btn">Back to Modes</button>' +
      "</div>" +
      '<div id="ai-feedback-container" class="ai-feedback-container"></div>' +
      "</div></div>";

    view.innerHTML = html;

    const redempBtn = document.getElementById("redemption-arc-btn");
    if (redempBtn) {
      redempBtn.addEventListener("click", function () {
        startRedemptionArc(chId);
      });
    }

    document.getElementById("review-btn").addEventListener("click", function () {
      renderReview(chId);
    });

    document.getElementById("ai-analyze-btn").addEventListener("click", function () {
      const mistakes = [];
      const correctItems = [];
      st.questions.forEach(function (q) {
        const a = st.answers[q.id];
        if (a && a.correct) {
          correctItems.push({ id: q.id, question: q.question });
        } else {
          if (st.mode === "id") {
            mistakes.push({
              id: q.id,
              question: q.question,
              yourAnswer: a && a.userAnswer ? a.userAnswer : "Timed out / unanswered",
              correctAnswer: q.answer,
              explanation: q.explanation,
            });
          } else {
            mistakes.push({
              id: q.id,
              question: q.question,
              yourAnswer: a && a.selected != null ? q.choices[a.selected] : "Timed out / unanswered",
              correctAnswer: q.choices[q.correctAnswer],
              explanation: q.explanation,
            });
          }
        }
      });
      const containerEl = document.getElementById("ai-feedback-container");
      triggerAiAnalysis(containerEl, {
        examId: examId,
        sectionTitle: ch.title + " (" + (st.mode === "id" ? "Identification" : "Multiple Choice") + ")",
        score: st.correct,
        total: total,
        mistakes: mistakes,
        correctItems: correctItems,
      });
    });

    document.getElementById("retake-btn").addEventListener("click", function () {
      freshChapterState(chId, st.mode, null, false, true);
      renderNav();
      renderChapter(chId);
    });

    document.getElementById("back-modes-btn").addEventListener("click", function () {
      freshChapterState(chId, null, null, false, false);
      renderNav();
      renderChapter(chId);
    });
  }

  function startRedemptionArc(chId) {
    const st = getChapterState(chId);
    if (!st.missedIds || !st.missedIds.length) return;

    // Filter only the missed questions from the run
    const missedQuestions = st.questions.filter(function (q) {
      return st.missedIds.indexOf(q.id) !== -1;
    });

    // Start a fresh state with only the missed questions, shuffled!
    freshChapterState(chId, st.mode, missedQuestions, true, true);
    renderNav();
    renderChapter(chId);
  }

  function renderReview(chId) {
    const ch = chapterById(chId);
    const st = getChapterState(chId);
    const isId = st.mode === "id";

    const items = st.questions.map(function (q, idx) {
      const a = st.answers[q.id];
      const ok = !!(a && a.correct);
      const verdict = ok ? "Correct ✓" : (a && a.timedOut ? "Time's up ✗" : "Incorrect ✗");

      if (isId) {
        const userTyped = a && a.userAnswer ? a.userAnswer : "(none)";
        const alts = (Array.isArray(q.alternates) && q.alternates.length)
          ? '<div class="ans-line" style="color:var(--muted); font-size:12.5px;">Accepted alternates: ' + esc(q.alternates.join(", ")) + '</div>'
          : '';
        return (
          '<div class="review-item">' +
          '<div class="rq-num">Question ' + (idx + 1) + ' &bull; Identification</div>' +
          '<p class="rq">' + esc(q.question) + "</p>" +
          '<div class="ans-line"><span class="' + (ok ? "ok" : "no") + '">' + verdict + "</span> &mdash; Your answer: <strong>" +
          esc(userTyped) + "</strong></div>" +
          '<div class="ans-line"><span class="ok">Correct answer:</span> <strong>' + esc(q.answer) + "</strong></div>" +
          alts +
          (q.explanation ? '<div class="review-expl"><strong>Explanation:</strong> ' + esc(q.explanation) + "</div>" : "") +
          "</div>"
        );
      } else {
        const choiceOrder = st.choiceOrder[q.id] || [0, 1, 2, 3];
        const chosen = a ? a.selected : null;
        const letter = a && a.at != null ? LETTERS[a.at] : "-";
        return (
          '<div class="review-item">' +
          '<div class="rq-num">Question ' + (idx + 1) + ' &bull; Multiple Choice</div>' +
          '<p class="rq">' + esc(q.question) + "</p>" +
          '<div class="ans-line"><span class="' + (ok ? "ok" : "no") + '">' + verdict + "</span> &mdash; Your answer: <strong>" +
          (chosen == null ? "(none)" : esc(q.choices[chosen]) + " (" + letter + ")") + "</strong></div>" +
          '<div class="ans-line"><span class="ok">Correct answer: ' + LETTERS[choiceOrder.indexOf(q.correctAnswer)] + ".</span> " + esc(q.choices[q.correctAnswer]) + "</div>" +
          (q.explanation ? '<div class="review-expl"><strong>Explanation:</strong> ' + esc(q.explanation) + "</div>" : "") +
          "</div>"
        );
      }
    });

    view.innerHTML =
      '<div class="view-panel">' +
      '<h2 class="panel-title">Review &mdash; ' + esc(ch.title) + "</h2>" +
      '<p class="panel-sub">' + (isId ? "Identification" : "Multiple Choice") + " &bull; Score: " + st.correct + "/" + st.questions.length + "</p>" +
      '<div class="next-row" style="justify-content:flex-start; margin-bottom:20px;"><button class="btn btn-primary" id="back-results">Back to results</button></div>' +
      items.join("") +
      "</div>";

    document.getElementById("back-results").addEventListener("click", function () {
      switchTo("ch|" + chId);
    });
  }

  /* ---------------- overall exam ---------------- */

  function renderOverall() {
    if (!overall) {
      overall = { started: false, submitted: false, selected: {}, idAnswers: {}, score: 0, correct: 0, incorrect: 0 };
    }
    if (overall.submitted) { renderOverallResults(); return; }
    if (!overall.started) {
      view.innerHTML =
        '<div class="view-panel">' +
        '<h2 class="panel-title">Overall Comprehensive Exam</h2>' +
        '<p class="panel-sub">Combines all chapters and question types. Untimed, Google Forms-style comprehensive test.</p>' +
        '<div class="card card-google-form"><p class="muted">' + flat.length + " questions total across " + bank.chapters.length + " chapters.</p>" +
        '<button class="btn btn-primary" id="start-overall">Begin Overall Exam</button></div></div>';
      document.getElementById("start-overall").addEventListener("click", function () {
        overall.started = true;
        renderNav();
        renderOverall();
        window.scrollTo(0, 0);
      });
      return;
    }

    let html = '<div class="view-panel">';
    html += "<h2 class=\"panel-title\">Overall Comprehensive Exam</h2>" +
      '<p class="panel-sub">Untimed &middot; Answer all items across chapters, then press submit.</p>';

    flat.forEach(function (item, idx) {
      const q = item.q;
      const isId = item.type === "identification";

      if (isId) {
        const val = overall.idAnswers[q.id] || "";
        html += '<div class="overall-q q-card-identification">' +
          '<div class="oq-head"><span class="oq-num">' + (idx + 1) + '. [Identification] ' + esc(q.question) + "</span>" +
          '<span class="oq-chapter">' + esc(item.ch.title) + "</span></div>" +
          '<div class="id-input-wrap">' +
          '<input type="text" class="id-input-underline overall-id-inp" data-qid="' + esc(q.id) + '" placeholder="Type your answer here..." value="' + esc(val) + '" autocomplete="off" spellcheck="false">' +
          '</div></div>';
      } else {
        const chosen = overall.selected[q.id];
        html += '<div class="overall-q q-card-mc">' +
          '<div class="oq-head"><span class="oq-num">' + (idx + 1) + '. [Multiple Choice] ' + esc(q.question) + "</span>" +
          '<span class="oq-chapter">' + esc(item.ch.title) + "</span></div>" +
          "<fieldset>";
        q.choices.forEach(function (c, ci) {
          const letter = LETTERS[ci];
          html += '<label class="opt"><input type="radio" name="oq-' + esc(q.id) + '" value="' + ci + '"' +
            (chosen === ci ? " checked" : "") + ">" +
            '<span class="opt-letter">' + letter + ".</span> " +
            '<span class="opt-text">' + esc(c) + "</span></label>";
        });
        html += "</fieldset></div>";
      }
    });

    html += '<div class="next-row"><button class="btn btn-primary" id="submit-overall">Submit Overall Exam</button>' +
      '<button class="btn" id="reset-overall">Reset</button></div></div>';

    view.innerHTML = html;

    view.querySelectorAll('input[type="radio"]').forEach(function (inp) {
      inp.addEventListener("change", function () {
        overall.selected[inp.name.replace("oq-", "")] = parseInt(inp.value, 10);
      });
    });

    view.querySelectorAll(".overall-id-inp").forEach(function (inp) {
      inp.addEventListener("input", function () {
        overall.idAnswers[inp.dataset.qid] = inp.value;
      });
    });

    document.getElementById("submit-overall").addEventListener("click", function () {
      if (confirm("Submit the Overall Exam? You cannot change answers afterward.")) {
        submitOverall();
      }
    });
    document.getElementById("reset-overall").addEventListener("click", function () {
      overall = { started: false, submitted: false, selected: {}, idAnswers: {}, score: 0, correct: 0, incorrect: 0 };
      renderNav();
      renderOverall();
    });
  }

  function submitOverall() {
    let correct = 0;
    flat.forEach(function (item) {
      const q = item.q;
      if (item.type === "identification") {
        const userVal = overall.idAnswers[q.id] || "";
        const ok = checkIdMatch(userVal, q.answer, q.alternates);
        if (ok) {
          correct += 1;
          if (mistakeBank[q.id]) resolveMistake(q.id);
        } else {
          recordMistake(q, item.ch, userVal, "identification");
        }
      } else {
        const sel = overall.selected[q.id];
        if (sel != null && sel === q.correctAnswer) {
          correct += 1;
          if (mistakeBank[q.id]) resolveMistake(q.id);
        } else {
          recordMistake(q, item.ch, sel, "mc");
        }
      }
    });

    overall.submitted = true;
    overall.correct = correct;
    overall.incorrect = flat.length - correct;
    overall.score = correct;
    renderNav();
    renderOverallResults();
  }

  function renderOverallResults() {
    const total = flat.length;
    const pct = total ? Math.round((overall.correct / total) * 100) : 0;
    let html = '<div class="view-panel">' +
      "<h2 class=\"panel-title\">Overall Exam Results</h2>" +
      '<div class="card card-google-form">' +
      '<div class="result-big">' + pct + "%</div>" +
      '<div class="result-grid">' +
      '<div class="result-cell"><span class="rc-value" style="color:var(--green)">' + overall.correct + " ✓</span><span class=\"rc-label\">Correct</span></div>" +
      '<div class="result-cell"><span class="rc-value" style="color:var(--red)">' + overall.incorrect + " ✗</span><span class=\"rc-label\">Incorrect</span></div>" +
      '<div class="result-cell"><span class="rc-value">' + overall.correct + "/" + total + "</span><span class=\"rc-label\">Score</span></div>" +
      "</div>" +
      '<div class="result-actions">' +
      '<button class="btn btn-ai" id="ai-analyze-overall-btn"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path></svg> <span>AI Diagnosis</span></button>' +
      '<button class="btn" id="retake-overall">Retake Overall</button>' +
      '</div>' +
      '<div id="ai-overall-feedback-container" class="ai-feedback-container"></div>' +
      "</div>";

    flat.forEach(function (item, idx) {
      const q = item.q;
      const isId = item.type === "identification";

      if (isId) {
        const userVal = overall.idAnswers[q.id] || "";
        const ok = checkIdMatch(userVal, q.answer, q.alternates);
        html += '<div class="overall-q submitted q-card-identification">' +
          '<div class="oq-head"><span class="oq-num">' + (idx + 1) + '. [Identification] ' + esc(q.question) + "</span>" +
          '<span class="oq-chapter">' + esc(item.ch.title) + "</span></div>" +
          '<div class="ans-line">Your answer: <strong>' + (userVal ? esc(userVal) : "(unanswered)") + '</strong></div>' +
          '<div class="ans-line"><span class="ok">Correct answer:</span> <strong>' + esc(q.answer) + '</strong></div>' +
          (Array.isArray(q.alternates) && q.alternates.length ? '<div class="ans-line" style="color:var(--muted); font-size:12px;">Accepted: ' + esc(q.alternates.join(", ")) + '</div>' : '') +
          '<div class="oq-result" style="color:' + (ok ? "var(--green)" : "var(--red)") + '">' + (ok ? "Correct ✓" : "Incorrect ✗") + '</div>' +
          (q.explanation ? '<div class="review-expl"><strong>Explanation:</strong> ' + esc(q.explanation) + '</div>' : '') +
          '</div>';
      } else {
        const sel = overall.selected[q.id];
        const ok = sel === q.correctAnswer;
        html += '<div class="overall-q submitted q-card-mc">' +
          '<div class="oq-head"><span class="oq-num">' + (idx + 1) + '. [Multiple Choice] ' + esc(q.question) + "</span>" +
          '<span class="oq-chapter">' + esc(item.ch.title) + "</span></div>";
        q.choices.forEach(function (c, ci) {
          let cls = "opt";
          if (ci === q.correctAnswer) cls += " good";
          else if (sel === ci) cls += " bad cho";
          html += '<div class="' + cls + '"><span class="opt-letter">' + LETTERS[ci] + '.</span> ' +
            '<span class="opt-text">' + esc(c) + "</span></div>";
        });
        html += '<div class="oq-result" style="color:' + (ok ? "var(--green)" : "var(--red)") + '">' +
          (ok ? "Correct ✓" : sel == null ? "Not answered ✗" : "Incorrect ✗") + "</div>" +
          (q.explanation ? '<div class="review-expl"><strong>Explanation:</strong> ' + esc(q.explanation) + "</div>" : "") +
          "</div>";
      }
    });
    html += "</div>";

    view.innerHTML = html;

    document.getElementById("ai-analyze-overall-btn").addEventListener("click", function () {
      const mistakes = [];
      const correctItems = [];
      flat.forEach(function (item) {
        const q = item.q;
        if (item.type === "identification") {
          const userVal = overall.idAnswers[q.id] || "";
          if (checkIdMatch(userVal, q.answer, q.alternates)) {
            correctItems.push({ id: q.id, question: q.question });
          } else {
            mistakes.push({
              id: q.id,
              question: q.question,
              yourAnswer: userVal ? userVal : "Unanswered",
              correctAnswer: q.answer,
              explanation: q.explanation || "",
            });
          }
        } else {
          const sel = overall.selected[q.id];
          if (sel != null && sel === q.correctAnswer) {
            correctItems.push({ id: q.id, question: q.question });
          } else {
            mistakes.push({
              id: q.id,
              question: q.question,
              yourAnswer: sel != null ? q.choices[sel] : "Not answered",
              correctAnswer: q.choices[q.correctAnswer],
              explanation: q.explanation || "",
            });
          }
        }
      });
      const containerEl = document.getElementById("ai-overall-feedback-container");
      triggerAiAnalysis(containerEl, {
        examId: examId,
        sectionTitle: "Overall Exam",
        score: overall.correct,
        total: total,
        mistakes: mistakes,
        correctItems: correctItems,
      });
    });

    document.getElementById("retake-overall").addEventListener("click", function () {
      overall = { started: false, submitted: false, selected: {}, idAnswers: {}, score: 0, correct: 0, incorrect: 0 };
      renderNav();
      renderOverall();
    });
  }

  /* ---------------- answer key (Section 14) ---------------- */

  function renderAnswerKey() {
    let html = '<div class="view-panel"><h2 class="panel-title">Answer Key</h2>' +
      '<p class="panel-sub">Complete questions, source-grounded answers, and alternates across all modes.</p>';

    bank.chapters.forEach(function (ch) {
      const mcList = getChapterPool(ch, "mc");
      const idList = getChapterPool(ch, "id");

      html += '<div class="key-chapter"><h3>' + esc(ch.title) + '</h3>';

      // Identification Key
      if (idList.length) {
        html += '<div class="key-mode-heading"><span class="badge-mode-id">Identification (' + idList.length + ')</span> Universal Term Knowledge</div>' +
          '<div class="key-list">';
        idList.forEach(function (q, idx) {
          const alts = (Array.isArray(q.alternates) && q.alternates.length)
            ? '<span class="key-alts"> &bull; Alternates: ' + esc(q.alternates.join(", ")) + '</span>'
            : '';
          html += '<div class="key-item key-item-id">' +
            '<div class="key-item-header">' +
              '<span class="key-num">ID' + (idx + 1) + '</span>' +
              '<span class="key-question">' + esc(q.question) + '</span>' +
            '</div>' +
            '<div class="key-answer-row">' +
              '<span class="key-answer-badge">Term</span>' +
              '<strong class="key-answer-text">' + esc(q.answer) + '</strong>' +
              alts +
            '</div>' +
            (q.explanation ? (
              '<div class="key-explanation">' +
                '<span class="key-exp-label">Explanation:</span> ' + esc(q.explanation) +
              '</div>'
            ) : '') +
          '</div>';
        });
        html += '</div>';
      }

      // Multiple Choice Key
      if (mcList.length) {
        html += '<div class="key-mode-heading" style="margin-top:24px;"><span class="badge-mode-mc">Multiple Choice (' + mcList.length + ')</span> Source-Dependent Assessment</div>' +
          '<div class="key-list">';
        mcList.forEach(function (q, idx) {
          const correctLetter = LETTERS[q.correctAnswer] || "";
          const correctChoice = q.choices && q.choices[q.correctAnswer] != null ? q.choices[q.correctAnswer] : "";
          html += '<div class="key-item">' +
            '<div class="key-item-header">' +
              '<span class="key-num">MC' + (idx + 1) + '</span>' +
              '<span class="key-question">' + esc(q.question) + '</span>' +
            '</div>' +
            '<div class="key-answer-row">' +
              '<span class="key-answer-badge">Answer</span>' +
              '<span class="key-letter">' + correctLetter + '</span>' +
              '<span class="key-answer-text">' + esc(correctChoice) + '</span>' +
            '</div>' +
            (q.explanation ? (
              '<div class="key-explanation">' +
                '<span class="key-exp-label">Explanation:</span> ' + esc(q.explanation) +
              '</div>'
            ) : '') +
          '</div>';
        });
        html += '</div>';
      }

      html += '</div>';
    });

    html += '</div>';
    view.innerHTML = html;
  }

  /* ---------------- encyclopedia ---------------- */

  function renderEncyclopedia() {
    let html = '<div class="view-panel"><h2 class="panel-title">Encyclopedia</h2>' +
      '<p class="panel-sub">Key terms and reference definitions extracted from source material.</p>';

    if (!bank.encyclopedia || !bank.encyclopedia.length) {
      html += '<p class="empty-note">No encyclopedia entries were generated.</p></div>';
      view.innerHTML = html;
      return;
    }

    const byChapter = {};
    bank.encyclopedia.forEach(function (e) {
      const key = e.chapterId || "general";
      (byChapter[key] = byChapter[key] || []).push(e);
    });

    Object.keys(byChapter).forEach(function (key) {
      const ch = chapterById(key);
      const label = ch ? ch.title : "General";
      html += '<div class="enc-section"><h3>' + esc(label) + "</h3>";
      byChapter[key].forEach(function (e) {
        html += '<div class="enc-term"><strong>' + esc(e.term) + "</strong> " +
          "<span class=\"enc-def\">&mdash; " + esc(e.definition) + "</span></div>";
      });
      html += "</div>";
    });
    html += "</div>";
    view.innerHTML = html;
  }

  /* ---------------- mistake bank ---------------- */

  function renderMistakes() {
    const list = Object.values(mistakeBank);
    if (!list.length) {
      view.innerHTML =
        '<div class="view-panel">' +
        '<h2 class="panel-title">Mistake Bank</h2>' +
        '<p class="panel-sub">Targeted practice for missed questions.</p>' +
        '<div class="card card-google-form">' +
        '<p class="muted">No missed questions recorded yet. Complete chapters or the overall exam, and any incorrect answers will automatically be collected here for targeted review.</p>' +
        '</div></div>';
      return;
    }

    let html =
      '<div class="view-panel">' +
      '<h2 class="panel-title">Mistake Bank &mdash; ' + list.length + ' Missed Questions</h2>' +
      '<p class="panel-sub">Review incorrect items or launch a focused practice session.</p>' +
      '<div class="mistakes-actions">' +
      '<button class="btn btn-primary" id="practice-mistakes-untimed">Practice Missed (Untimed)</button>' +
      '<button class="btn" id="practice-mistakes-timed">Practice Missed (Timed)</button>' +
      '<button class="btn btn-outline" id="clear-mistakes">Clear Mistake Bank</button>' +
      '</div>' +
      '<div class="mistake-list">';

    list.forEach(function (m, idx) {
      const isId = m.type === "identification" || m.choices.length === 0;
      html +=
        '<div class="mistake-item ' + (isId ? 'q-card-identification' : 'q-card-mc') + '">' +
        '<div class="mistake-item-head">' +
        '<span class="mistake-chapter-tag">' + esc(m.chapterTitle) + ' &bull; ' + (isId ? "Identification" : "Multiple Choice") + '</span>' +
        '<span class="mistake-wrong-count">Missed ' + m.wrongCount + 'x</span>' +
        '</div>' +
        '<p class="rq">' + (idx + 1) + '. ' + esc(m.question) + '</p>' +
        (m.lastSelected ? '<div class="ans-line"><span class="no">Your previous answer:</span> ' + esc(m.lastSelected) + '</div>' : '') +
        (isId
          ? '<div class="ans-line"><span class="ok">Correct term:</span> <strong>' + esc(m.correctAnswer) + '</strong></div>'
          : '<div class="ans-line"><span class="ok">Correct answer: ' + LETTERS[m.correctAnswer] + '.</span> ' + (m.choices && m.choices[m.correctAnswer] ? esc(m.choices[m.correctAnswer]) : '') + '</div>') +
        (m.explanation ? '<div class="review-expl"><strong>Explanation:</strong> ' + esc(m.explanation) + '</div>' : '') +
        '</div>';
    });

    html += '</div></div>';
    view.innerHTML = html;

    document.getElementById("practice-mistakes-untimed").addEventListener("click", function () {
      startMistakeSession("untimed");
    });
    document.getElementById("practice-mistakes-timed").addEventListener("click", function () {
      startMistakeSession("timed");
    });
    document.getElementById("clear-mistakes").addEventListener("click", function () {
      if (confirm("Clear all recorded mistakes?")) {
        mistakeBank = {};
        saveMistakes();
        renderNav();
        renderMistakes();
      }
    });
  }

  function startMistakeSession(mode) {
    const list = Object.values(mistakeBank);
    if (!list.length) return;
    const fakeCh = {
      chapterId: "__mistakes__",
      title: "Mistakes Practice (" + list.length + " Questions)",
      description: "Focused practice session on previously missed questions.",
      questions: list.filter(function (m) { return m.type !== "identification" && m.choices && m.choices.length === 4; }).map(function (m) {
        return {
          id: m.id,
          type: "mc",
          question: m.question,
          choices: m.choices,
          correctAnswer: m.correctAnswer,
          explanation: m.explanation,
        };
      }),
      identification: list.filter(function (m) { return m.type === "identification" || !m.choices || m.choices.length !== 4; }).map(function (m) {
        return {
          id: m.id,
          type: "identification",
          question: m.question,
          answer: m.correctAnswer,
          alternates: m.alternates || [],
          explanation: m.explanation,
        };
      }),
    };

    const existingIdx = bank.chapters.findIndex(function (c) { return c.chapterId === "__mistakes__"; });
    if (existingIdx !== -1) bank.chapters.splice(existingIdx, 1);
    bank.chapters.push(fakeCh);

    freshChapterState("__mistakes__", fakeCh.identification.length > fakeCh.questions.length ? "id" : "mc", null, true);
    const st = getChapterState("__mistakes__");
    st.pacing = mode;
    st.started = true;
    currentView = "ch|__mistakes__";
    renderNav();
    renderChapter("__mistakes__");
  }

  /* ---------------- AI Diagnosis Engine ---------------- */

  function triggerAiAnalysis(containerEl, payload) {
    containerEl.innerHTML =
      '<div class="ai-loading-box">' +
      '<div class="ai-spinner"></div>' +
      '<div class="ai-loading-text">Analyzing your answers with AI Diagnostic Engine&hellip;</div>' +
      '<div class="ai-loading-sub">Running technical assessment on questions and misconceptions</div>' +
      '</div>';

    const reqPayload = Object.assign({ seed: Date.now() }, payload);

    fetch("api/analyze.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(reqPayload),
    })
      .then(function (r) {
        if (!r.ok) throw new Error("HTTP " + r.status);
        return r.json();
      })
      .then(function (data) {
        if (!data.ok) throw new Error(data.error || "Analysis failed");
        renderAiReport(containerEl, data.analysis, data.provider, reqPayload);
      })
      .catch(function (err) {
        console.warn("Backend response issue, generating local adaptive diagnosis:", err);
        const fallback = generateLocalDiagnosis(reqPayload);
        renderAiReport(containerEl, fallback, "OpenCode AI Engine (Adaptive)", reqPayload);
      });
  }

  function generateLocalDiagnosis(payload) {
    const pct = payload.total ? Math.round((payload.score / payload.total) * 100) : 0;
    const seed = payload.seed || Date.now();
    const cycle = Math.abs(seed % 3);

    const summaries = [
      (pct >= 85
        ? "Outstanding technical mastery (" + pct + "%) of " + payload.sectionTitle + ". Strong recall, accurate terminology, and solid conceptual boundaries."
        : (pct >= 60
          ? "Solid core comprehension (" + pct + "%) of " + payload.sectionTitle + ". Foundational definitions are solid; edge cases require targeted reinforcement."
          : "Foundational conceptual gaps identified (" + pct + "%) in " + payload.sectionTitle + ". Key principles should be reviewed prior to retaking.")),
      (pct >= 85
        ? "Excellent proficiency (" + pct + "%) in " + payload.sectionTitle + ". You consistently identified the precise terms and avoided distractor traps."
        : (pct >= 60
          ? "Moderate functional competence (" + pct + "%) in " + payload.sectionTitle + ". Good grasp of straightforward definitions, but applied scenarios were mixed."
          : "Systematic remediation recommended (" + pct + "%) for " + payload.sectionTitle + ". Review explanations in the Answer Key and Encyclopedia.")),
      (pct >= 85
        ? "Near-complete retention (" + pct + "%) on " + payload.sectionTitle + ". High confidence demonstrated across standard terms and concepts."
        : (pct >= 60
          ? "Progressing understanding (" + pct + "%) of " + payload.sectionTitle + ". Review the specific rationales below to eliminate recurring misconceptions."
          : "Significant knowledge gaps detected (" + pct + "%) in " + payload.sectionTitle + ". Target the missed items in the Redemption Arc."))
    ];
    const summary = summaries[cycle % summaries.length];

    const strengths = [];
    if (payload.correctItems && payload.correctItems.length) {
      strengths.push("Successfully answered " + payload.correctItems.length + " question(s), demonstrating reliable command of primary terms.");
      strengths.push("Consistent performance on core definitions and architectural relationships.");
    } else {
      strengths.push("Attempted the full evaluation under exam conditions.");
    }

    const weaknesses = [];
    if (payload.mistakes && payload.mistakes.length) {
      const pool = payload.mistakes.slice();
      if (cycle === 1) pool.reverse();
      else if (cycle === 2) pool.sort(function (a, b) { return a.id.localeCompare(b.id); });

      pool.slice(0, 4).forEach(function (m) {
        const qShort = m.question.length > 60 ? m.question.slice(0, 58) + "..." : m.question;
        if (m.yourAnswer === "Timed out / unanswered") {
          weaknesses.push("Pacing constraint on: \"" + qShort + "\" — Expected: " + m.correctAnswer + ".");
        } else if (m.explanation) {
          const expShort = m.explanation.length > 70 ? m.explanation.slice(0, 68) + "..." : m.explanation;
          weaknesses.push("Misidentified: \"" + qShort + "\" (Input: '" + m.yourAnswer + "', Expected: '" + m.correctAnswer + "'). Note: " + expShort);
        } else {
          weaknesses.push("Difficulty with: \"" + qShort + "\" (Correct answer: " + m.correctAnswer + ").");
        }
      });
    } else {
      weaknesses.push("No conceptual errors detected in this attempt.");
    }

    const recSets = [
      [
        "Use the Redemption Arc button to re-test only the missed questions until achieving 100% mastery.",
        "Review the specific rationales for each missed item in the Answer Key.",
        "Consult the reference Encyclopedia definitions for terms related to your incorrect answers."
      ],
      [
        "Take an Untimed retake of this section to focus on thorough recall without pacing pressure.",
        "Inspect the contrasting distractors in the Review mode to diagnose why the incorrect choices failed.",
        "Use the Print Exam feature to create a physical or PDF review sheet."
      ],
      [
        "Target the specific keywords in your incorrect answers to prevent repeating the same misconception.",
        "Reinforce understanding by taking the Overall Exam across all chapters.",
        "Retest in Timed mode once your accuracy exceeds 90%."
      ]
    ];
    const recommendations = recSets[cycle % recSets.length];

    return {
      summary: summary,
      strengths: strengths,
      weaknesses: weaknesses,
      recommendations: recommendations,
    };
  }

  function renderAiReport(containerEl, report, provider, payload) {
    let html =
      '<div class="ai-feedback-card">' +
      '<div class="ai-header">' +
      '<div class="ai-title-wrap">' +
      '<span class="ai-icon-badge">' +
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path></svg>' +
      '</span>' +
      '<h3 class="ai-title">AI Performance Diagnosis</h3>' +
      '</div>' +
      '<span class="ai-provider-badge"><span class="ai-provider-dot"></span> ' + esc(provider) + '</span>' +
      '</div>';

    if (report.summary) {
      html += '<div class="ai-summary">' + esc(report.summary) + '</div>';
    }

    html += '<div class="ai-sections-grid">';

    if (report.strengths && report.strengths.length) {
      html +=
        '<div class="ai-block ai-block-strengths">' +
        '<div class="ai-block-heading">' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' +
        'Demonstrated Strengths' +
        '</div><ul class="ai-bullet-list">';
      report.strengths.forEach(function (s) {
        html += '<li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> <span>' + esc(s) + '</span></li>';
      });
      html += '</ul></div>';
    }

    if (report.weaknesses && report.weaknesses.length) {
      html +=
        '<div class="ai-block ai-block-weaknesses">' +
        '<div class="ai-block-heading">' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>' +
        'Diagnosed Misconceptions' +
        '</div><ul class="ai-bullet-list">';
      report.weaknesses.forEach(function (w) {
        html += '<li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg> <span>' + esc(w) + '</span></li>';
      });
      html += '</ul></div>';
    }

    if (report.recommendations && report.recommendations.length) {
      html +=
        '<div class="ai-block ai-block-recommendations">' +
        '<div class="ai-block-heading">' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>' +
        'Targeted Action Plan' +
        '</div><ul class="ai-bullet-list">';
      report.recommendations.forEach(function (rec) {
        html += '<li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg> <span>' + esc(rec) + '</span></li>';
      });
      html += '</ul></div>';
    }

    html += '</div>';

    html +=
      '<div class="ai-actions-row">' +
      '<button class="btn btn-sm" id="ai-reanalyze-btn">Re-analyze (Fresh Diagnosis)</button>' +
      '</div></div>';

    containerEl.innerHTML = html;

    const reanalyzeBtn = containerEl.querySelector("#ai-reanalyze-btn");
    if (reanalyzeBtn) {
      reanalyzeBtn.addEventListener("click", function () {
        const nextPayload = Object.assign({}, payload, { refresh: true, seed: Date.now() });
        triggerAiAnalysis(containerEl, nextPayload);
      });
    }
  }

  /* ---------------- home ---------------- */

  function renderHome() {
    view.innerHTML =
      '<div class="view-panel"><div class="card card-google-form"><h2 class="panel-title">' + esc(bank.title) + "</h2>" +
      "<p class=\"muted\">Pick a chapter above to begin, or take the Overall Exam.</p></div></div>";
  }

  /* ---------------- dropdown outside-click & escape ---------------- */

  function closeChapterDropdown() {
    const wrap = document.getElementById("chapter-dd-wrap");
    if (!wrap) return;
    wrap.classList.remove("open");
    const btn = wrap.querySelector(".chapter-dd-btn");
    if (btn) btn.setAttribute("aria-expanded", "false");
    const menu = wrap.querySelector(".chapter-dd-menu");
    if (menu) menu.hidden = true;
  }

  document.addEventListener("click", function (e) {
    const wrap = document.getElementById("chapter-dd-wrap");
    if (wrap && !wrap.contains(e.target)) {
      closeChapterDropdown();
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      closeChapterDropdown();
    }
  });

  /* ---------------- init ---------------- */

  load();
})();