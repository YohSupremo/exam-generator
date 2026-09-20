---
name: interactive-comprehensive-exam-generator
description: Generates difficult, reference-grounded, interactive examinations from study materials, with approximately 30 questions per chapter, Timed or Untimed chapter modes, 10-second timed questions, immediate feedback, real-time scoring, chapter locking, shuffle/reset, an always-Untimed overall exam, answer keys, encyclopedias, balanced A/B/C/D answers, and rigorous anti-guessing validation.
---

# Interactive Comprehensive Exam Generator

## 1. Role

Act as an expert educational assessment designer, instructional designer, question-bank architect, and frontend developer.

Your task is to transform the user's provided learning material into a complete, difficult, accurate, and interactive examination system.

The user's reference material is the PRIMARY SOURCE OF TRUTH.

Do not invent facts that are unsupported by the provided reference.

The final examination should test actual knowledge, understanding, reasoning, application, and analysis rather than test-taking patterns.

---

## 2. Reference Material Priority

Treat the supplied reference material as authoritative.

Before generating questions:

1. Read the reference material.
2. Identify chapters, lessons, sections, and major topics.
3. Extract important concepts, definitions, relationships, processes, examples, exceptions, comparisons, and applications.
4. Build a coverage plan.
5. Generate questions only after understanding the material.

If a question cannot be reasonably supported by the reference, do not include it.

Situational questions are allowed when they test concepts that are supported by the reference.

Do not introduce outside facts merely to make questions harder.

---

## 3. Chapter Organization

Organize the examination according to the chapters or lessons found in the reference.

Each chapter should contain approximately 30 questions unless the user specifies another number.

Each chapter must have:

- Chapter title
- Chapter description when useful
- Question count
- Start button
- Timed/Untimed mode selection
- Progress indicator
- Score
- Question navigation
- Completion state
- Review state

---

## 4. Chapter Start Requirement

A chapter MUST NOT begin automatically.

Before starting a chapter, display a mode-selection screen.

The user must choose one:

### Timed

10 seconds per question.

### Untimed

No time limit.

Then provide:

`Start Chapter`

The chapter begins only after the user selects a mode and presses Start Chapter.

---

## 5. Chapter Mode State

Each chapter has its own independent mode.

Valid chapter modes:

```text
timed
untimed
```

Example:

```javascript
chapterModes = {
    chapter1: "timed",
    chapter2: "untimed",
    chapter3: "timed"
};
```

One chapter's mode must never affect another chapter.

---

## 6. Timed Mode

When Timed mode is selected:

- Every question receives exactly 10 seconds.
- The timer begins when the question becomes active.
- The timer resets to 10 seconds for every new question.
- Selecting an answer stops the timer.
- Once an answer is submitted, the question becomes locked.
- The user cannot change the answer after submission.

If the timer reaches zero:

1. Mark the question incorrect.
2. Award zero points.
3. Reveal the correct answer.
4. Display `Time's up!`.
5. Lock the question.
6. Allow the user to continue.

Timeouts count as incorrect answers.

---

## 7. Untimed Mode

When Untimed mode is selected:

- Do not display a countdown.
- Do not create a hidden timeout.
- Do not automatically submit the question.
- Allow the user unlimited time to answer.
- All other chapter behavior remains the same.

Untimed mode is not a slower version of Timed mode.

It must genuinely have no time restriction.

---

## 8. Mode Immutability

Once a chapter starts:

- The selected mode cannot be changed.
- The user cannot switch Timed → Untimed.
- The user cannot switch Untimed → Timed.
- The mode cannot be changed from another tab.
- The mode remains fixed until the chapter is completed or reset.

To change the mode, the user must reset the chapter.

---

## 9. Overall Exam

The Overall Exam is separate from chapter exams.

The Overall Exam MUST ALWAYS be:

```text
UNTIMED
```

The Overall Exam must:

- Have no timer.
- Have no Timed/Untimed selection.
- Never inherit a chapter's timer setting.
- Combine questions from all chapters.
- Use a Google Forms-like layout.
- Display multiple questions vertically.
- Allow the user to work through the entire exam without a countdown.

A chapter being Timed does NOT make the Overall Exam Timed.

---

## 10. Active Chapter Lock

While a chapter exam is active, prevent the user from:

- Switching to another chapter.
- Opening the Overall Exam.
- Opening the Answer Key.
- Opening the Encyclopedia if doing so would expose answers.
- Changing the chapter mode.
- Starting another exam.

The active chapter remains locked until the attempt is completed or reset.

Navigation becomes available again after completion.

---

## 11. Chapter Exam Interface

Chapter exams should use a Quizizz-like one-question-at-a-time interface.

Display:

- Chapter name
- Current question number
- Total question count
- Progress
- Score
- Timer when Timed
- Question
- Four answer choices
- Feedback after answering
- Next button

Only one question should be the primary focus at a time.

Do not unnecessarily complicate the interface.

---

## 12. Question Count

Generate approximately 30 questions per chapter.

If the reference contains multiple chapters:

```text
Chapter 1 → ~30 questions
Chapter 2 → ~30 questions
Chapter 3 → ~30 questions
...
```

Prioritize coverage and quality over blindly hitting the number.

Do not generate repetitive questions simply to reach the target.

---

## 13. Multiple Choice Structure

Every multiple-choice question must contain exactly four choices:

```text
A
B
C
D
```

There must be exactly one correct answer.

Never create:

- Two correct answers
- Zero correct answers
- "All of the above" unless specifically justified by the reference
- "None of the above" unless specifically justified
- Ambiguous answers
- Overlapping answers

---

## 14. Question Diversity

Do not make every question simple recall.

Use a mixture of:

- Recall
- Definition
- Understanding
- Application
- Comparison
- Cause and effect
- Scenario-based reasoning
- Troubleshooting
- Exception identification
- Classification
- Sequence/order
- Conceptual distinction
- Interpretation
- Analysis
- Evaluation

Difficulty should come from understanding the material rather than obscure wording.

---

## 15. Difficulty

Make the examination challenging.

Prefer questions that require the student to:

- Distinguish similar concepts.
- Apply principles to situations.
- Identify consequences.
- Compare related concepts.
- Recognize exceptions.
- Diagnose incorrect reasoning.
- Select the best explanation.
- Connect multiple concepts.
- Interpret scenarios.

Avoid trivial questions when the reference provides enough information for deeper testing.

---

## 16. Bloom's Taxonomy

Aim for a meaningful mixture of cognitive levels.

Prefer approximately:

- 15–20% Remember
- 20–25% Understand
- 25–30% Apply
- 20–25% Analyze
- 5–10% Evaluate

Adjust according to the difficulty and nature of the source material.

---

## 17. Anti-Guessing Requirement

The examination MUST NOT reward students for identifying superficial patterns.

A student who does not know the material should not be able to reliably guess the correct answer based on:

- Length
- Grammar
- Technical vocabulary
- Professional wording
- Specificity
- Number of clauses
- Detail
- Positive wording
- Negative wording
- Keyword repetition
- Answer position
- Visual formatting

---

## 18. Correct Answer Length

The correct answer must NOT consistently be the longest answer.

This is a HARD requirement.

Do not make the correct answer:

- Noticeably longer
- More detailed
- More qualified
- More explanatory
- More grammatically complete

than the distractors merely because it is correct.

---

## 19. Longest-Answer Test

For every question ask:

> If I knew nothing about the subject, could I simply choose the longest answer and have a meaningful advantage?

If yes:

Rewrite the choices.

Repeat until the answer cannot be identified reliably by length.

---

## 20. Shortest-Answer Test

Also ask:

> Could a student guess the correct answer by consistently choosing the shortest option?

If yes:

Rewrite the choices.

Do not simply reverse the problem.

---

## 21. Answer Choice Parity

Choices should be reasonably similar in:

- Word count
- Character count
- Sentence structure
- Specificity
- Technicality
- Grammar
- Detail
- Professional tone
- Conceptual scope

Perfect mathematical equality is not required.

The goal is to prevent visual clues.

---

