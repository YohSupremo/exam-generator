---
name: reviewer-quiz-builder
description: Build an interactive, self-contained HTML quiz from a reviewer, lecture PDF, notes, or textbook, with Identification and Multiple Choice modes. Classify questions by knowledge fitness: Identification tests stable, universally identifiable named terms; Multiple Choice tests source/textbook-dependent information such as benefits, advantages, challenges, characteristics, purposes, examples, comparisons, and list items. Use whenever the user wants an exam, quiz, reviewer test, identification test, interactive exam, practice questions, or Q&A PDF from uploaded course material.
---

# Reviewer Quiz Builder

Turns course material into one self-contained HTML quiz with typed-answer Identification and Multiple Choice modes, organized per lesson, with instant feedback, shuffling, mistake review, and a repeatable "Redemption Arc." Optionally builds a Q&A PDF.

## 1. Read the Source First

- Read the entire reference: every lesson, chapter, section, or equivalent unit.
- Preserve the source's terminology, organization, framing, and level of detail.
- Ignore headers, page numbers, instructor names, and other non-content metadata unless they are explicitly part of the material.
- Never silently replace source terminology with general knowledge or a different textbook's terminology.
- Never invent a definition, term, relationship, benefit, or classification that the source does not support.
- Build a list of **term families** and **source-information families** per lesson before writing questions.

## 2. Question-Type Fitness Test — CRITICAL

Do not divide questions merely into "terms" and "non-terms."

The primary distinction is:

> **Identification = universal/stable term knowledge.**
>
> **Multiple Choice = source/textbook-dependent information.**

Every candidate question must pass the fitness test before being assigned a mode.

### 2.1 Identification — Universal Term Knowledge

Use Identification ONLY when all or nearly all of these conditions are satisfied:

1. The answer is a recognized, definable term, concept, model, pattern, framework, protocol, component, phase, artifact, principle, acronym, or other named concept.
2. The clue uniquely identifies the term from its definition, function, structure, or defining characteristics.
3. The answer does not depend on the author's particular list, categorization, ordering, wording, interpretation, or stated advantage/disadvantage.
4. Another textbook could explain the same concept using different wording while the underlying answer would remain the same.
5. The question is asking **WHAT the concept/term IS or WHAT it is called**, not what the source says about it.

Example:

Source:
"An integration pattern where a central hub routes and translates communication between systems."

Valid Identification:
"An integration pattern where a central hub routes and translates communication between systems."
Answer: `Hub-and-spoke integration`

The clue identifies a named integration pattern rather than testing a textbook-specific claim about it.

### 2.2 Multiple Choice — Source/Textbook-Dependent Knowledge

Use Multiple Choice for information whose answer depends on how the reference describes, categorizes, lists, prioritizes, explains, or interprets the material.

This includes:

- benefits
- advantages
- disadvantages
- challenges
- limitations
- risks
- characteristics
- features
- purposes
- objectives
- reasons
- use cases
- examples
- applications
- best practices
- success factors
- pitfalls
- recommendations
- effects
- outcomes
- comparisons
- classifications created by the source
- numbered or ordered list items
- author's explanations
- statements such as "according to the text"
- "which statement is true/false"
- "which belongs to the list"
- "which is NOT a benefit/challenge/etc."
- questions where different textbooks could reasonably phrase or categorize the answer differently

Example:

"Which is a benefit of hub-and-spoke integration?"

→ Multiple Choice.

Even though "hub-and-spoke integration" is a valid Identification term, this question tests information ABOUT the term, not the identity of the term.

### 2.3 The "Term vs. Information About the Term" Rule

When a source contains both a named term and information about that term, separate them.

Identification:
"What integration pattern uses a central hub to route and translate communication between systems?"
Answer: `Hub-and-spoke integration`

Multiple Choice:
"Which is a benefit of hub-and-spoke integration according to the reference?"
Answer: the source-supported benefit.

Do NOT turn every sentence containing a technical term into an Identification question.

### 2.4 Universality Test

Before assigning Identification, ask:

> "Would the answer still be the same if another textbook explained this concept using different wording, different examples, or a different organization of the material?"

- YES → Identification may be appropriate.
- NO → Multiple Choice.
- UNCERTAIN → Multiple Choice.

