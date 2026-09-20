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
    let metaHtml = "";
    if (bank.subject) metaHtml += '<span class="meta-tag">' + esc(bank.subject) + "</span>";
    if (bank.summary) metaHtml += '<p class="exam-summary">' + esc(bank.summary) + "</p>";
    document.getElementById("exam-meta").innerHTML = metaHtml;

    const stats = document.getElementById("exam-header-stats");
    if (stats) {
      stats.innerHTML =
        '<div class="stat-tile"><span class="st-value">' + bank.chapters.length + '</span><span class="st-label">Chapters</span></div>' +
        '<div class="stat-tile"><span class="st-value">' + flat.length + '</span><span class="st-label">Questions</span></div>';
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
    view.innerHTML =
      '<div class="view-panel">' +
      '<h2 class="panel-title">' + esc(ch.title) + '</h2>' +
      (ch.description ? '<p class="panel-sub">' + esc(ch.description) + '</p>' : '') +
      '<div class="card start-card">' +
      '<div class="start-meta">' + ch.questions.length + ' questions in this chapter</div>' +
      '<div class="mode-label">Select Mode</div>' +
      '<div class="mode-grid">' +
      '<div class="mode-option ' + (st.mode === "timed" ? "selected" : "") + '" data-mode="timed" role="button" tabindex="0" aria-pressed="' + (st.mode === "timed") + '">' +
      '<div class="mode-option-top">' +
      '<span class="mode-icon-wrap"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></span>' +
      '<span class="mode-badge">10s / question</span>' +
      '</div>' +
      '<h4>Timed Mode</h4><p>10 seconds per question with automatic submission on timeout.</p></div>' +
      '<div class="mode-option ' + (st.mode === "untimed" ? "selected" : "") + '" data-mode="untimed" role="button" tabindex="0" aria-pressed="' + (st.mode === "untimed") + '">' +
      '<div class="mode-option-top">' +
      '<span class="mode-icon-wrap"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg></span>' +
      '<span class="mode-badge">Self-paced</span>' +
      '</div>' +
      '<h4>Untimed Mode</h4><p>No time limit. Take as much time as you need per question.</p></div>' +
      '</div>' +
      '<div class="start-actions">' +
      '<button class="btn btn-primary" id="start-id">Start Chapter</button>' +
      '<button class="btn" id="shuffle-again">Shuffle Order</button>' +
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
        '<span class="choice-text">' + esc(q.choices[origIdx]) + "</span></button></li>";
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
    const q = chapterById(chId).questions.find(function (x) { return x.id === qid; });
    const correct = origIdx === q.correctAnswer;
    st.answers[qid] = { selected: origIdx, correct: correct, timedOut: false, at: choiceIdx };
    st.answered = Object.keys(st.answers).length;
    if (correct) { st.correct += 1; st.score += 1; } else { st.incorrect += 1; }
    clearTimer(st);
    renderChapter(chId);
  }

  function answerChapterTimeout(chId, qid) {
    const st = getChapterState(chId);
    if (st.answers[qid]) return; // already answered
    st.answers[qid] = { selected: null, correct: false, timedOut: true, at: null };
    st.answered = Object.keys(st.answers).length;
    st.incorrect += 1;
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
      '<button class="btn" id="retake-btn">Retake (reset)</button>' +
      '<button class="btn" id="again-btn">Shuffle &amp; Retake</button>' +
      "</div></div></div>";

    document.getElementById("review-btn").addEventListener("click", function () {
      renderReview(chId);
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
      if (sel != null && sel === q.correctAnswer) correct += 1;
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
      '<div class="result-actions"><button class="btn" id="retake-overall">Retake</button></div>' +
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

    document.getElementById("retake-overall").addEventListener("click", function () {
      overall = { started: false, submitted: false, selected: {}, score: 0, correct: 0, incorrect: 0 };
      renderNav();
      renderOverall();
    });
  }

  /* ---------------- answer key ---------------- */

  function renderAnswerKey() {
    let html = '<div class="view-panel"><h2 class="panel-title">Answer Key</h2>' +
      '<p class="panel-sub">Correct answer for every question.</p>';

    bank.chapters.forEach(function (ch) {
      html += '<div class="key-chapter"><h3>' + esc(ch.title) + "</h3><ol class=\"key-list\">";
      ch.questions.forEach(function (q) {
        html += "<li><span class=\"key-letter\">" + LETTERS[q.correctAnswer] + "</span> " +
          esc(q.choices[q.correctAnswer]) + "</li>";
      });
      html += "</ol></div>";
    });
    html += "</div>";
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

  /* ---------------- home ---------------- */

  function renderHome() {
    view.innerHTML =
      '<div class="view-panel"><div class="card"><h2 class="panel-title">' + esc(bank.title) + "</h2>" +
      "<p class=\"muted\">Pick a chapter above to begin, or take the Overall Exam.</p></div></div>";
  }

  /* ---------------- init ---------------- */

  load();
})();