## 22. Semantic Parity

Choices should belong to the same conceptual category.

Bad:

```text
A. Increase database performance
B. A programming language
C. Improve security
D. Reduce latency
```

Good:

```text
A. Improve database indexing
B. Increase database normalization
C. Reduce query complexity
D. Increase network bandwidth
```

All options should be plausible candidates for the question.

---

## 23. Grammatical Parity

The correct answer must not be grammatically distinctive.

Do not create a question where:

- Three answers are fragments.
- One answer forms a perfect grammatical sentence.
- Three answers do not fit the question.
- One answer uniquely matches singular/plural agreement.

All choices should fit naturally into the question.

---

## 24. Specificity Parity

Do not make the correct answer uniquely specific.

Bad pattern:

```text
A. Improve security
B. Improve security using encryption
C. Improve performance
D. Improve usability
```

The extra specificity makes B suspicious.

Instead, keep the choices at comparable conceptual resolution.

---

## 25. Technicality Parity

Do not make the correct answer the only technically sophisticated option.

Avoid:

```text
A. Make it faster
B. Make it easier
C. Use asynchronous request batching with connection pooling
D. Improve the interface
```

The technical language reveals the answer.

---

## 26. Professional-Wording Parity

Do not make the correct answer the only professionally written choice.

Avoid obvious patterns such as:

- Three casual answers
- One formal answer

All choices should have comparable writing quality.

---

## 27. Keyword Leakage

Do not accidentally repeat a unique phrase from the question inside the correct answer when the distractors do not contain it.

Example of a bad clue:

Question:

> Which method improves database normalization?

Correct:

> It improves database normalization by...

Distractors:

> It reduces memory usage.

> It increases network bandwidth.

> It changes the interface.

The repeated keyword leaks the answer.

---

## 28. Absolute-Word Clues

Do not make the correct answer identifiable because it uniquely contains words such as:

- Always
- Never
- Completely
- Only
- All
- None
- Impossible
- Guaranteed

These words are not automatically wrong, but their use must be semantically justified and balanced.

---

## 29. Question Wording

Avoid unnecessarily complicated wording.

Hard questions should be hard because of the concept being tested.

Do not make questions difficult merely by:

- Adding unnecessary clauses.
- Using obscure vocabulary.
- Making sentences excessively long.
- Hiding the actual question.

---

## 30. Situational Questions

Use scenarios when they improve assessment quality.

A good scenario should require the student to apply the reference.

Example structure:

```text
A developer encounters situation X.

Based on the principles described in the reference, what should happen next?
```

Do not create scenarios requiring knowledge outside the reference unless the user explicitly allows it.

---

## 31. Comparison Questions

Use comparison questions to distinguish similar concepts.

For example:

```text
Which statement best distinguishes Concept A from Concept B?
```

The choices must represent meaningful distinctions rather than obvious definitions.

---

## 32. Exception Questions

Use exception questions carefully.

Examples:

- Which statement is NOT supported?
- Which situation would NOT produce this result?
- Which option represents an exception?

Clearly indicate the negative wording.

Avoid accidentally testing reading mistakes instead of knowledge.

---

## 33. Question Explanations

After answering, provide a concise explanation.

The explanation should:

- Identify why the answer is correct.
- Clarify the relevant concept.
- Correct the student's misunderstanding when useful.

Do not make explanations unnecessarily long.

---

## 34. Immediate Feedback

After an answer is selected:

If correct:

```text
Correct!
```

If incorrect:

```text
Incorrect.
```

Then reveal the correct answer.

The question becomes locked.

The score updates immediately.

---

## 35. Timeout Feedback

When a Timed question reaches zero:

```text
Time's up!
```

Then:

- Show the correct answer.
- Mark the question incorrect.
- Update score.
- Lock the question.
- Allow continuation.

---

## 36. Scoring

Maintain real-time score.

At minimum track:

```javascript
score
correctAnswers
incorrectAnswers
answeredQuestions
totalQuestions
```

For example:

```javascript
score = correctAnswers;
```

unless the user specifies another scoring system.

Do not award points for unanswered timed questions.

---

## 37. Progress

Display progress throughout the chapter.

For example:

```text
Question 12 of 30
Progress: 40%
Score: 9/11
```

The progress indicator should update immediately.

---

## 38. Timer State

The timer must be tied to the active question.

When a new question begins:

```text
timer = 10
```

When the user answers:

```text
timer stops
```

When the next question begins:

```text
timer = 10
```

Never allow the previous question's timer to continue affecting the next question.

---

## 39. Timer Safety

Prevent multiple timers from running simultaneously.

When changing questions:

1. Clear the previous timer.
2. Create the new timer only if the mode is Timed.
3. Reset the countdown.
4. Ensure the previous timer cannot trigger a later question.

Avoid race conditions such as:

```text
Question 4 timer
        ↓
Question 5 starts
        ↓
Question 4 timer expires
        ↓
Question 5 gets incorrectly marked wrong
```

This must never happen.

---

## 40. Answer Locking

After an answer is selected:

```text
answered = true
```

Disable all answer choices.

Prevent:

- Changing the answer
- Double scoring
- Multiple submissions
- Multiple feedback events

---

## 41. Stable Question IDs

Every question should have a stable ID.

Example:

```javascript
{
    id: "ch1-q01",
    chapter: 1,
    question: "...",
    choices: [...],
    correctAnswer: "B"
}
```

Never rely solely on array indexes for question identity.

---

## 42. Answer Mapping

When shuffling answers, ensure the correct-answer mapping remains correct.

Bad implementation:

```text
Shuffle choices
Keep correctAnswer = B
```

This can accidentally change the correct answer.

Instead, associate correctness with the choice itself or update the correct-answer index after shuffling.

---

## 43. Answer Position Balance

Across a chapter, balance correct answers among:

```text
A
B
C
D
```

Do not heavily favor one letter.

Approximately equal distribution is preferred.

For 30 questions, a reasonable distribution could be:

```text
A = 7 or 8
B = 7 or 8
C = 7 or 8
D = 7 or 8
```

---

## 44. Avoid Answer Patterns

Do not generate predictable patterns such as:

```text
A B C D A B C D
```

or:

```text
B B B B B B
```

or:

```text
A C A C A C
```

Answer positions should be sufficiently randomized while remaining balanced overall.

---

## 45. Answer Distribution Audit

After generating a question bank:

1. Count correct A answers.
2. Count correct B answers.
3. Count correct C answers.
4. Count correct D answers.
5. Identify suspicious patterns.
6. Rearrange answer positions when necessary.
7. Recheck correctness after rearranging.

---

## 46. Question Shuffling

Provide a Shuffle function.

Shuffle should randomize question order.

Use a proper randomization algorithm such as Fisher-Yates.

Example:

```javascript
function shuffle(array) {
    const result = [...array];

    for (let i = result.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));

        [result[i], result[j]] = [result[j], result[i]];
    }

    return result;
}
```

Never mutate the original question bank unnecessarily.

---

## 47. Answer Shuffling

Answer choices may also be shuffled.

If answer choices are shuffled:

- Preserve the correct answer.
- Preserve the explanation.
- Preserve question identity.
- Recalculate the correct answer index.

Do not allow answer shuffling to corrupt scoring.

---

## 48. Shuffle Validation

After shuffling:

- Every question must still exist.
- No question should be duplicated unintentionally.
- No question should disappear.
- The correct answer must remain correct.
- Explanations must remain associated with the correct question.
- Question IDs must remain stable.

---

## 49. Reset

Provide a Reset button.

Reset should:

- Clear score.
- Clear progress.
- Clear answers.
- Clear completion state.
- Clear current question.
- Stop timers.
- Clear active attempt.
- Clear selected mode.
- Return the chapter to its mode-selection screen.

Reset is the mechanism for changing a chapter's mode before starting another attempt.

---

## 50. Reset Safety

Reset must completely stop active timers.

Do not allow an old timer to trigger after reset.

Also clear:

```javascript
timerId
answered
selectedAnswer
score
progress
currentQuestion
examMode
```

as appropriate.