This is a classification safeguard. When in doubt, do not force source-dependent information into Identification.

### 2.5 Definition-Only Requirement

Identification questions must be based on:

- an explicit definition;
- a formal description;
- a directly stated function;
- a directly stated structure;
- or unmistakable defining characteristics explicitly supported by the source.

Do not infer a term from a general statement merely because the model recognizes the concept from outside knowledge.

If the source does not explicitly establish the clue-to-term relationship, do not create an Identification question.

### 2.6 No Textbook-Invented Identification Terms

Do not use Identification for labels that exist only because the source created a category or list, such as:

- "Legacy challenge"
- "Success factor"
- "Pitfall"
- "Benefit #3"
- "Step 4"
- "Key advantage"
- "Major limitation"

unless the label itself is an independently recognized named concept AND the source explicitly defines it.

### 2.7 Question-Type Priority Rule

When information could theoretically produce either type:

1. Prefer Identification only if it tests the identity/definition of a stable named concept.
2. Prefer Multiple Choice for information ABOUT that concept.
3. If uncertainty remains, use Multiple Choice.

## 3. Cross-Mode Duplication Control

Do not test the exact same fact using nearly identical clues in both modes.

Bad:

Identification:
"An integration architecture providing centralized routing, transformation, protocol mediation, and orchestration."
Answer: `ESB`

Multiple Choice:
"Which architecture provides centralized routing, transformation, protocol mediation, and orchestration?"
Answer: `ESB`

These test essentially the same knowledge.

Better:

Identification:
"An integration architecture providing centralized routing, transformation, protocol mediation, and orchestration."
Answer: `ESB`

Multiple Choice:
"Which is a benefit of using an ESB according to the reference?"
Options contain source-supported benefits and plausible distractors.

The MC question should test a different aspect of the source.

## 4. Source Fidelity

- Never add a question solely because the model knows the answer from general knowledge.
- Never "correct" the source silently.
- If the source uses a particular terminology, preserve it.
- If the source gives multiple names for the same concept, record the source-supported variants.
- If the source is ambiguous, do not invent certainty.
- If two sources conflict, preserve the distinction and do not reconcile them without instruction.
- If a requested question count cannot be met using source-supported material without inventing content, report the shortfall rather than fabricating questions.

## 5. Term Families and Completeness

Before generating questions, enumerate every relevant family per lesson.

Examples:

- phases
- models
- layers
- principles
- components
- protocols
- patterns
- artifacts
- roles
- views
- benefits
- challenges
- limitations
- characteristics
- best practices
- steps
- classifications

For each family:

1. Identify every explicitly listed member.
2. Mark whether each member is suitable for Identification or Multiple Choice.
3. Ensure every member receives appropriate coverage.
4. When a family contains stable named terms, test the terms through Identification where appropriate.
5. Test source-dependent information about those terms through Multiple Choice.
6. If the same list reappears across lessons, avoid unnecessary duplication while maintaining coverage.

### Family Fitness Example

If the source lists ESB functions:

- routing
- transformation
- protocol mediation
- orchestration
- monitoring and logging
- security enforcement

Do not automatically turn every function into Identification.

First determine whether each item is:
- an independently recognized named concept explicitly defined by the source → possible Identification;
- merely a function/item in the source's list → normally Multiple Choice.

## 6. Quantity

- Default: at least **30 questions per lesson in each mode**, unless the user specifies another quantity.
- Do not manufacture questions merely to hit the number.
- If a mode cannot reach the requested count using source-supported material, report the exact count and why.
- Avoid duplicate questions.
- Avoid near-duplicate clues.
- When lessons overlap, rephrase only when the source provides genuinely distinct information.

## 7. Question Construction

### Identification

Each Identification question must:

- ask for one term;
- use a definition or defining description;
- avoid giving away the answer through wording;
- avoid asking for a benefit, challenge, advantage, purpose, example, or other source-dependent detail;
- accept only source-supported terms and variants.

Good:

> "What integration pattern uses a central hub to route and translate communication between systems?"

Bad:

> "What is one advantage of hub-and-spoke integration?"

The second is Multiple Choice.

### Multiple Choice

Multiple Choice may test:

- benefits
- advantages/disadvantages
- challenges
- characteristics
- purposes
- examples
- use cases
- comparisons
- list membership
- source-specific claims
- conceptual "why/how/which statement" questions

