(function () {
  "use strict";

  const examId = document.body.dataset.examId;
  const view = document.getElementById("view");
  const navEl = document.getElementById("exam-nav");

  let bank = null;
  let flat = []; // [{ch, q}]
  let currentView = null; // 'ch|ch1' | 'overall' | 'answerkey' | 'encyclopedia'

  const LETTERS = ["A", "B", "C", "D"];

  // chapterState[ch.chapterId] = {mode, started, completed, order[], index, score, correct, incorrect, answers{}, choiceOrder{}, timer}
  const chapterState = {};
  let overall = null; // {started, submitted, selected{}, score, correct, incorrect}

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

  function recordMistake(q, ch, selectedIdx) {
    if (!q) return;
    const existing = mistakeBank[q.id] || {
      id: q.id,
      chapterId: ch ? ch.chapterId : "",
      chapterTitle: ch ? ch.title : "General",
      question: q.question,
      choices: q.choices,
      correctAnswer: q.correctAnswer,
      explanation: q.explanation,
      wrongCount: 0,
      lastSelected: null,
      lastMissedAt: null,
    };
    existing.wrongCount += 1;
    existing.lastSelected = selectedIdx != null ? q.choices[selectedIdx] : null;
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
    if (e.target && (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA")) return;
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

  /* ---------------- data loading ---------------- */

  function initBank(data) {
    if (!data || !Array.isArray(data.chapters) || !data.chapters.length) {
      throw new Error("Question bank is empty or malformed");
    }
    bank = data;
    flat = [];
    data.chapters.forEach(function (ch) {
      ch.questions.forEach(function (q) { flat.push({ ch: ch, q: q }); });
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

    /* AI Assessment badge */
    metaHtml +=
      '<span class="meta-chip meta-chip-ai">' +
        '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>' +
        'AI Studio 2.0' +
      '</span>';

    /* Inline stat chips */
    metaHtml +=
      '<span class="meta-chip meta-chip-chapters">' +
        '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>' +
        bank.chapters.length + " Chapters" +
      "</span>" +
      '<span class="meta-chip meta-chip-questions">' +
        '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
        flat.length + " Questions" +
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

    bank.chapters.forEach(function (ch) {
      const s = chapterState[ch.chapterId];
      const done = s && s.completed;
      const isCurrent = currentView === "ch|" + ch.chapterId;
      const btn = makeTab(
        ch.title,
        "ch|" + ch.chapterId,
        done ? '<span class="chk" aria-hidden="true">\u2713</span>' : "",
        lock && lock !== ch.chapterId,
        isCurrent
      );
      cont.appendChild(btn);
    });

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
    b.innerHTML = esc(label) + (suffix ? " " + suffix : "");
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
      renderNav(); // re-affirm lock state
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

  function freshChapterState(chId) {
    const ch = chapterById(chId);
    const order = shuffle(ch.questions.map(function (q) { return q.id; }));
    const choiceOrder = {};
    ch.questions.forEach(function (q) {
      choiceOrder[q.id] = shuffle([0, 1, 2, 3]);
    });
    chapterState[chId] = {
      mode: null,
      started: false,
      completed: false,
      order: order,
      index: 0,
      score: 0,
      correct: 0,
      incorrect: 0,
      answered: 0,
      answers: {},
      choiceOrder: choiceOrder,
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
    if (!st.started || !st.mode) {
      renderModeSelect(ch);
      return;
    }
    renderQuestion(ch);
  }

  function renderModeSelect(ch) {
    const st = getChapterState(ch.chapterId);
    const chIdx = bank.chapters.findIndex(function(c) { return c.chapterId === ch.chapterId; });
    const chNum = chIdx >= 0 ? (chIdx + 1) : 1;
    const totalCh = bank.chapters.length;

    view.innerHTML =
      '<div class="view-panel">' +
      '<div class="panel-eyebrow">' +
        '<span class="eyebrow-pill">Chapter ' + chNum + ' of ' + totalCh + '</span>' +
      '</div>' +
      '<h2 class="panel-title">' + esc(ch.title) + '</h2>' +
      (ch.description ? '<p class="panel-sub">' + esc(ch.description) + '</p>' : '') +
      '<div class="card start-card">' +
      '<div class="start-card-header">' +
        '<div class="start-meta-pill">' +
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
          '<span>' + ch.questions.length + ' Questions in this chapter</span>' +
        '</div>' +
        '<div class="start-card-tip">Pick a test mode below to begin</div>' +
      '</div>' +
      '<div class="mode-label-row">' +
        '<span class="mode-label">Select Mode</span>' +
      '</div>' +
      '<div class="mode-grid">' +
      '<div class="mode-option ' + (st.mode === "timed" ? "selected" : "") + '" data-mode="timed" role="button" tabindex="0" aria-pressed="' + (st.mode === "timed") + '">' +
      '<div class="mode-option-top">' +
      '<span class="mode-icon-wrap"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></span>' +
      '<span class="mode-badge mode-badge-timed">10s / question</span>' +
      '</div>' +
      '<h4>Timed Mode</h4><p>10 seconds per question with automatic submission on timeout. Tests rapid recall and instant mastery under pressure.</p></div>' +
      '<div class="mode-option ' + (st.mode === "untimed" ? "selected" : "") + '" data-mode="untimed" role="button" tabindex="0" aria-pressed="' + (st.mode === "untimed") + '">' +
      '<div class="mode-option-top">' +
      '<span class="mode-icon-wrap"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg></span>' +
      '<span class="mode-badge mode-badge-untimed">Self-paced</span>' +
      '</div>' +
      '<h4>Untimed Mode</h4><p>No time limit. Take as much time as you need to analyze scenarios and review detailed rationale.</p></div>' +
      '</div>' +
      '<div class="start-actions">' +
      '<button class="btn btn-primary btn-start-chapter" id="start-id">' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>' +
        '<span>Start Chapter</span>' +
      '</button>' +
      '<button class="btn btn-shuffle" id="shuffle-again">' +
        '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>' +
        '<span>Shuffle Order</span>' +
      '</button>' +
      '</div>' +
      '</div></div>';

    view.querySelectorAll(".mode-option").forEach(function (el) {
      const pick = function () {
        st.mode = el.dataset.mode;
        view.querySelectorAll(".mode-option").forEach(function (o) {
          o.classList.toggle("selected", o === el);
          o.setAttribute("aria-pressed", o === el ? "true" : "false");
        });
      };
      el.addEventListener("click", pick);
      el.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") { e.preventDefault(); pick(); }
      });
    });

    document.getElementById("shuffle-again").addEventListener("click", function () {
      freshChapterState(ch.chapterId);
      renderModeSelect(ch);
    });

    document.getElementById("start-id").addEventListener("click", function () {
      if (!st.mode) {
        toast("Choose Timed or Untimed first.");
        return;
      }
      st.started = true;
      st.index = 0;
      renderNav();
      renderChapter(ch.chapterId);
    });
  }

  function currentQ(ch, st, displayIndex) {
    const qid = st.order[displayIndex];
    const q = ch.questions.find(function (x) { return x.id === qid; });
    return q;
  }

  function renderQuestion(ch) {
    const st = getChapterState(ch.chapterId);
    const pos = st.index;
    const q = currentQ(ch, st, pos);
    if (!q) {
      completeChapter(ch.chapterId);
      return;
    }

    const total = ch.questions.length;
    const displayNum = pos + 1;
    const answered = st.answers[q.id];
    const choiceOrder = st.choiceOrder[q.id];
    const isTimed = st.mode === "timed";
    const pct = Math.round((pos / total) * 100);

    let html =
      '<div class="view-panel q-view">' +
      '<div class="q-header">' +
      '<div class="q-head-left">' +
      '<span class="q-chapter-badge">' + esc(ch.title) + '</span>' +
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
      '<div class="q-card">' +
      '<p class="q-text">' + esc(q.question) + "</p>";

    html += '<ul class="choices">';
    for (let i = 0; i < 4; i++) {
      const origIdx = choiceOrder[i];
      const cls = [];
      if (answered) {
        if (origIdx === q.correctAnswer) cls.push("correct");
        if (answered.selected === origIdx && origIdx !== q.correctAnswer) cls.push("incorrect");
      }
      html += '<li><button class="choice ' + cls.join(" ") + '" data-choice="' + i + '"' + (answered ? " disabled" : "") + ">" +
        '<span class="letter">' + LETTERS[i] + "</span>" +
        '<span class="choice-text">' + esc(q.choices[origIdx]) + "</span>" +
        (!answered ? '<span class="choice-shortcut"><kbd>' + LETTERS[i] + '</kbd></span>' : '') +
        "</button></li>";
    }
    html += "</ul>";

    if (answered) {
      let cls = "correct", title = "Correct!", expl = esc(q.explanation);
      if (answered.timedOut) { cls = "timeout"; title = "Time's up!"; }
      else if (!answered.correct) { cls = "incorrect"; title = "Incorrect."; }
      html += '<div class="feedback ' + cls + '"><span class="fb-title">' + title + "</span>" +
        "<strong>Correct answer: " + LETTERS[choiceOrder.indexOf(q.correctAnswer)] + ".</strong> " +
        "<span>" + expl + "</span></div>";
      if (answered.timedOut) {
        html += "<p style=\"color:var(--muted);font-size:13px;\">Out of time &mdash; counted as incorrect. Score: " +
          st.correct + "/" + (st.correct + st.incorrect) + ".</p>";
      }
      html += '<div class="next-row"><button class="btn btn-primary" id="next-btn">' +
        (pos + 1 >= total ? "Finish Chapter" : "Next Question") + "</button></div>";
    }

    html += "</div>" +
      '<div class="kbd-hint"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="M6 8h.001M10 8h.001M14 8h.001M18 8h.001M8 12h.001M12 12h.001M16 12h.001M7 16h10"></path></svg> <span>Press <kbd>A</kbd>&ndash;<kbd>D</kbd> or <kbd>1</kbd>&ndash;<kbd>4</kbd> to select &bull; <kbd>Enter</kbd> to advance</span></div>' +
      '<div class="next-row"><button class="reset-link" id="reset-link">Reset chapter (choose mode again)</button></div>' +
      "</div>";

    view.innerHTML = html;

    if (!answered) {
      const idxQid = q.id;
      view.querySelectorAll(".choice").forEach(function (btn) {
        btn.addEventListener("click", function () {
          const choiceIdx = parseInt(btn.dataset.choice, 10);
          const origIdx = st.choiceOrder[idxQid][choiceIdx];
          answerChapterCurrent(ch.chapterId, idxQid, origIdx, choiceIdx);
        });
      });
    } else {
      document.getElementById("next-btn").addEventListener("click", function () {
        clearTimer(st);
        st.index += 1;
        renderChapter(ch.chapterId);
      });
    }

    document.getElementById("reset-link").addEventListener("click", function () {
      if (confirm("Reset this chapter? Your score and progress will be cleared, and you can pick a new mode.")) {
        resetChapter(ch.chapterId);
      }
    });

    if (!answered && isTimed) {
      startTimer(ch.chapterId, q.id);
    }
  }

  function startTimer(chId, qid) {
    const st = getChapterState(chId);
    clearTimer(st);
    let left = 10;
    const el = view.querySelector("[data-timer]");
    if (el) { el.textContent = left; el.classList.remove("low"); }

    st.timer = setInterval(function () {
      // guard: only tick if the locked chapter is still this one
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
          answerChapterTimeout(chId, qid);
        }
      }
    }, 1000);
  }

  function answerChapterCurrent(chId, qid, origIdx, choiceIdx) {
    const st = getChapterState(chId);
    const ch = chapterById(chId);
    const q = ch.questions.find(function (x) { return x.id === qid; });
    const correct = origIdx === q.correctAnswer;
    st.answers[qid] = { selected: origIdx, correct: correct, timedOut: false, at: choiceIdx };
    st.answered = Object.keys(st.answers).length;
    if (correct) {
      st.correct += 1;
      st.score += 1;
      if (mistakeBank[qid]) resolveMistake(qid);
    } else {
      st.incorrect += 1;
      recordMistake(q, ch, origIdx);
    }
    clearTimer(st);
    renderChapter(chId);
  }

  function answerChapterTimeout(chId, qid) {
    const st = getChapterState(chId);
    if (st.answers[qid]) return; // already answered
    const ch = chapterById(chId);
    const q = ch ? ch.questions.find(function (x) { return x.id === qid; }) : null;
    st.answers[qid] = { selected: null, correct: false, timedOut: true, at: null };
    st.answered = Object.keys(st.answers).length;
    st.incorrect += 1;
    if (q) recordMistake(q, ch, null);
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
    freshChapterState(chId);
    renderNav();
    renderChapter(chId);
  }

  function renderChapterResults(chId) {
    const ch = chapterById(chId);
    const st = getChapterState(chId);
    const total = ch.questions.length;
    const pct = total ? Math.round((st.correct / total) * 100) : 0;

    view.innerHTML =
      '<div class="view-panel">' +
      '<h2 class="panel-title">Chapter complete</h2>' +
      '<p class="panel-sub">' + esc(ch.title) + "</p>" +
      '<div class="card">' +
      '<div class="result-big">' + pct + "%</div>" +
      '<div class="result-grid">' +
      '<div class="result-cell"><span class="rc-value" style="color:var(--green)">' + st.correct + "</span><span class=\"rc-label\">Correct</span></div>" +
      '<div class="result-cell"><span class="rc-value" style="color:var(--red)">' + st.incorrect + "</span><span class=\"rc-label\">Incorrect</span></div>" +
      '<div class="result-cell"><span class="rc-value">' + st.correct + "/" + total + "</span><span class=\"rc-label\">Score</span></div>" +
      '<div class="result-cell"><span class="rc-value">' + (st.mode === "timed" ? "Timed" : "Untimed") + "</span><span class=\"rc-label\">Mode</span></div>" +
      "</div>" +
      '<div class="result-actions">' +
      '<button class="btn btn-primary" id="review-btn">Review Answers</button>' +
      '<button class="btn btn-ai" id="ai-analyze-btn"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path></svg> <span>AI Diagnosis</span></button>' +
      '<button class="btn" id="retake-btn">Retake (reset)</button>' +
      '<button class="btn" id="again-btn">Shuffle &amp; Retake</button>' +
      "</div>" +
      '<div id="ai-feedback-container" class="ai-feedback-container"></div>' +
      "</div></div>";

    document.getElementById("review-btn").addEventListener("click", function () {
      renderReview(chId);
    });
    document.getElementById("ai-analyze-btn").addEventListener("click", function () {
      const mistakes = [];
      const correctItems = [];
      ch.questions.forEach(function (q) {
        const a = st.answers[q.id];
        if (a && a.correct) {
          correctItems.push({ id: q.id, question: q.question });
        } else {
          mistakes.push({
            id: q.id,
            question: q.question,
            yourAnswer: a && a.selected != null ? q.choices[a.selected] : "Timed out / unanswered",
            correctAnswer: q.choices[q.correctAnswer],
            explanation: q.explanation,
          });
        }
      });
      const containerEl = document.getElementById("ai-feedback-container");
      triggerAiAnalysis(containerEl, {
        examId: examId,
        sectionTitle: ch.title,
        score: st.correct,
        total: total,
        mistakes: mistakes,
        correctItems: correctItems,
      });
    });
    document.getElementById("retake-btn").addEventListener("click", function () {
      resetChapter(chId);
    });
    document.getElementById("again-btn").addEventListener("click", function () {
      freshChapterState(chId);
      renderModeSelect(ch);
    });
  }

  function renderReview(chId) {
    const ch = chapterById(chId);
    const st = getChapterState(chId);
    const items = ch.questions.map(function (q) {
      const a = st.answers[q.id];
      const chosen = a ? a.selected : null;
      const ok = !!(a && a.correct);
      const verdict = ok ? "Correct" : (a && a.timedOut ? "Time's up (incorrect)" : "Incorrect");
      const letter = a && a.at != null ? LETTERS[a.at] : "-";
      return (
        '<div class="review-item">' +
        '<p class="rq">' + esc(q.question) + "</p>" +
        '<div class="ans-line"><span class="' + (ok ? "ok" : "no") + '">' + verdict + "</span> &mdash; Your answer: <strong>" +
        (chosen == null ? "(none)" : esc(q.choices[chosen]) + " (" + letter + ")") + "</strong></div>" +
        '<div class="ans-line"><span class="ok">Correct answer: ' + LETTERS[q.correctAnswer] + ".</span> " + esc(q.choices[q.correctAnswer]) + "</div>" +
        '<div class="review-expl"><strong>Explanation:</strong> ' + esc(q.explanation) + "</div>" +
        "</div>"
      );
    });
    view.innerHTML =
      '<div class="view-panel">' +
      '<h2 class="panel-title">Review &mdash; ' + esc(ch.title) + "</h2>" +
      '<p class="panel-sub">Score: ' + st.correct + "/" + ch.questions.length + "</p>" +
      '<div class="next-row" style="justify-content:flex-start"><button class="btn" id="back-results">Back to results</button></div>' +
      items.join("") +
      "</div>";

    document.getElementById("back-results").addEventListener("click", function () {
      switchTo("ch|" + chId);
    });
  }

  /* ---------------- overall exam ---------------- */

  function renderOverall() {
    if (!overall) {
      overall = { started: false, submitted: false, selected: {}, score: 0, correct: 0, incorrect: 0 };
    }
    if (overall.submitted) { renderOverallResults(); return; }
    if (!overall.started) {
      view.innerHTML =
        '<div class="view-panel">' +
        '<h2 class="panel-title">Overall Exam</h2>' +
        '<p class="panel-sub">Combines every chapter. Untimed, Google Forms-style. You can change answers until you submit.</p>' +
        '<div class="card"><p class="muted">' + flat.length + " questions across " + bank.chapters.length + " chapters.</p>" +
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
    html += "<h2 class=\"panel-title\">Overall Exam</h2>" +
      '<p class="panel-sub">Untimed &middot; Answer all questions, then press submit. No time limit.</p>';

    flat.forEach(function (item, idx) {
      const q = item.q;
      const chosen = overall.selected[q.id];
      html += '<div class="overall-q">' +
        '<div class="oq-head"><span class="oq-num">' + (idx + 1) + '. ' + esc(q.question) + "</span>" +
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
    });

    html += '<div class="next-row"><button class="btn btn-primary" id="submit-overall">Submit Overall Exam</button>' +
      '<button class="btn" id="reset-overall">Reset</button></div></div>';

    view.innerHTML = html;

    view.querySelectorAll('input[type="radio"]').forEach(function (inp) {
      inp.addEventListener("change", function () {
        overall.selected[inp.name.replace("oq-", "")] = parseInt(inp.value, 10);
      });
    });

    document.getElementById("submit-overall").addEventListener("click", function () {
      if (confirm("Submit the Overall Exam? You cannot change answers afterward.")) {
        submitOverall();
      }
    });
    document.getElementById("reset-overall").addEventListener("click", function () {
      overall = { started: false, submitted: false, selected: {}, score: 0, correct: 0, incorrect: 0 };
      renderNav();
      renderOverall();
    });
  }

  function submitOverall() {
    let correct = 0;
    flat.forEach(function (item) {
      const q = item.q;
      const sel = overall.selected[q.id];
      if (sel != null && sel === q.correctAnswer) {
        correct += 1;
        if (mistakeBank[q.id]) resolveMistake(q.id);
      } else {
        recordMistake(q, item.ch, sel);
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
      '<div class="card">' +
      '<div class="result-big">' + pct + "%</div>" +
      '<div class="result-grid">' +
      '<div class="result-cell"><span class="rc-value" style="color:var(--green)">' + overall.correct + "</span><span class=\"rc-label\">Correct</span></div>" +
      '<div class="result-cell"><span class="rc-value" style="color:var(--red)">' + overall.incorrect + "</span><span class=\"rc-label\">Incorrect</span></div>" +
      '<div class="result-cell"><span class="rc-value">' + overall.correct + "/" + total + "</span><span class=\"rc-label\">Score</span></div>" +
      "</div>" +
      '<div class="result-actions">' +
      '<button class="btn btn-ai" id="ai-analyze-overall-btn"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path></svg> <span>AI Diagnosis</span></button>' +
      '<button class="btn" id="retake-overall">Retake</button>' +
      '</div>' +
      '<div id="ai-overall-feedback-container" class="ai-feedback-container"></div>' +
      "</div>";

    flat.forEach(function (item, idx) {
      const q = item.q;
      const sel = overall.selected[q.id];
      const ok = sel === q.correctAnswer;
      html += '<div class="overall-q submitted">' +
        '<div class="oq-head"><span class="oq-num">' + (idx + 1) + '. ' + esc(q.question) + "</span>" +
        '<span class="oq-chapter">' + esc(item.ch.title) + "</span></div>";
      q.choices.forEach(function (c, ci) {
        let cls = "opt";
        if (ci === q.correctAnswer) cls += " good";
        else if (sel === ci) cls += " bad cho";
        html += '<div class="' + cls + '"><span class="opt-letter">' + LETTERS[ci] + '.</span> ' +
          '<span class="opt-text">' + esc(c) + "</span></div>";
      });
      html += '<div class="oq-result" style="color:' + (ok ? "var(--green)" : "var(--red)") + '">' +
        (ok ? "Correct" : sel == null ? "Not answered" : "Incorrect") + "</div>" +
        '<div class="review-expl"><strong>Explanation:</strong> ' + esc(q.explanation) + "</div>" +
        "</div>";
    });
    html += "</div>";

    view.innerHTML = html;

    document.getElementById("ai-analyze-overall-btn").addEventListener("click", function () {
      const mistakes = [];
      const correctItems = [];
      flat.forEach(function (item) {
        const q = item.q;
        const sel = overall.selected[q.id];
        if (sel != null && sel === q.correctAnswer) {
          correctItems.push({ id: q.id, question: q.question });
        } else {
          mistakes.push({
            id: q.id,
            question: q.question,
            yourAnswer: sel != null ? q.choices[sel] : "Not answered",
            correctAnswer: q.choices[q.correctAnswer],
            explanation: q.explanation,
          });
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
      overall = { started: false, submitted: false, selected: {}, score: 0, correct: 0, incorrect: 0 };
      renderNav();
      renderOverall();
    });
  }

  /* ---------------- answer key ---------------- */

  function renderAnswerKey() {
    let html = '<div class="view-panel"><h2 class="panel-title">Answer Key</h2>' +
      '<p class="panel-sub">Complete questions, correct answers, and explanations for every topic.</p>';

    bank.chapters.forEach(function (ch) {
      html += '<div class="key-chapter"><h3>' + esc(ch.title) + '</h3><div class="key-list">';
      ch.questions.forEach(function (q, idx) {
        const correctLetter = LETTERS[q.correctAnswer] || "";
        const correctChoice = q.choices && q.choices[q.correctAnswer] != null ? q.choices[q.correctAnswer] : "";
        html += '<div class="key-item">' +
          '<div class="key-item-header">' +
            '<span class="key-num">Q' + (idx + 1) + '</span>' +
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
      html += '</div></div>';
    });
    html += '</div>';
    view.innerHTML = html;
  }

  /* ---------------- encyclopedia ---------------- */

  function renderEncyclopedia() {
    let html = '<div class="view-panel"><h2 class="panel-title">Encyclopedia</h2>' +
      '<p class="panel-sub">Key terms from the reference material.</p>';

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
        '<div class="card">' +
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
      html +=
        '<div class="mistake-item">' +
        '<div class="mistake-item-head">' +
        '<span class="mistake-chapter-tag">' + esc(m.chapterTitle) + '</span>' +
        '<span class="mistake-wrong-count">Missed ' + m.wrongCount + 'x</span>' +
        '</div>' +
        '<p class="rq">' + (idx + 1) + '. ' + esc(m.question) + '</p>' +
        (m.lastSelected ? '<div class="ans-line"><span class="no">Your previous answer:</span> ' + esc(m.lastSelected) + '</div>' : '') +
        '<div class="ans-line"><span class="ok">Correct answer: ' + LETTERS[m.correctAnswer] + '.</span> ' + esc(m.choices[m.correctAnswer]) + '</div>' +
        '<div class="review-expl"><strong>Explanation:</strong> ' + esc(m.explanation) + '</div>' +
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
      questions: list.map(function (m) {
        return {
          id: m.id,
          question: m.question,
          choices: m.choices,
          correctAnswer: m.correctAnswer,
          explanation: m.explanation,
        };
      }),
    };
    chapterState["__mistakes__"] = {
      mode: mode,
      started: true,
      completed: false,
      order: shuffle(fakeCh.questions.map(function (q) { return q.id; })),
      index: 0,
      score: 0,
      correct: 0,
      incorrect: 0,
      answered: 0,
      answers: {},
      choiceOrder: {},
      timer: null,
    };
    fakeCh.questions.forEach(function (q) {
      chapterState["__mistakes__"].choiceOrder[q.id] = shuffle([0, 1, 2, 3]);
    });
    
    const existingIdx = bank.chapters.findIndex(function (c) { return c.chapterId === "__mistakes__"; });
    if (existingIdx !== -1) bank.chapters.splice(existingIdx, 1);
    bank.chapters.push(fakeCh);

    currentView = "ch|__mistakes__";
    renderNav();
    renderChapter("__mistakes__");
  }

  /* ---------------- AI Diagnosis ---------------- */

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
        ? "Outstanding technical mastery (" + pct + "%) of " + payload.sectionTitle + ". Strong recall, accurate syntax, and solid conceptual boundaries."
        : (pct >= 60
          ? "Solid core comprehension (" + pct + "%) of " + payload.sectionTitle + ". Foundational definitions are solid; edge cases require targeted reinforcement."
          : "Foundational conceptual gaps identified (" + pct + "%) in " + payload.sectionTitle + ". Key principles should be reviewed prior to retaking.")),
      (pct >= 85
        ? "Excellent proficiency (" + pct + "%) in " + payload.sectionTitle + ". You consistently navigated past common distractor traps."
        : (pct >= 60
          ? "Moderate functional competence (" + pct + "%) in " + payload.sectionTitle + ". Good grasp of straightforward questions, but applied scenarios were mixed."
          : "Systematic remediation recommended (" + pct + "%) for " + payload.sectionTitle + ". Review explanations in the Answer Key and Encyclopedia.")),
      (pct >= 85
        ? "Near-complete retention (" + pct + "%) on " + payload.sectionTitle + ". High confidence demonstrated across standard command syntaxes and concepts."
        : (pct >= 60
          ? "Progressing understanding (" + pct + "%) of " + payload.sectionTitle + ". Review the specific rationales below to eliminate recurring misconceptions."
          : "Significant knowledge gaps detected (" + pct + "%) in " + payload.sectionTitle + ". Target the missed items in the Mistakes bank."))
    ];
    const summary = summaries[cycle % summaries.length];

    const strengths = [];
    if (payload.correctItems && payload.correctItems.length) {
      strengths.push("Successfully answered " + payload.correctItems.length + " question(s), demonstrating reliable command of primary definitions.");
      strengths.push("Consistent performance on standard operational syntax and terminology.");
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
          weaknesses.push("Misidentified: \"" + qShort + "\" (Chose: '" + m.yourAnswer + "', Expected: '" + m.correctAnswer + "'). Note: " + expShort);
        } else {
          weaknesses.push("Difficulty with: \"" + qShort + "\" (Correct answer: " + m.correctAnswer + ").");
        }
      });
    } else {
      weaknesses.push("No conceptual errors detected in this attempt.");
    }

    const recSets = [
      [
        "Review the specific rationales for each missed question in the Answer Key.",
        "Practice using the 'Mistakes' review session to re-test missed items until achieving 100% mastery.",
        "Consult the reference Encyclopedia definitions for terms related to your incorrect choices."
      ],
      [
        "Take an Untimed retake of this section to focus on thorough comprehension without pacing pressure.",
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
      '<div class="view-panel"><div class="card"><h2 class="panel-title">' + esc(bank.title) + "</h2>" +
      "<p class=\"muted\">Pick a chapter above to begin, or take the Overall Exam.</p></div></div>";
  }

  /* ---------------- init ---------------- */

  load();
})();