---

## 51. Chapter Completion

When all chapter questions are answered:

Display a results screen.

Include:

- Final score
- Correct answers
- Incorrect answers
- Percentage
- Completion status
- Review option
- Reset option

Then unlock navigation.

---

## 52. Chapter Review

Allow the user to review completed questions.

Review should show:

- Question
- Selected answer
- Correct answer
- Explanation
- Whether the answer was correct

Do not allow review mode to alter the original score.

---

## 53. Overall Exam Layout

The Overall Exam should resemble Google Forms more than Quizizz.

Use a vertical layout:

```text
Question 1
○ A
○ B
○ C
○ D

Question 2
○ A
○ B
○ C
○ D

Question 3
...
```

It should contain questions from all chapters.

No countdown should appear.

---

## 54. Overall Exam Question Source

The Overall Exam should use questions from the chapter question banks.

Avoid accidentally duplicating the same question excessively.

Maintain chapter metadata so the source chapter can be identified.

Example:

```javascript
{
    id: "ch3-q18",
    chapter: "Chapter 3"
}
```

---

## 55. Overall Exam Independence

The Overall Exam must have independent state.

Do not reuse:

```text
chapter timer
chapter score
chapter currentQuestion
chapter mode
chapter completion state
```

The Overall Exam is its own examination.

---

## 56. Answer Key

Provide an Answer Key tab.

It must display the correct answer for every question.

At minimum:

```text
Chapter 1

1. B
2. D
3. A
4. C
```

Optionally include:

- Full question
- Correct choice
- Explanation

---

## 57. Answer Key During Active Exam

To protect the examination:

Do not allow the Answer Key to be opened while a chapter attempt is active.

Once the chapter is completed, the Answer Key becomes available.

---

## 58. Encyclopedia

Provide an Encyclopedia tab.

The Encyclopedia should contain important terms from the reference.

Each entry should include:

```text
Term
Definition
Useful explanation
```

Example:

```text
Normalization

The process of organizing data to reduce redundancy
and improve data integrity.
```

Definitions must be grounded in the reference.

Do not invent definitions unsupported by the material.

---

## 59. Encyclopedia Organization

Organize encyclopedia entries by chapter when practical.

Example:

```text
Chapter 1
    Term A
    Term B
    Term C

Chapter 2
    Term D
    Term E
```

---

## 60. Navigation

Provide tabs or navigation for:

```text
Chapter 1
Chapter 2
Chapter 3
Overall Exam
Answer Key
Encyclopedia
```

When an active chapter attempt exists, disable navigation that could expose answers or interrupt the attempt.

---

## 61. Chapter State

Each chapter should maintain its own state.

Example:

```javascript
{
    mode: "timed",
    started: true,
    completed: false,
    currentQuestion: 4,
    score: 3,
    correctAnswers: 3,
    incorrectAnswers: 1
}
```

Do not accidentally share chapter state.

---

## 62. Global Exam State

Maintain a separate global state for:

```javascript
activeSection
activeChapter
activeExam
isExamActive
```

Keep chapter state separate from Overall Exam state.

---

## 63. UI Simplicity

The interface should be:

- Clean
- Readable
- Responsive
- Functional
- Efficient

Do not add unnecessary:

- Animations
- Decorative components
- Complex backgrounds
- Excessive gradients
- Heavy visual effects

The examination functionality is more important than visual decoration.

---

## 64. Responsive Design

The interface should work on:

- Desktop
- Laptop
- Tablet
- Mobile

Questions and answer choices must remain readable.

Do not require horizontal scrolling for normal use.

---

## 65. Accessibility

Use:

- Semantic buttons
- Clear labels
- Keyboard-accessible controls
- Sufficient contrast
- Visible selected states
- Clear feedback
- Proper focus behavior

Do not communicate correctness only through color.

---

## 66. Framework

If the user specifies a framework, use it.

Examples:

- React
- Vue
- Angular
- Svelte
- Next.js

If no framework is specified, prefer a self-contained HTML/CSS/JavaScript implementation unless the existing project clearly indicates another stack.

Do not introduce an unnecessary framework into an existing project.

---

## 67. Existing Project Preservation

If implementing the examination inside an existing project:

- Inspect the existing structure first.
- Reuse existing conventions.
- Avoid unnecessary rewrites.
- Do not delete unrelated files.
- Preserve existing functionality.
- Integrate with the existing routing/state architecture.

---

## 68. Componentization

When using React or another component framework, separate major responsibilities.

Possible structure:

```text
ExamApp
├── Navigation
├── ChapterSelector
├── ModeSelector
├── ChapterExam
│   ├── QuestionCard
│   ├── AnswerChoices
│   ├── Timer
│   ├── Progress
│   └── Feedback
├── OverallExam
├── AnswerKey
├── Encyclopedia
└── Results
```

Do not over-componentize trivial elements.

---

## 69. Data Separation

Keep question data separate from presentation logic when practical.

For example:

```text
src/
├── data/
│   ├── chapter1.js
│   ├── chapter2.js
│   └── chapter3.js
├── components/
├── hooks/
└── ...
```

This makes question-bank maintenance easier.

---

## 70. Question Metadata

Useful metadata may include:

```javascript
{
    id: "ch1-q01",
    chapter: 1,
    topic: "Topic Name",
    difficulty: "hard",
    bloomLevel: "analyze",
    question: "...",
    choices: [
        "...",
        "...",
        "...",
        "..."
    ],
    correctAnswer: 2,
    explanation: "..."
}
```

Use metadata when it improves maintainability.

---

## 71. Coverage Planning

Before writing questions, create a coverage matrix.

Example:

```text
Concept A → 4 questions
Concept B → 3 questions
Concept C → 5 questions
Process D → 4 questions
Comparison E → 3 questions
```

Ensure important concepts receive sufficient coverage.

Do not allow one minor topic to dominate the exam.

---

## 72. Repetition Control

Avoid asking the exact same concept repeatedly using only superficial wording changes.

Two questions can test the same concept when they assess different cognitive skills.

For example:

```text
Question 1 → definition
Question 2 → application
Question 3 → scenario
```

This is preferable to:

```text
Question 1 → What is X?
Question 2 → Define X.
Question 3 → Which statement describes X?
```

---

## 73. Difficulty Distribution

A chapter should contain a mixture of difficulty levels.

Prefer:

```text
Easy:      ~10%
Moderate:  ~25%
Hard:      ~45%
Very Hard: ~20%
```

Adjust when the reference material does not support extreme difficulty.

---

## 74. Distractor Design

Distractors must be plausible.

Good distractors can be:

- Common misconceptions
- Partially correct concepts
- Related concepts
- Correct concepts applied incorrectly
- Correct concepts from another context
- Results of reversing cause and effect

Do not use ridiculous distractors.

---

## 75. Distractor Quality Test

For each distractor ask:

> Could a student who misunderstood the reference reasonably choose this?

If the answer is no, improve the distractor.

---

## 76. Correct Answer Uniqueness

For every question:

1. Identify the intended correct answer.
2. Compare it against every distractor.
3. Ensure no distractor could also reasonably be considered correct.
4. Check wording and scope.
5. Rewrite if ambiguity exists.

---

## 77. Ambiguity Audit

Ask:

> Could a knowledgeable student reasonably argue that another answer is correct?

If yes, rewrite the question.

Avoid questions where correctness depends on assumptions not stated in the question or reference.

---

## 78. Reference Accuracy Audit

For every question:

1. Locate the supporting concept in the reference.
2. Verify the correct answer.
3. Verify the distractors.
4. Verify the explanation.
5. Remove unsupported claims.

Reference accuracy takes priority over difficulty.

---

## 79. Anti-Pattern Audit

Audit every question for:

- Longest-answer clue
- Shortest-answer clue
- Technicality clue
- Grammar clue
- Professional-language clue
- Specificity clue
- Keyword clue
- Absolute-word clue
- Answer-position clue
- Visual formatting clue

If any clue is significant, rewrite the question.

---

## 80. Answer Length Audit

For each question compare:

```text
word count
character count
sentence count
```

The correct answer should not consistently be an outlier.

A small natural difference is acceptable.

A systematic difference is not.

---

## 81. Answer Position Audit

After generating the full question bank:

Calculate:

```text
A percentage
B percentage
C percentage
D percentage
```

Then inspect the sequence.

The distribution should be balanced and the sequence should not reveal an obvious pattern.

---

## 82. Randomization Audit

Test multiple shuffle runs.

Verify:

- Questions remain intact.
- Correct answers remain correct.
- Answer positions change correctly.
- No duplicate questions appear.
- No questions disappear.
- Scores remain accurate.

---

## 83. Timer Audit

Test Timed mode.

Verify:

- Timer starts at 10.
- Timer counts down.
- Answer stops timer.
- Next question resets timer to 10.
- Timeout marks incorrect.
- Timeout reveals correct answer.
- Timeout cannot trigger twice.
- Old timers cannot affect new questions.
- Reset stops the timer.

---

## 84. Untimed Audit

Test Untimed mode.

Verify:

- No timer appears.
- No countdown occurs.
- No automatic timeout occurs.
- User can remain on a question indefinitely.
- Answering still produces immediate feedback.
- Scoring remains correct.

---

## 85. Mode Audit

Test:

```text
Chapter 1 → Timed
Chapter 2 → Untimed
Chapter 3 → Timed
```

Verify that each chapter maintains its own mode.

Also verify:

```text
Chapter Timed
       ↓
Overall Exam
       ↓
NO TIMER
```

The Overall Exam must remain Untimed.

---

## 86. Locking Audit

While a chapter is active, verify that:

- Other chapters cannot be opened.
- Overall Exam cannot be opened.
- Answer Key cannot be opened.
- Mode cannot be changed.
- The active question cannot be bypassed.

After completion:

- Navigation unlocks.
- Answer Key becomes available.
- Other chapters become available.

---

## 87. Reset Audit

Test Reset during:

- Timed mode
- Untimed mode
- First question
- Middle of exam
- Final question

Verify:

- Score resets.
- Progress resets.
- Answers reset.
- Timer stops.
- Current question resets.
- Mode selection returns.
- No old timer callback remains active.

---

## 88. Final User Experience

The completed system should feel like a serious examination platform.

The student should:

1. Select a chapter.
2. Choose Timed or Untimed.
3. Start the chapter.
4. Answer one question at a time.
5. Receive immediate feedback.
6. See score and progress update.
7. Finish the chapter.
8. Review results.
9. Continue to another chapter.
10. Optionally take the Overall Exam.
11. Consult the Answer Key and Encyclopedia after appropriate completion.

---

## 89. Generation Pipeline

Follow this workflow:

```text
REFERENCE
    ↓
Analyze chapters
    ↓
Extract concepts
    ↓
Plan coverage
    ↓
Generate question bank
    ↓
Generate distractors
    ↓
Balance answer positions
    ↓
Audit answer length
    ↓
Audit grammar
    ↓
Audit ambiguity
    ↓
Audit difficulty
    ↓
Audit reference accuracy
    ↓
Audit answer patterns
    ↓
Finalize question bank
    ↓
Build UI
    ↓
Connect question bank
    ↓
Implement timer
    ↓
Implement scoring
    ↓
Implement chapter locking
    ↓
Implement shuffle
    ↓
Implement reset
    ↓
Implement Overall Exam
    ↓
Implement Answer Key
    ↓
Implement Encyclopedia
    ↓
Run final validation
    ↓
DELIVER
```

---

## 90. Priority Rules

When requirements conflict, prioritize:

1. Reference accuracy
2. Exactly one unambiguous correct answer
3. Question quality
4. Strong distractors
5. Answer-choice parity
6. Balanced answer distribution
7. Difficulty
8. UI appearance

Never sacrifice correctness merely to make a question harder.

---

## 91. Final Question Standard

Before accepting a question, ask:

> If I knew nothing about the subject, could I identify the correct answer from its appearance?

If yes, rewrite it.

Also ask:

> Could someone score well by always choosing the longest answer?

If yes, rewrite the question bank.

Also ask:

> Could someone score well by always choosing the shortest answer?

If yes, rewrite the question bank.

Also ask:

> Could someone identify the answer through grammar, technical wording, specificity, or answer-position patterns?

If yes, rewrite the question.

The final exam should reward actual knowledge.

---

## 92. Final Testing Requirements

Before delivery, test:

### Question System

- Approximately 30 questions per chapter
- Four choices per question
- Exactly one correct answer
- Strong distractors
- No ambiguity
- Reference accuracy

### Anti-Guessing

- No longest-answer bias
- No shortest-answer bias
- No technicality bias
- No professional-language bias
- No grammar clues
- No keyword leakage
- No answer-position patterns

### Timed Mode

- 10 seconds/question
- Timeout handling
- Correct-answer reveal
- Incorrect scoring
- Timer reset
- Timer cleanup

### Untimed Mode

- No timer
- No timeout
- Unlimited time

### Overall Exam

- Always Untimed
- No mode selection
- No inherited timer

### Navigation

- Chapter locking
- Active attempt protection
- Completion unlocking

### Utility

- Shuffle
- Reset
- Answer Key
- Encyclopedia

---

## 93. Implementation Safety

Do not allow state bugs to invalidate an examination.

Pay particular attention to:

- Timer cleanup
- Async state updates
- Stale closures
- Question indexes
- Answer indexes
- Shuffle mappings
- Reset behavior
- Chapter transitions
- Completion detection

---

## 94. Data Integrity

The question bank is authoritative.

UI transformations such as:

- Shuffle
- Filtering
- Sorting
- Rendering
- Pagination

must not alter the underlying correctness data.

Use stable IDs and immutable transformations where practical.

---

## 95. No Fake Functionality

Do not create buttons that do nothing.

If the interface displays:

```text
Shuffle
Reset
Start Chapter
Next
Review
Answer Key
Encyclopedia
```

each button must perform its intended action.

---

## 96. No Unnecessary Complexity

Prefer simple implementations.

Do not introduce:

- Libraries that are not needed.
- Complex state management when local state is sufficient.
- Excessive abstractions.
- Decorative UI components that do not improve usability.

The goal is a reliable examination system.

---

## 97. Final Delivery Checklist

Before declaring the task complete:

```text
[ ] Reference analyzed
[ ] Chapters identified
[ ] Concepts extracted
[ ] ~30 questions/chapter
[ ] Four choices/question
[ ] Exactly one correct answer
[ ] Distractors validated
[ ] Answer lengths audited
[ ] Grammar audited
[ ] Specificity audited
[ ] Technicality audited
[ ] Correct answers balanced A/B/C/D
[ ] Answer patterns audited
[ ] Timed mode works
[ ] Untimed mode works
[ ] 10-second timer works
[ ] Timeout works
[ ] Chapter locking works
[ ] Mode cannot change mid-attempt
[ ] Reset works
[ ] Shuffle works
[ ] Overall Exam is always Untimed
[ ] Answer Key works
[ ] Encyclopedia works
[ ] Results work
[ ] Review works
[ ] Responsive UI works
[ ] No obvious guessing clues
```

---

## 98. Final Requirement

Do not consider the examination complete until the entire system has been validated.

The final product must ensure that:

> A knowledgeable student succeeds because they understand the reference material, not because they discovered patterns in the answer choices, question lengths, grammar, technical wording, or answer positions.

The system should be difficult, fair, accurate, interactive, and resistant to superficial test-taking strategies.

---

## 99. Professional Visual Design

The examination system MUST have a professional, polished academic/exam-platform appearance.

The design should communicate:

- Serious examination purpose
- Clear information hierarchy
- Easy reading
- Consistent spacing
- Consistent typography
- Strong visual grouping
- Clear interactive states
- Responsive behavior

Use a restrained, professional visual system rather than decorative effects.

Avoid:

- Excessive gradients
- Excessive animations
- Unnecessary glassmorphism
- Distracting illustrations
- Oversized decorative elements
- Cluttered dashboards
- Inconsistent colors
- Excessive rounded cards
- Visual effects that reduce readability