For conceptual questions:
- write 1 correct answer supported by the source;
- write 3 plausible but incorrect options;
- keep all options comparable in type and scope;
- avoid trick wording unless the user explicitly requests difficult/tricky questions.

## 8. Distractor Construction

For label-style source-dependent questions:

- Pool distractors from other source-supported items in the same family where possible.
- Prefer distractors with the same semantic category.
- Example: benefit → other benefits; challenge → other challenges.
- Do not use an unrelated term merely because it is technically plausible.
- Exclude anything that matches an accepted answer.
- Use exactly 3 distractors unless the user specifies another number.

For conceptual questions:

- Distractors should be plausible but clearly unsupported or contradicted by the source.
- Do not introduce outside facts merely to create distractors.
- Avoid two options that could both reasonably be considered correct from the source.

## 9. Answer Data Format

Each Identification question:

```js
[clue, "main answer|alt1|alt2"]
```

Each Multiple Choice question:

```js
[clue, "correct", ["wrong1", "wrong2", "wrong3"]]
```

Rules:

- Put the main answer first.
- Add only source-supported answer variants.
- Accept acronym/full-name variants when supported.
- Accept spelling and punctuation variants where appropriate.
- Add "Phase X" forms only when supported by the source.
- Do not invent alternate terminology from general knowledge.
- Clues are descriptions, not the answer.
- Include layer/type context when helpful and source-supported.
- Clues may contain `<` or `>`; always HTML-escape clue, answer, and user input before inserting into `innerHTML`.

## 10. Identification Answer Matching

Normalize both sides:

- lowercase;
- convert `&` to `and`;
- remove non-alphanumeric characters;
- normalize hyphens/punctuation;
- drop articles (`the`, `a`, `an`);
- singularize simple plural forms;
- join without spaces.

Accept if equal to any approved alternate.

```js
function sing(w){
  if(w.length>3){
    if(/ies$/.test(w)) return w.slice(0,-3)+"y";
    if(/(ss|us|is)$/.test(w)) return w;
    if(/(ches|shes|xes|ses)$/.test(w)) return w.slice(0,-2);
    if(/s$/.test(w)) return w.slice(0,-1);
  }
  return w;
}

function norm(s){
  return s.toLowerCase()
    .replace(/&/g," and ")
    .replace(/[^a-z0-9 ]+/g," ")
    .split(/\s+/)
    .filter(w=>w && !["the","a","an"].includes(w))
    .map(sing)
    .join("");
}

const esc=s=>String(s)
  .replace(/&/g,"&amp;")
  .replace(/</g,"&lt;")
  .replace(/>/g,"&gt;");
```

Do not accept an answer merely because it is conceptually related. It must match the source-supported answer or an explicitly approved variant.

## 11. Required Internal Generation Workflow

Before producing the final quiz:

### Step 1 — Parse
Read the complete source.

### Step 2 — Extract
Build:
- named terms;
- explicit definitions;
- term families;
- source-dependent lists;
- benefits/advantages;
- challenges/limitations;
- characteristics;
- examples;
- comparisons;
- other source-specific claims.

### Step 3 — Classify
For every candidate item, assign:

- `IDENTIFICATION`
- `MULTIPLE_CHOICE`
- `EXCLUDE`

Apply the Universality Test.

### Step 4 — Validate
For every Identification candidate, verify:
- it is a stable named term;
- the source explicitly defines or unmistakably identifies it;
- the clue asks what it is/called;
- it does not depend on a source-specific list or claim.

For every MC candidate, verify:
- the correct answer is explicitly supported by the source;
- distractors do not accidentally become correct;
- the question tests information different from any duplicate Identification item.

### Step 5 — Coverage Check
Check every source family and every requested lesson.

### Step 6 — Deduplication
Remove:
- exact duplicates;
- near-duplicates;
- same fact tested in both modes without a meaningful change in knowledge being tested.

### Step 7 — Generate
Only after classification and validation should the HTML question bank be produced.

## 12. Required Behavior of the HTML

- Single self-contained HTML file.
- No external dependencies.
- No browser storage.
- Lesson chips (`L2..Ln` or source-equivalent units).
- Opening a lesson shows:
  - Identification (n)
  - Multiple Choice (m)
