---
title: Exam Generator
emoji: 📝
colorFrom: indigo
colorTo: blue
sdk: docker
app_port: 7860
---

# SynthExam — PDF → Interactive Comprehensive Exam Web App

Upload a PDF (lecture notes, slides, reviewer, textbook). The app hands the PDF to
[opencode](https://opencode.ai) running the **`interactive-comprehensive-exam-generator`**
skill, which builds a complete interactive exam: ~30 questions per chapter,
Timed (10 s/question) / Untimed chapter modes, an always-Untimed Overall Exam,
immediate feedback, real-time scoring, chapter locking, shuffle/reset, an Answer Key,
and an Encyclopedia — all grounded in the uploaded PDF.

## How it works

```
Browser (index.php upload)
   │  POST api/generate.php
   ▼
PHP saves PDF, creates job id, writes a PowerShell launcher
   │  (detached process)
   ▼
worker.php (CLI):
   1) opencode run produces plan.json (detects the source's chapters)
   2) one opencode run per chapter, up to CONCURRENCY at a time (parallel)
   3) PHP validates each chapter bank and merges them into questions.json
   │
   ▼  each opencode run loads the skill and reads the extracted source
PHP validates the JSON (4 choices, correctAnswer 0..3, explanations)
   ▼
exam.php?id=<id> + assets/exam-app.js render the interactive exam
```

Question banks are stored as JSON in `data/exams/<id>/questions.json`; metadata lives
in a SQLite database at `data/exam.db`. Generation runs in the background; the upload
page polls `api/status.php` and opens the exam when ready.

## Requirements

- PHP 8.2+ with `pdo_sqlite` (included by default in XAMPP)
- The `opencode` CLI installed (global npm install)
- Apache running (for the htdocs URL)

## Setup

1. Put this folder anywhere under `C:\xampp\htdocs\`. It is already at
   `C:\xampp\htdocs\automation\side-project`.
2. Make sure your opencode config has a working model/provider (whatever you normally use).
3. Check `lib/config.php`:
   - `OPENCODE_EXE` — absolute path to `opencode.exe`
     (default: `%APPDATA%\npm\node_modules\opencode-ai\bin\opencode.exe`).
   - `PHP_EXE` — absolute path to the CLI PHP (default: `C:\xampp\php\php.exe`).
     Override either with the `OPENCODE_EXE` / `PHP_EXE` environment variables.
   - `CONCURRENCY` — max parallel per-chapter opencode runs (default 3).
4. Start Apache (XAMPP Control → Apache, or `httpd.exe`).
5. Open **http://localhost/automation/side-project/**.

The skill itself is bundled in the project at
`.opencode/skills/interactive-comprehensive-exam-generator/SKILL.md` so opencode
auto-discovers it. It is a copy of your `.agents` skill — update it there if you
tune the skill.

## Usage

- **Create**: upload a PDF (≤ 50 MB). Optionally set a title. Watch the status
  spinner; the page auto-opens the exam when done.
- **Take**: chapters → pick **Timed** (10 s/question, timeouts = incorrect) or
  **Untimed** → Start. One question at a time, instant feedback + explanation,
  live score/progress, Shuffle & Reset per chapter.
- **Overall Exam**: combines all chapters, always Untimed, Google Forms style.
- **Answer Key** and **Encyclopedia**: available when no attempt is in progress.
- While a chapter attempt is active, all other sections are locked until you
  finish or reset that chapter.

## Notes / limitations

- **Permissions:** the worker invokes opencode with `--auto` so it can read the
  PDF and write the question bank without interactive prompts. Generation runs
  only on YOUR machine; this is a local tool, not a remote service.
- **Cost/time:** chapters are generated in parallel (up to `CONCURRENCY` runs at
  once), so a 7-chapter exam typically completes much faster than a single serial
  run and produces hundreds of questions; plan API usage accordingly. Large or
  image-heavy PDFs increase token use.
- **Accuracy:** the skill treats the PDF as the source of truth. Verify generated
  questions before formal use (an "Answer Key" tab exists for quick audit).
- **Job lifecycle:** `queued → running → done | error | cancelled`. Jobs can be
  cancelled from the upload page or the exam list; an estimated time remaining is
  shown while a job runs (based on past job history). Jobs stuck longer than
  30 minutes are auto-marked `error`.

## Project layout

```
index.php              upload page + exam list + backend health
exam.php               interactive exam page (fetches the bank via api/data.php)
worker.php             CLI worker that runs opencode with the skill
api/generate.php       upload + spawn detached worker (+ delete op)
api/status.php         job status polling (+ health ping)
api/data.php           validated delivery of a finished question bank
lib/config.php         paths, limits, skill name
lib/db.php             SQLite metadata (exams table)
assets/                site + exam app CSS/JS
data/                  exam.db, job folders, logs (web-inaccessible)
.opencode/skills/      the bundled examiner skill
```