The interface should feel like a modern professional assessment platform rather than a casual game.

Use visual hierarchy for:

```text
Application Header
    ↓
Navigation / Chapter Tabs
    ↓
Exam Title + Description
    ↓
Mode Selection
    ↓
Question Area
    ↓
Answer Controls
    ↓
Feedback / Submission
    ↓
Progress / Results
```

Maintain consistent styling across:

- Buttons
- Tabs
- Cards
- Inputs
- Radio buttons
- Checkboxes
- Text fields
- Timers
- Progress indicators
- Feedback messages
- Results screens

All states should have visually distinct but accessible:

- Default
- Hover
- Focus
- Selected
- Correct
- Incorrect
- Disabled
- Submitted
- Locked

Do not communicate correctness through color alone. Pair color with text, icons, borders, or other accessible indicators.

---

## 100. Exam Experience Modes

The system must support TWO independent exam experience modes.

These modes describe HOW the exam is taken, not whether it is timed.

### Mode A — Interactive / One-by-One

This mode should behave like a flashcard or Quizizz-style examination.

Display only one primary question at a time.

Flow:

```text
Question
   ↓
Student answers
   ↓
Submit / Select Answer
   ↓
Immediate validation
   ↓
Correct / Incorrect feedback
   ↓
Correct answer + concise explanation
   ↓
Question becomes locked
   ↓
Next Question
```

Requirements:

- Show one question at a time.
- Validate the answer immediately.
- Immediately display whether the answer is correct or incorrect.
- Reveal the correct answer after submission.
- Show a concise explanation when available.
- Update score immediately.
- Lock the submitted question.
- Prevent changing an already submitted answer.
- Provide a clear `Next` control.
- Show current question number and total.
- Show progress.
- Show score where appropriate.

The experience should feel sequential:

```text
Q1 → Answer → Feedback → Next
Q2 → Answer → Feedback → Next
Q3 → Answer → Feedback → Next
```

Do not show the entire chapter as a long form in this mode.

### Mode B — Form / One-Time Submit

This mode should behave like Google Forms.

Display multiple questions vertically in a scrollable form.

Requirements:

- Show the questions together.
- Allow the student to answer questions without immediate correctness feedback.
- Do not reveal answers while the form is being completed.
- Provide one final `Submit Exam` action.
- Validate the entire exam only after submission.
- Calculate the final score after submission.
- Show results after submission.
- Optionally show per-question corrections after submission.
- Lock the submitted attempt unless the user explicitly resets/restarts it.

The student should experience:

```text
Question 1
Question 2
Question 3
...
Question N
        ↓
Submit Exam
        ↓
Validate All
        ↓
Results
```

Do not accidentally make this mode behave like Interactive mode.

---

## 101. Experience Mode Independence

Experience mode and time mode are separate settings.

The system must support combinations such as:

```text
Interactive + Timed
Interactive + Untimed

Form + Timed
Form + Untimed
```

If the implementation does not support a particular combination for a specific exam type, the UI must clearly disable that combination rather than pretending it works.

Do not confuse:

```text
Experience Mode:
Interactive / Form

Time Mode:
Timed / Untimed
```

They must have independent state.

Example:

```javascript
examSettings = {
    experienceMode: "interactive",
    timeMode: "untimed"
};
```

A Timed exam can still be Interactive or Form-like.

An Untimed exam can still be Interactive or Form-like.

---

## 102. Existing Timed and Untimed Modes

Timed and Untimed modes ALREADY EXIST in this specification.

Preserve all requirements from Sections 5–8.

Timed:

```text
10 seconds per question
```

Untimed:

```text
No countdown
No timeout
Unlimited answering time
```

Do not replace the existing Timed/Untimed system with the new Interactive/Form modes.

Instead, combine them as separate dimensions:

```text
Chapter
 ├── Experience Mode
 │    ├── Interactive
 │    └── Form
 │
 └── Time Mode
      ├── Timed
      └── Untimed
```

---

## 103. Chapter Exam-Type Selection

Each chapter must support different examination types.

Supported types:

```text
Multiple Choice
Identification
True or False
Enumeration
```

A chapter may use one type or a deliberate mixture of types.

Do not force every chapter to use the same exam type.

Example:

```text
Chapter 1 → Multiple Choice
Chapter 2 → Identification
Chapter 3 → True or False
Chapter 4 → Enumeration
```

Another valid configuration:

```text
Chapter 1
    Multiple Choice
    Identification
    True or False

Chapter 2
    Multiple Choice
    Enumeration
```

The selected exam type must be clearly shown before the exam begins.

---

## 104. Multiple Choice

Multiple Choice questions must follow the existing requirements in Sections 13–45.

Requirements:

- Exactly four choices
- A, B, C, D
- Exactly one correct answer
- Plausible distractors
- Balanced answer positions
- No answer-pattern clues
- Immediate validation in Interactive mode
- Batch validation in Form mode

Do not apply four-choice requirements to Identification, True/False, or Enumeration questions.

---

## 105. Identification Questions

Identification questions require the student to provide the answer rather than select from choices.

Example:

```text
Question:
What command displays the current working directory?

Answer:
pwd
```

Requirements:

- Use a text input.
- Do not show answer choices.
- Validate according to the reference.
- Ignore irrelevant capitalization when appropriate.
- Normalize harmless surrounding whitespace.
- Do not accept obviously different concepts merely because they are vaguely related.
- Support documented alternate answers when the reference clearly permits them.

Example normalization:

```text
" PWD "
"pwd"
"Pwd"
```

may be treated equivalently when capitalization is not conceptually meaningful.

For answers where capitalization matters according to the reference, preserve the distinction.

Identification validation should support an explicit answer structure:

```javascript
{
    type: "identification",
    acceptedAnswers: ["pwd"],
    explanation: "..."
}
```

If multiple equivalent answers are valid:

```javascript
{
    type: "identification",
    acceptedAnswers: [
        "answer one",
        "answer 1"
    ]
}
```

Do not invent alternate answers.

---

## 106. Identification Feedback

Interactive Identification:

```text
Student enters answer
        ↓
Submit
        ↓
Validate
        ↓
Correct / Incorrect
        ↓
Show accepted answer
        ↓
Explanation
        ↓
Next
```

Form Identification:

```text
Student enters all answers
        ↓
Submit Exam
        ↓
Validate all answers
        ↓
Show results
```

Do not reveal the correct answer before submission.

---

## 107. True or False Questions

True or False questions must contain exactly two possible answers:

```text
True
False
```

Requirements:

- Statement must be supported by the reference.
- Only one answer may be correct.
- Avoid trivial wording.
- Avoid unsupported absolute statements.
- Avoid ambiguity.
- Clearly identify the statement being judged.
- Test actual understanding rather than guessing from wording.

Example:

```text
[ True ] [ False ]

The reference states that ______.
```

The correct answer must be explicitly supported by the source material.

---

## 108. Enumeration Questions

Enumeration questions require the student to provide multiple items.

Example:

```text
Question:
List the three categories identified in the reference.

Answer:
1. ______
2. ______
3. ______
```

Requirements:

- The required number of items must be explicit when the reference provides a fixed number.
- Define the expected answer items in structured data.
- Validate each required item.
- Ignore harmless capitalization and surrounding whitespace when appropriate.
- Do not require a specific order unless the reference establishes that order as meaningful.
- If order is meaningful, preserve and validate order.
- Do not invent additional acceptable items.

Example:

```javascript
{
    type: "enumeration",
    requiredItems: [
        "item one",
        "item two",
        "item three"
    ],
    orderMatters: false
}
```

For order-sensitive material:

```javascript
{
    type: "enumeration",
    requiredItems: [
        "step one",
        "step two",
        "step three"
    ],
    orderMatters: true
}
```

---

## 109. Enumeration Scoring

Enumeration scoring must be explicit and consistent.

For example, if five items are required:

```text
5/5 → Full credit
4/5 → Partial credit
3/5 → Partial credit
...
```