- Disable a mode with 0 questions.
- One question per screen.
- Progress bar.
- Running score.
- Question counter.

### Immediate Feedback

On Submit:

- show green "Correct" or red "Incorrect";
- show the correct answer;
- turn the input/option green or red;
- always show ✓/✗ so color is not the only signal.

Enter behavior:

- Enter submits;
- Enter again goes next;
- do not move focus to Next automatically;
- call `preventDefault()` on Enter in the input;
- ignore `e.repeat`.

### Multiple Choice

- Radio-style option buttons.
- Submit enabled after a selection.
- Correct option turns green.
- Wrong selected option turns red.

### Shuffling

- Shuffle toggle, default ON.
- Apply shuffle when a run begins.
- Do not alter the stored original question indexes.

### Lesson Lock

- Once a run starts, disable other lesson chips until the run finishes.
- Reset lesson must confirm first.

### Results

Show:

- score;
- percentage;
- per-question review;
- clue;
- user's answer;
- correct answer.

Include:

- **Redemption Arc** button;
- shuffled retake containing only missed questions;
- repeatable until no questions are missed;
- Back to modes.

Track each log entry's original question index so Redemption Arc can rebuild the order correctly.

## 13. Look — Google Forms Style

- Soft tinted page background.
- White cards.
- 8px radius.
- Thick colored top border on the header card.
- Question card.
- "Identify the term *" for Identification.
- "Choose the best answer *" for Multiple Choice.
- Underline-style text input.
- Filled primary buttons.
- Chip-style lesson tabs.
- Default palette green:
  - `--ac:#1e7e4f`
  - `bg:#e3f2e8`
- Use the user's chosen palette if provided.
- Provide dark-mode tokens using `prefers-color-scheme`.
- Correct/incorrect colors remain green/red and always include ✓/✗.
- Mobile:
  - `viewport-fit=cover`;
  - safe-area padding;
  - wrapping chips;
  - full-width options.

## 14. Q&A PDF

If requested, build a Q&A PDF using ReportLab.

For each lesson:

- one table;
- columns:
  - #
  - Question
  - Answer
- main answer in bold;
- Identification alternates under the main answer in smaller grey text;
- Multiple Choice rows include the wrong options;
- mark the mode;
- repeat the header row;
- include page footer.

Do not include material that was excluded by the fitness test.

## 15. Delivery

1. Write the HTML to `/mnt/user-data/outputs/`.
2. Publish it as an artifact.
3. Republish the same URL on every revision.
4. Also provide the `.html` file for offline use.
5. If requested, provide the Q&A PDF.
6. Syntax-check the generated JavaScript with `new Function(code)` in Node before publishing.

Final response must report:

- counts per lesson per mode;
- what was classified as Identification and why;
- what was moved to Multiple Choice and why;
- anything excluded;
- any source-supported material not covered;
- artifact/download link.

## 16. Common Mistakes to Avoid

- Treating every named phrase as Identification.
- Asking textbook-dependent benefits, advantages, challenges, or characteristics as typed Identification.
- Asking a definition in both Identification and Multiple Choice.
- Using a textbook-created list label as an Identification term.
- Inventing definitions from general knowledge.
- Adding outside information not present in the source.
- Missing members of an explicitly listed family.
- Using unrelated distractors.
- Creating distractors that are also supported by the source.
- Accepting merely related terms as Identification answers.
- Missing alternate spellings/acronyms explicitly supported by the source.
- Raw `<tag>` text in clues breaking `innerHTML`.
- Feedback flashing away because focus moved to Next on Enter.
- Shipping fewer questions than requested without explaining the source-supported limitation.
- Adding questions about artifacts or terms the source never defines.
- Manufacturing questions solely to satisfy a numeric quota.

## 17. Core Principle

The quiz must test **two different kinds of mastery**:

### Identification
> **"What is this called?"**

Use stable, explicitly defined, universally identifiable terms.

### Multiple Choice
> **"What does the reference say about it?"**

Use source-dependent information such as benefits, advantages, disadvantages,
characteristics, purposes, examples, challenges, comparisons, classifications,
and list items.

When uncertain:

> **Prefer Multiple Choice over forcing source-dependent information into Identification.**