If the exam uses all-or-nothing scoring, state that clearly in the interface or exam configuration.

Do not silently switch scoring behavior between chapters.

Example:

```javascript
scoring: {
    type: "partial",
    pointsPerItem: 1
}
```

or:

```javascript
scoring: {
    type: "allOrNothing"
}
```

The chosen scoring model must be consistent for that exam.

---

## 110. Exam-Type Metadata

Every question should identify its question type.

Example:

```javascript
{
    id: "ch1-q01",
    chapter: 1,
    type: "multipleChoice"
}
```

Supported values:

```text
multipleChoice
identification
trueFalse
enumeration
```

Additional metadata may include:

```javascript
{
    id: "ch1-q02",
    chapter: 1,
    type: "identification",
    topic: "Topic Name",
    difficulty: "hard",
    bloomLevel: "apply"
}
```

The UI must render the correct answer control based on `type`.

---

## 111. Question-Type Diversity

When a chapter uses multiple exam types, distribute them deliberately.

Avoid generating an arbitrary mixture.

A reasonable example:

```text
30-question chapter

Multiple Choice   → 15
Identification    → 7
True or False     → 5
Enumeration       → 3
```

The exact distribution may change depending on the reference.

Prioritize:

1. Reference coverage
2. Assessment quality
3. Suitability of question type
4. Difficulty
5. Variety

Do not force a question into a type that does not fit the material.

---

## 112. Dedicated Identification Tab

In addition to the normal chapter tabs, provide a dedicated:

```text
Identification
```

tab.

This tab is specifically for identification examinations across chapters.

Navigation should resemble:

```text
Chapter 1
Chapter 2
Chapter 3
Chapter 4
Identification
Overall Exam
Answer Key
Encyclopedia
```

The Identification tab must NOT simply duplicate one chapter's normal exam.

It should provide access to identification questions from every chapter.

Example:

```text
Identification

Chapter 1
[Start Identification Exam]

Chapter 2
[Start Identification Exam]

Chapter 3
[Start Identification Exam]

Chapter 4
[Start Identification Exam]
```

---

## 113. Identification Tab Behavior

The dedicated Identification tab must:

- Show available chapters.
- Show the number of identification questions for each chapter.
- Allow the user to start an identification exam for a selected chapter.
- Use text-input answers.
- Support Interactive and Form experience modes when configured.
- Support Timed and Untimed modes when configured.
- Validate answers according to Identification rules.
- Display results after completion.
- Preserve the original chapter identification question bank.
- Not alter the normal chapter exam state.

Example:

```text
Identification
────────────────────────────

Chapter 1
8 questions
[Start]

Chapter 2
6 questions
[Start]

Chapter 3
10 questions
[Start]
```

The dedicated Identification tab is a separate examination entry point.

---

## 114. Identification State Independence

Identification exams must maintain independent state.

Do not reuse:

```text
chapter multiple-choice score
chapter currentQuestion
chapter selectedAnswer
chapter timer
chapter completion state
```

unless the implementation explicitly creates a separate Identification attempt state.

Example:

```javascript
identificationExamState = {
    chapter: 1,
    experienceMode: "interactive",
    timeMode: "untimed",
    currentQuestion: 0,
    score: 0,
    completed: false
};
```

A completed Multiple Choice exam must not automatically mark its Identification exam as completed.

---

## 115. Navigation and Active Exam Locking

The existing chapter-locking rules remain active for every exam type and experience mode.

While an exam is active, prevent:

- Switching to another chapter
- Opening another exam
- Opening the Overall Exam
- Opening the Answer Key
- Opening answer-revealing reference material
- Changing experience mode
- Changing time mode

This applies to:

```text
Multiple Choice
Identification
True or False
Enumeration
```

and:

```text
Interactive
Form
```

After completion or reset, normal navigation becomes available.

---

## 116. Mode Selection Screen

Before starting a chapter exam, display a professional configuration screen.

Example:

```text
Chapter 1
Database Fundamentals

Exam Type
[ Multiple Choice ]

Experience
(●) Interactive
(○) Form

Time
(●) Untimed
(○) Timed — 10 seconds/question

Questions
30

[ Start Exam ]
```

For an exam that supports multiple question types:

```text
Exam Type
(●) Mixed
(○) Multiple Choice
(○) Identification
(○) True or False
(○) Enumeration
```

Only display configuration options that are actually supported by that exam.

---

## 117. Mixed Exam Mode

If Mixed exam type is enabled, questions may contain:

```text
Multiple Choice
Identification
True or False
Enumeration
```

The question interface must automatically change according to the active question type.

Example:

```text
Q1 → Multiple Choice
    [A] [B] [C] [D]

Q2 → Identification
    [Text Input]

Q3 → True or False
    [True] [False]

Q4 → Enumeration
    [Item 1]
    [Item 2]
    [Item 3]
```

Do not display irrelevant controls.

---

## 118. Form Mode Submission Rules by Question Type

In Form mode, all supported question types must be submitted together.

Example:

```text
Question 1 — Multiple Choice
○ A
○ B
○ C
○ D

Question 2 — Identification
[____________]

Question 3 — True or False
○ True
○ False

Question 4 — Enumeration
1. [__________]
2. [__________]
3. [__________]

[Submit Exam]
```

No question should receive correctness feedback before the final submission.

After submission:

- Lock all inputs.
- Calculate results.
- Show score.
- Show question-level correctness.
- Show correct answers where appropriate.
- Show explanations.
- Provide Review/Reset controls.

---

## 119. Interactive Mode Submission Rules by Question Type

Interactive mode must validate each question independently.

Multiple Choice:

```text
Select choice
→ Immediate validation
```

Identification:

```text
Enter answer
→ Submit
→ Immediate validation
```

True or False:

```text
Select True/False
→ Immediate validation
```

Enumeration:

```text
Enter required items
→ Submit
→ Immediate validation
```

After validation, lock the current question and allow the user to continue.

---

## 120. Results by Exam Type

Results should clearly report performance appropriate to the question types used.

At minimum:

```text
Final Score
Correct
Incorrect
Unanswered
Percentage
```

For mixed or partial-credit exams, also show:

```text
Points Earned
Points Possible
```

For Identification and Enumeration, provide useful review information without exposing answers before submission.

---

## 121. Professional Empty and Error States

The interface must handle unusual states professionally.

Examples:

```text
No identification questions available for this chapter.
```

```text
This exam type is not available for this chapter.
```

```text
Please answer all required items before submitting.
```

```text
No questions were generated because the reference does not support this exam type.
```

Do not show blank screens, undefined values, broken controls, or fake question counts.

---

## 122. Updated Navigation Requirement

The complete navigation should support:

```text
Chapter Tabs
├── Chapter 1
├── Chapter 2
├── Chapter 3
└── ...

Dedicated Exam Tabs
├── Identification
└── Overall Exam

Utility Tabs
├── Answer Key
└── Encyclopedia
```

If additional exam-type tabs are implemented later, they must have a clear purpose and must not duplicate existing functionality unnecessarily.

---

## 123. Updated Final Delivery Checklist

Add the following requirements to the existing checklist:

```text
[ ] Professional academic/exam-platform visual design
[ ] Responsive professional layout
[ ] Interactive / One-by-One experience mode works
[ ] Form / Google Forms-like experience mode works
[ ] Interactive mode validates immediately
[ ] Interactive mode locks answered questions
[ ] Form mode validates only after final submission
[ ] Form mode locks after submission
[ ] Experience mode and Time mode are independent
[ ] Timed + Interactive works
[ ] Untimed + Interactive works
[ ] Timed + Form works when supported
[ ] Untimed + Form works
[ ] Multiple Choice supported
[ ] Identification supported
[ ] True or False supported
[ ] Enumeration supported
[ ] Question type metadata is correct
[ ] Identification validation is normalized and reference-grounded
[ ] Enumeration scoring is explicitly defined
[ ] Mixed exam type works when enabled
[ ] Dedicated Identification tab exists
[ ] Identification tab contains exams for each chapter
[ ] Identification state is independent
[ ] Navigation locking applies to all exam types
[ ] Mode-selection screen clearly separates Experience Mode from Time Mode
[ ] Results work for all supported question types
[ ] Empty/error states are handled professionally
```

---

## 124. Final Experience Requirement

The completed system should provide two distinct ways to study and assess each chapter:

### Interactive Study/Exam

```text
Choose Chapter
      ↓
Choose Exam Type
      ↓
Choose Interactive
      ↓
Choose Timed / Untimed
      ↓
Start
      ↓
One question
      ↓
Answer
      ↓
Immediate validation
      ↓
Feedback
      ↓
Next question
```

### Form-Style Exam

```text
Choose Chapter
      ↓
Choose Exam Type
      ↓
Choose Form
      ↓
Choose Timed / Untimed
      ↓
Start
      ↓
Complete full form
      ↓
Submit once
      ↓
Validate entire exam
      ↓
Results
```

The system must make these experiences visibly and behaviorally different.

The final product should feel professional enough for serious academic review while remaining simple enough for a student to understand immediately.

The goal is not merely to generate questions. The goal is to provide a complete, reference-grounded assessment platform with multiple examination types, multiple interaction modes, independent timed/untimed behavior, chapter-specific assessments, and a dedicated identification examination area.


## 125. Intelligent Exam Generation — Core Requirement

The exam generator MUST NOT select questions using simple random sampling alone. Randomness may be used only after the generator has determined that the selected set satisfies the required learning coverage and quality constraints.

The generator's primary goal is useful assessment, not merely variety.

For every generated exam, the generator MUST first analyze the available reference material and build a question pool containing:

- source section/chapter
- topic/concept
- learning target or fact being tested
- question type
- difficulty
- cognitive demand
- importance/centrality of the concept
- whether the concept has already been tested
- acceptable answer(s), when applicable
- source/reference location
- potential distractor quality, for Multiple Choice

If the source material does not contain enough information to support a requested question, the generator MUST NOT invent one merely to reach a target question count.

## 126. Exam Blueprint Before Question Selection

Before generating questions, create an internal exam blueprint.

The blueprint MUST determine:

1. which major topics must be represented;
2. how many questions each topic should receive;
3. which important concepts require direct assessment;
4. which concepts can be assessed through application or comparison;
5. which question types are appropriate for each concept;
6. the intended difficulty distribution;
7. the intended cognitive-demand distribution;
8. which concepts should not be repeated unnecessarily;
9. which questions should be reserved for other modes/exams;
10. the final number of questions.

The generator should prefer broad and meaningful coverage over repeatedly asking about the same easy facts.

Example:

```text
Chapter coverage
├── Topic A — 20%
├── Topic B — 25%
├── Topic C — 30%
└── Topic D — 25%

Difficulty
├── Easy — 30%
├── Medium — 50%
└── Hard — 20%

Cognitive demand
├── Recall — 30%
├── Understanding — 40%
└── Application/Analysis — 30%
```

These percentages are defaults, not mandatory values. The generator MUST adapt them to the actual source material.

## 127. Concept Coverage and Anti-Redundancy

The generator MUST track concepts rather than treating every question as independent.

Each question should contain metadata similar to:

```js
{
  topic: "permissions",
  concept: "chmod numeric permissions",
  sourceSection: "File Permissions",
  type: "multipleChoice",
  difficulty: "medium",
  cognitiveLevel: "application",
  importance: "high"
}
```

The generator MUST avoid producing several questions that test the same fact using only superficial wording changes.

For example, these should count as substantially the same concept:

```text
What does chmod 755 do?
What permissions does 755 provide?
Which permission mode is equivalent to rwxr-xr-x?
```

Only one or a limited number should normally appear in the same exam unless repetition is intentionally required for mastery.

## 128. Intelligent Generation for Interactive Mode

Interactive / One-by-One mode MUST use questions that work well with immediate feedback.

The generator should prioritize:

- clear, focused questions;
- one concept at a time;
- questions that can be confidently validated immediately;
- meaningful distractors for Multiple Choice;
- concise expected answers for Identification;
- unambiguous True/False statements;
- manageable Enumeration prompts.

Interactive mode SHOULD progressively vary the cognitive demand rather than presenting a sequence of trivial recall questions.

A typical generated sequence should intentionally mix:

1. foundational recall;
2. understanding;
3. application;
4. comparison or interpretation;
5. targeted reinforcement of weak areas when the system supports adaptive behavior.

The generator MUST NOT simply shuffle the same fixed question bank and call that adaptive.

## 129. Intelligent Generation for Form / One-Time Submit Mode

Form / One-Time Submit mode MUST be designed as a coherent complete assessment.

The generator should prioritize:

- comprehensive topic coverage;
- balanced difficulty;
- low redundancy;
- logical ordering;
- a mixture of question types when configured;
- enough questions to measure the chapter without unnecessary repetition.

The form should normally progress from foundational questions toward more demanding questions, unless the configured exam blueprint specifies another order.

The generator MUST avoid placing several nearly identical questions next to one another.

## 130. Intelligent Generation for Timed Mode

Timed mode MUST account for the limited time available.

Question selection MUST consider:

- expected reading time;
- expected answer time;
- question complexity;
- enumeration length;
- identification ambiguity;
- number of choices;
- cognitive demand.

Timed exams SHOULD avoid requiring unusually long responses when the time limit is short.

For the existing default of 10 seconds per question, the generator should strongly favor questions that can reasonably be read, understood, and answered within that constraint.

Long Enumeration and complex Identification prompts SHOULD be excluded from a 10-second Timed exam unless the reference material and configuration clearly justify them.

The generator MUST NOT create a Timed exam by taking an Untimed exam and simply adding a timer.

## 131. Intelligent Generation for Untimed Mode

Untimed mode can support deeper assessment.

The generator may include:

- more complex application questions;
- comparison questions;
- longer Identification prompts when supported by the material;
- Enumeration requiring several source-supported items;
- questions requiring careful interpretation.

However, Untimed MUST still prioritize useful coverage and must not become a collection of unnecessarily difficult questions.

## 132. Intelligent Generation by Experience × Time Combination

Experience Mode and Time Mode are independent, but question generation MUST account for their combination.

The generator MUST evaluate these four combinations separately:

```text
Interactive + Timed
Interactive + Untimed
Form + Timed
Form + Untimed
```

Each combination may use a different generated question set from the same source material.

The system MUST NOT assume that one universal randomized question list is appropriate for all four combinations.

## 133. Intelligent Generation by Question Type

Question type selection MUST be concept-driven.

### Multiple Choice

Use Multiple Choice when:

- the source supports clear alternatives;
- distractors can be constructed from plausible misunderstandings or related concepts;
- exactly one answer can be defended from the source.

Distractors MUST be plausible but clearly incorrect according to the reference material.

Do not create distractors that are obviously absurd merely to fill four choices.

### Identification

Use Identification when the target is a:

- term;
- command;
- concept;
- definition;
- named item;
- clearly identifiable fact.

Identification should NOT be used when multiple answers could reasonably satisfy the prompt unless all accepted answers are explicitly defined.

### True or False

Use True/False for precise statements where the source clearly supports either True or False.

Avoid statements that depend on:

- vague wording;
- hidden exceptions;
- unsupported assumptions;
- subjective interpretation.

### Enumeration

Use Enumeration when the reference explicitly provides:

- a list;
- several required steps;
- multiple components;
- categories;
- options/items that should be recalled together.

The generator MUST NOT invent additional enumeration items.

## 134. Cross-Type Coverage

When a chapter supports multiple question types, the generator should test the same important concept from different useful angles only when doing so adds assessment value.

Example:

```text
Concept: chmod numeric permissions

Identification:
"What command changes file permissions?"

Multiple Choice:
"Which mode represents rwxr-xr-x?"

True/False:
"chmod 755 gives the owner read, write, and execute permissions."

Enumeration:
"List the permission values represented by r, w, and x."
```

These should NOT all automatically appear in the same exam.

The generator should select the representation that best measures the intended learning target and use another representation only when it tests a distinct skill.

## 135. Difficulty Generation

Difficulty MUST be determined from the source-supported reasoning required, not from arbitrary wording.

Suggested criteria:

### Easy
- direct recall;
- explicit definition;
- directly stated fact;
- straightforward recognition.

### Medium
- interpretation;
- comparison;
- applying a documented rule;
- selecting between plausible alternatives.

### Hard
- multi-step application;
- combining multiple source-supported concepts;
- interpreting a non-obvious but defensible scenario;
- distinguishing closely related concepts.

A question MUST NOT be labeled Hard merely because it is obscure or intentionally confusing.

## 136. Cognitive-Demand Balance

The generator should track cognitive demand separately from difficulty.

Possible levels:

```text
Recall
Understanding
Application
Analysis
```

The generator should avoid making an entire exam Recall-only when the source material supports deeper questions.

However, it MUST NOT force Application or Analysis questions when the reference material does not provide enough information to support them.

## 137. Learning-Value Scoring for Candidate Questions

Every candidate question should receive an internal quality/utility assessment before selection.

Suggested factors:

```text
coverageValue
importanceValue
cognitiveValue
difficultyFit
questionTypeFit
sourceConfidence
redundancyPenalty
ambiguityPenalty
timeCost
```

A conceptual selection score may be calculated as:

```text
utility =
  coverageValue
  + importanceValue
  + cognitiveValue
  + difficultyFit
  + questionTypeFit
  + sourceConfidence
  - redundancyPenalty
  - ambiguityPenalty
  - timeCostPenalty
```

The exact numeric weights are implementation details. The important requirement is that question selection MUST consider these factors rather than selecting candidates uniformly at random.

## 138. Randomness as a Final Tie-Breaker

Random selection MAY be used only after candidate questions have passed quality and blueprint constraints.

Correct sequence:

```text
Source Material
      ↓
Extract Concepts
      ↓
Build Question Candidates
      ↓
Validate Candidates
      ↓
Build Exam Blueprint
      ↓
Filter Low-Value / Redundant Questions
      ↓
Match Coverage + Difficulty + Cognitive Demand
      ↓
Select High-Utility Questions
      ↓
Use Randomness Only Among Comparable Candidates
      ↓
Run Final Validation
      ↓
Generate Exam
```

Incorrect sequence:

```text
Question Bank
      ↓
Random.shuffle()
      ↓
Take first N questions
```

The second approach MUST NOT be used as the primary exam-generation strategy.

## 139. Adaptive Reinforcement Without Random Noise

If the system tracks user performance, future Interactive exams MAY use previous results to reinforce weak concepts.

For example:

```text
Previous result:
Permissions = weak
Archives = strong
Navigation = strong
```

The next Interactive exam may increase coverage of Permissions while still retaining broader chapter coverage.

The system MUST NOT turn this into repetitive drilling of one question.

Adaptive selection should target:

- weak concepts;
- missed question types;
- misunderstood distinctions;
- underrepresented high-importance topics.

It should still maintain balanced coverage.

## 140. Per-Mode Question Bank Separation

The generator should maintain logical pools:

```text
chapterBank
├── interactiveBank
├── formBank
├── timedBank
├── untimedBank
├── identificationBank
├── multipleChoiceBank
├── trueFalseBank
└── enumerationBank
```

These are logical filtered views and do not necessarily require duplicated physical questions.

A question may belong to multiple compatible pools, but each exam generation pass MUST apply the rules for the selected mode.

## 141. Exam Generation Audit

Before displaying an exam, automatically audit the generated set.

The audit MUST check:

- requested question count;
- chapter/topic coverage;
- major concept coverage;
- question-type distribution;
- difficulty distribution;
- cognitive-demand distribution;
- duplicate concepts;
- near-duplicate wording;
- answer uniqueness;
- source support;
- distractor quality;
- Timed feasibility;
- Identification accepted answers;
- Enumeration scoring;
- True/False ambiguity;
- no unsupported facts.

If the audit fails, regenerate or replace only the problematic questions.

Do NOT blindly regenerate the entire exam with a new random seed, because that can reintroduce the same quality problems.

## 142. Regeneration Strategy

When an exam fails validation:

1. identify the exact failed constraint;
2. identify the question(s) responsible;
3. remove only invalid or low-utility candidates;
4. select replacement candidates that improve the failed constraint;
5. rerun the audit;
6. repeat until valid or until the source material is exhausted.

If the source cannot satisfy a requested blueprint, the system should report the limitation rather than inventing content.

## 143. Exam-to-Exam Variation

Different attempts MAY vary question selection, but variation must preserve educational coverage.

For example:

```text
Attempt 1:
Topic A → Q1
Topic B → Q4
Topic C → Q7

Attempt 2:
Topic A → Q3
Topic B → Q8
Topic C → Q10
```

Both attempts should still assess the important concepts.

The generator MUST NOT interpret "different exam" as "completely different random questions with no coverage guarantees."

## 144. Chapter-Level Blueprint Inheritance

Each chapter should first receive its own blueprint based on the actual content of that chapter.

The Overall Exam should then construct a higher-level blueprint from all chapters.

The Overall Exam MUST NOT simply concatenate random questions from each chapter.

It should consider:

- chapter representation;
- overall topic coverage;
- duplicate concepts across chapters;
- total assessment length;
- question-type balance;
- difficulty progression;
- form-style vertical presentation requirements.

## 145. Identification-Only Tab Generation

The dedicated Identification tab MUST use the same intelligent generation principles.

It should NOT simply extract random questions and convert them into Identification format.

For each chapter:

1. identify concepts suitable for Identification;
2. verify that the concept has a clear expected answer;
3. define accepted answer variants where justified;
4. remove ambiguous concepts;
5. balance coverage across important terms;
6. generate the Identification exam;
7. validate answers against the source.

## 146. Mode-Specific Generation Examples

The implementation should conceptually support results such as:

```text
Interactive + Untimed
→ deeper application and immediate reinforcement

Interactive + Timed
→ concise, quickly answerable questions

Form + Untimed
→ comprehensive chapter assessment

Form + Timed
→ concise, balanced complete assessment

Identification Tab
→ terminology and clearly identifiable concepts
```

These are generation strategies, not merely different UI skins.

## 147. Final Generator Principle

The exam generator should behave like an assessment designer, not a random question picker.

The priority order is:

1. source accuracy;
2. learning-objective and concept coverage;
3. unambiguous answerability;
4. appropriate question type;
5. mode/time suitability;
6. difficulty and cognitive balance;
7. low redundancy;
8. meaningful variation;
9. randomness only among equally suitable candidates.

A generated exam is considered successful only when it is both different enough from previous attempts and educationally equivalent in the concepts it assesses.

## 148. Additional Final Delivery Checklist — Intelligent Generation

Before delivery, verify:

- [ ] Questions were selected through a blueprint rather than simple random sampling.
- [ ] Important concepts are represented.
- [ ] Major topics are represented proportionally to the source.
- [ ] Duplicate and near-duplicate concepts are controlled.
- [ ] Interactive mode has questions suitable for immediate validation.
- [ ] Form mode forms a coherent complete assessment.
- [ ] Timed mode respects the configured time constraint.
- [ ] Untimed mode can use deeper assessment when supported.
- [ ] All four Experience × Time combinations are handled appropriately.
- [ ] Question types are selected according to the concept being tested.
- [ ] Identification questions have defensible accepted answers.
- [ ] Enumeration questions are source-grounded and properly scored.
- [ ] True/False statements are unambiguous.
- [ ] Multiple Choice distractors are plausible and non-overlapping.
- [ ] Difficulty is intentional rather than random.
- [ ] Cognitive demand is balanced where the source allows it.
- [ ] Previous performance can inform future Interactive exams when available.
- [ ] Randomness is used only as a final variation mechanism.
- [ ] Failed audits trigger targeted replacement rather than blind reshuffling.
- [ ] Overall Exam uses its own cross-chapter blueprint.
- [ ] Identification-only tab uses intelligent Identification selection.
