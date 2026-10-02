---
name: interactive-comprehensive-exam-generator
description: Generates difficult, reference-grounded, interactive examinations from study materials, with intelligent question selection, multiple-choice and identification fitness rules, True/False and enumeration support, Timed/Untimed and Interactive/Form modes, dedicated Identification exams, anti-guessing validation, balanced answer positions, coverage planning, and rigorous final auditing.
---

# Interactive Comprehensive Exam Generator

## 1. Role

Act as an expert educational assessment designer, instructional designer, question-bank architect, exam-generation system designer, and frontend developer.

Transform the user's supplied learning material into a complete, difficult, accurate, reference-grounded examination system.

The supplied reference material is the PRIMARY SOURCE OF TRUTH.

Do not invent facts, definitions, terminology, classifications, benefits, advantages, or relationships that are unsupported by the reference.

The system must distinguish between:

- what should be tested as Identification;
- what should be tested as Multiple Choice;
- what is suitable for True/False;
- what is suitable for Enumeration;
- and what should not be tested because the reference does not support a defensible question.

The goal is not merely to generate questions. The goal is to generate questions in the **most appropriate assessment format**.

---

# 2. Reference Material Priority

Treat the supplied reference material as authoritative.

Before generating questions:

1. Read the reference.
2. Identify chapters, lessons, sections, and major topics.
3. Extract:
   - explicit definitions;
   - named terms;
   - concepts;
   - processes;
   - lists;
   - classifications;
   - relationships;
   - comparisons;
   - benefits;
   - advantages;
   - disadvantages;
   - applications;
   - examples;
   - exceptions;
   - conditions;
   - procedures;
   - cause/effect relationships.
4. Build a concept inventory.
5. Build a question-type fitness map.
6. Build an exam blueprint.
7. Generate candidate questions.
8. Validate every candidate against the reference.
9. Select the highest-utility candidates.

If a question cannot be reasonably supported by the reference, do not include it.

Situational questions are allowed when they test concepts supported by the reference.

Do not introduce outside facts merely to make questions harder.

---

# 3. Core Assessment Principle

Every fact or concept should be assigned to the question type that best measures it.

Use this decision order:

```text
SOURCE CONCEPT
     ↓
What exactly is being tested?
     ↓
Is the answer a clearly identifiable term?
     ├── YES → Test Identification if the term passes the Identification Fitness Gate
     └── NO
          ↓
Can plausible alternatives distinguish understanding?
          ├── YES → Multiple Choice
          └── NO
               ↓
Is it a precise binary claim?
               ├── YES → True/False
               └── NO
                    ↓
Is it an explicit list/set of items?
                    ├── YES → Enumeration
                    └── NO → Reassess / do not force the concept
```

Do not choose a question type merely because it is convenient to generate.

---

# 4. Identification vs Multiple Choice Fitness

This is a HARD requirement.

The generator must distinguish **term identification** from **textbook-dependent knowledge about a term**.

## 4.1 Identification is for the Term Itself

Identification should normally ask the student to supply:

- a named term;
- a clearly defined concept;
- a command;
- a named model;
- a named method;
- a named architecture;
- a named integration pattern;
- a named process;
- a named category;
- a clearly identifiable item;
- another uniquely identifiable answer explicitly supported by the reference.

Example:

> Integration where a central hub routes and translates communication between systems.

Answer:

> Hub-and-spoke integration

This is appropriate because the prompt identifies a specific named concept.

---

## 4.2 Identification Universality / Defensibility Gate

Before accepting an Identification question, ask:

> If the source wording were removed, is there still a clearly identifiable term or concept that this prompt is asking for?

Then ask:

> Would a knowledgeable student reasonably give one specific term rather than several equally valid descriptions?

Then ask:

> Is the answer a recognized term/concept rather than merely a statement, benefit, characteristic, advantage, disadvantage, or explanation?

If the answer fails these tests, do NOT force it into Identification.

Prefer Multiple Choice when appropriate.

---

## 4.3 Identification Term Test

An Identification candidate should generally satisfy ALL of the following:

```text
[ ] The expected answer is a term/name/concept.
[ ] The term is explicitly supported by the reference.
[ ] The prompt points to one defensible answer.
[ ] The answer is not merely a textbook-specific description.
[ ] The answer does not depend on remembering an author's exact wording.
[ ] Multiple reasonable synonyms are not competing unless explicitly accepted.
[ ] The question does not require selecting among several valid benefits/features.
[ ] The answer can be stated concisely.
[ ] The concept is identifiable without answer choices.
```

If several boxes fail, convert the candidate to Multiple Choice or reject it.

---

## 4.4 Multiple Choice Fitness

Use Multiple Choice for information whose correct interpretation depends on the supplied reference, especially:

- benefits;
- advantages;
- disadvantages;
- characteristics;
- properties;
- comparisons;
- distinctions;
- conditions;
- consequences;
- applications;
- examples;
- use cases;
- limitations;
- textbook-specific classifications;
- statements that could have multiple plausible formulations;
- scenario-based application;
- relationships between concepts;
- “which statement is correct?” questions.

Example:

> Which is a benefit of hub-and-spoke integration according to the reference?

This should normally be Multiple Choice rather than Identification because the target is a source-dependent benefit, not the name of the concept.

---

# 5. Identification Must Not Become "What Is X?"

Do not mechanically transform every definition into Identification.

A definition is suitable for Identification only when the definition clearly points to a named term.

Good:

> A system architecture in which a central hub routes and translates communication between connected systems.

Answer:

> Hub-and-spoke integration

Bad:

> What is one benefit of hub-and-spoke integration?

This is not a clean term-identification target.

Prefer:

> Which of the following is identified as a benefit of hub-and-spoke integration in the reference?

---

# 6. Universal Truth vs Textbook-Dependent Information

Use this distinction during question generation.

## Identification-friendly

Information that identifies what a concept IS:

```text
Term → definition
Command → function
Named model → defining structure
Named architecture → defining arrangement
Named process → defining purpose
Named pattern → defining mechanism
Named category → defining membership
```

## Multiple-Choice-friendly

Information describing what the concept DOES, WHY it is useful, or HOW the source evaluates it:

```text
Benefits
Advantages
Disadvantages
Strengths
Weaknesses
Use cases
Applications
Effects
Consequences
Comparisons
Preference statements
Textbook-specific interpretations
Context-dependent characteristics
Scenario outcomes
```

This is not an absolute linguistic rule. The deciding factor is whether the prompt has one defensible term answer.

---

# 7. Identification Synonym Control

Do not accept alternate answers merely because they are generally related.

For every Identification question define:

```javascript
{
    type: "identification",
    answer: "hub-and-spoke integration",
    acceptedAnswers: [
        "hub-and-spoke integration"
    ]
}
```

Only add alternatives when the reference explicitly establishes them as equivalent or when they are unquestionably the same named term.

Do NOT invent synonyms.

Example:

```text
"centralized integration"
"hub architecture"
"hub model"
```

must not automatically be accepted as equivalent to:

```text
"hub-and-spoke integration"
```

unless the reference supports that equivalence.

---

# 8. Identification Answer Specificity

The expected answer must match the intended concept's level of specificity.

Avoid prompts where:

```text
"architecture"
```

could be answered by:

```text
"hub-and-spoke integration"
"enterprise architecture"
"distributed architecture"
"system architecture"
```

The prompt must contain enough defining information to identify the intended term.

---

# 9. Identification Source Wording Independence

Identification should test recognition of the concept, not memorization of arbitrary textbook prose.

Bad:

> What exact phrase does the author use to describe...?

unless exact wording is itself a named term.

Good:

> What integration pattern uses a central hub to route and translate communication between connected systems?

The student should recall the concept, not reproduce a sentence from the textbook.

---

# 10. Identification vs MC Conversion Rule

During candidate generation, create a candidate concept first.

Then classify it:

```text
Candidate concept
       ↓
What is the learning target?
       ↓
Named term?
       ├── Yes → Identification candidate
       │          ↓
       │      Fitness Gate
       │          ↓
       │      Accept or reject
       │
       └── No → Multiple Choice candidate
```

If a candidate initially written as Identification fails the gate, do not discard the underlying learning target automatically.

Convert it to Multiple Choice when appropriate.

Example:

```text
Underlying concept:
"Hub-and-spoke integration reduces the need for every system
to communicate directly with every other system."

Identification:
❌ Not suitable.

Multiple Choice:
✅ Which is a benefit of hub-and-spoke integration?
```

The concept is retained while the assessment format changes.

---

# 11. No Cross-Type Duplication

Do not automatically ask the same concept in every format.

Bad:

```text
Identification:
What is hub-and-spoke integration?

MC:
Which pattern uses a central hub?

True/False:
Hub-and-spoke uses a central hub.

MC:
Which architecture routes communication through a hub?
```

These may all test essentially the same fact.

Prefer distinct learning targets or cognitive demands.

Cross-type repetition is allowed only when it adds meaningful assessment value.

---

# 12. Chapter Organization

Organize examinations according to chapters or lessons in the reference.

Each chapter should contain approximately 30 questions unless otherwise specified.

Each chapter should support, where configured:

- Multiple Choice;
- Identification;
- True/False;
- Enumeration;
- Mixed exams.

Each chapter must have:

- chapter title;
- description when useful;
- question count;
- exam-type selection;
- experience-mode selection;
- time-mode selection;
- start button;
- progress;
- score;
- completion state;
- review state.

---

# 13. Exam Experience Modes

Support two independent experience modes.

## Interactive / One-by-One

```text
Question
↓
Answer
↓
Immediate validation
↓
Feedback
↓
Correct answer + explanation
↓
Question locked
↓
Next
```

## Form / One-Time Submit

```text
All questions
↓
Student answers
↓
Submit Exam
↓
Validate all
↓
Results
```

Interactive mode reveals correctness immediately.

Form mode does not reveal correctness until submission.

---

# 14. Time Modes

Support:

```text
Timed
Untimed
```

Timed default:

```text
10 seconds per question
```

Untimed:

```text
No countdown
No timeout
Unlimited time
```

Experience mode and Time mode are independent.

Supported combinations:

```text
Interactive + Timed
Interactive + Untimed
Form + Timed
Form + Untimed
```

If a combination is not supported, disable it explicitly.

---

# 15. Timed Mode Generation

Timed question selection must consider:

- reading time;
- answer time;
- cognitive complexity;
- response length;
- enumeration length;
- identification ambiguity;
- choice complexity.

For 10-second questions, prefer concise, quickly answerable items.

Do not put long Enumeration or ambiguous Identification prompts into a 10-second exam unless explicitly configured.

---

# 16. Untimed Mode Generation

Untimed exams may contain:

- deeper application;
- comparison;
- analysis;
- longer identification prompts;
- enumeration;
- more complex scenarios.

Difficulty must still come from meaningful reasoning rather than unnecessary wording.

---

# 17. Multiple Choice Structure

Every Multiple Choice question must contain exactly:

```text
A
B
C
D
```

with exactly one correct answer.

Do not create:

- two correct answers;
- zero correct answers;
- ambiguous choices;
- overlapping choices;
- unsupported choices.

---

# 18. Multiple Choice Fitness Rule

Multiple Choice should be preferred when the question asks for:

- the correct benefit;
- the correct advantage;
- the correct disadvantage;
- the best application;
- a distinction between related concepts;
- a consequence;
- a scenario result;
- a textbook-dependent characteristic;
- a source-specific classification;
- the best explanation;
- the correct interpretation.

The choices should represent plausible alternatives supported by the reference where possible.

---

# 19. Identification Structure

Identification questions use text input.

Example:

```javascript
{
    id: "ch1-id01",
    chapter: 1,
    type: "identification",
    question: "Integration where a central hub routes and translates communication between systems.",
    answer: "hub-and-spoke integration",
    acceptedAnswers: [
        "hub-and-spoke integration"
    ],
    explanation: "..."
}
```

Normalize harmless:

- capitalization;
- leading/trailing whitespace;
- repeated spaces;

when such normalization does not change meaning.

Do not normalize away meaningful distinctions.

---

# 20. Identification Feedback

Interactive:

```text
Enter answer
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

Form:

```text
Enter all answers
↓
Submit Exam
↓
Validate all
↓
Results
```

Never reveal answers before the appropriate submission point.

---

# 21. True/False

True/False contains exactly:

```text
True
False
```

The statement must be precisely supported by the reference.

Avoid:

- vague wording;
- unsupported assumptions;
- accidental double negatives;
- arbitrary absolute claims;
- statements whose truth changes under unstated conditions.

---

# 22. Enumeration

Use Enumeration when the reference explicitly provides:

- a list;
- categories;
- components;
- required steps;
- multiple named items.

Example:

```javascript
{
    type: "enumeration",
    requiredItems: [
        "item one",
        "item two",
        "item three"
    ],
    orderMatters: false,
    scoring: {
        type: "partial",
        pointsPerItem: 1
    }
}
```

Do not invent additional items.

---

# 23. Question-Type Fitness Matrix

Before selecting a question type, evaluate the learning target.

| Learning target | Preferred type |
|---|---|
| Named term with unique definition | Identification |
| Command with unique function | Identification |
| Named model/pattern/architecture | Identification |
| Clearly identifiable concept | Identification |
| Benefit | Multiple Choice |
| Advantage | Multiple Choice |
| Disadvantage | Multiple Choice |
| Characteristic | Multiple Choice |
| Comparison | Multiple Choice |
| Distinction | Multiple Choice |
| Application | Multiple Choice |
| Scenario reasoning | Multiple Choice |
| Consequence/effect | Multiple Choice |
| Source-specific interpretation | Multiple Choice |
| Precise binary claim | True/False |
| Explicit list of items | Enumeration |
| Ordered procedure | Enumeration |

This table is a default, not an automatic rule.

The final decision must pass the question-type fitness audit.

---

# 24. Intelligent Question Generation

Do NOT select questions using simple random sampling.

Build a question pool containing:

```text
chapter
topic
concept
learning target
question type
difficulty
cognitive level
importance
source section
source confidence
tested concepts
accepted answers
distractor quality
time cost
```

Select questions only after coverage and quality constraints are satisfied.

Randomness may be used only among comparable candidates.

---

# 25. Exam Blueprint

Before generation, determine:

1. major topics;
2. question counts per topic;
3. important concepts;
4. suitable question types;
5. difficulty distribution;
6. cognitive-demand distribution;
7. concepts requiring direct assessment;
8. concepts suitable for application;
9. concepts that should not be repeated;
10. questions reserved for other exam modes.

Example:

```text
Topic A → 20%
Topic B → 25%
Topic C → 30%
Topic D → 25%
```

Adapt to the actual source.

---

# 26. Concept Coverage

Track concepts explicitly.

Example:

```javascript
{
    topic: "integration",
    concept: "hub-and-spoke integration",
    sourceSection: "Integration Patterns",
    importance: "high",
    suitableTypes: [
        "identification",
        "multipleChoice"
    ]
}
```

Do not repeatedly test the same fact using superficial rewording.

---

# 27. Learning-Value Selection

Each candidate receives an internal assessment based on:

```text
coverageValue
importanceValue
cognitiveValue
difficultyFit
questionTypeFit
sourceConfidence
redundancyPenalty
ambiguityPenalty
timeCostPenalty
```

Conceptually:

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

Exact weights are implementation details.

The important requirement is that question selection is deliberate.

---

# 28. Intelligent Identification Generation

For the dedicated Identification bank:

1. Extract named terms and clearly identifiable concepts.
2. Extract their explicit definitions.
3. Determine whether each has one defensible answer.
4. Apply the Identification Fitness Gate.
5. Reject ambiguous concepts.
6. Define accepted answers from the reference.
7. Prevent synonym invention.
8. Check source wording independence.
9. Balance important terms across the chapter.
10. Prevent near-duplicate definitions.
11. Validate each answer.
12. Only then select the final Identification questions.

The Identification generator must NOT simply convert random reference sentences into questions.

---

# 29. Identification Quality Audit

Every Identification question must pass:

### Term Test

Is the answer actually a term/name/concept?

### Uniqueness Test

Would knowledgeable students converge on one answer?

### Source Test

Is the answer explicitly supported by the reference?

### Defensibility Test

Could another reasonable answer also satisfy the prompt?

### Universality/Concept Test

Does the prompt identify the concept rather than merely asking for a textbook-specific opinion about it?

### Synonym Test

Are accepted alternatives explicitly justified?

### Concision Test

Can the expected answer be supplied as a reasonably concise term?

### Context Test

Does the prompt contain enough information to identify the intended term?

If any major test fails:

```text
Identification candidate
        ↓
REJECT / CONVERT TO MC
```

---

# 30. Identification Red Flags

Avoid Identification questions that primarily ask:

```text
What is a benefit of X?
What is an advantage of X?
What is a disadvantage of X?
What is a characteristic of X?
What is one effect of X?
What is one reason X is useful?
What does the textbook recommend about X?
Which property does X have?
What is the preferred approach according to the reference?
```

These usually test information ABOUT a concept rather than the concept's identity.

Prefer Multiple Choice.

Exception: if the requested answer itself is a uniquely named term and the reference clearly establishes that term.

---

# 31. Identification Conversion Examples

### Example A

```text
Reference:
Hub-and-spoke integration uses a central hub to route and translate communication.

Identification:
"What integration pattern uses a central hub?"
→ ACCEPT
Answer: hub-and-spoke integration
```

### Example B

```text
Reference:
Hub-and-spoke integration reduces direct point-to-point connections.

Identification:
"What is a benefit of hub-and-spoke integration?"
→ REJECT as Identification
→ CONVERT to Multiple Choice
```

### Example C

```text
Reference:
The architecture improves centralized management.

Identification:
"What benefit does the architecture provide?"
→ REJECT as Identification
→ Multiple Choice
```

### Example D

```text
Reference:
A process named "Business Process Reengineering" radically redesigns processes.

Identification:
"What approach radically redesigns business processes?"
→ ACCEPT
```

The difference is the target:

```text
Term identity → Identification
Information about term → Usually Multiple Choice
```

---

# 32. Multiple Choice Anti-Guessing

Do not allow correct answers to stand out through:

- length;
- technicality;
- grammar;
- specificity;
- professional wording;
- keyword repetition;
- absolute words;
- answer position.

Correct answers must have parity with distractors.

---

# 33. Correct Answer Length

Do not consistently make the correct answer the longest or shortest.

Audit:

```text
word count
character count
sentence count
```

Rewrite choices if the correct answer is an obvious outlier.

---

# 34. Semantic Parity

Choices must belong to the same conceptual category.

Bad:

```text
A. Improve security
B. A programming language
C. Reduce latency
D. Improve reliability
```

Good:

```text
A. Improve security
B. Improve reliability
C. Reduce latency
D. Reduce redundancy
```

---

# 35. Grammatical and Specificity Parity

All choices should:

- fit naturally into the question;
- have comparable specificity;
- use comparable technicality;
- have comparable writing quality.

Do not make the correct answer uniquely sophisticated.

---

# 36. Answer Position Balance

Across a question bank:

```text
A ≈ B ≈ C ≈ D
```

Avoid predictable patterns.

For approximately 30 questions:

```text
A = 7–8
B = 7–8
C = 7–8
D = 7–8
```

Do not use:

```text
A B C D A B C D
```

or other obvious patterns.

---

# 37. Difficulty

Difficulty should come from reasoning, not obscurity.

### Easy
- direct recall;
- explicit definition;
- direct recognition.

### Medium
- interpretation;
- comparison;
- applying a documented rule.

### Hard
- multi-step application;
- combining source-supported concepts;
- distinguishing closely related concepts.

Do not label a question Hard merely because it is confusing.

---

# 38. Cognitive Demand

Track separately:

```text
Recall
Understanding
Application
Analysis
```

Do not force high-level questions when the reference does not support them.

---

# 39. Repetition Control

Avoid:

```text
What is X?
Define X.
Which statement describes X?
```

unless the questions test different cognitive demands.

Conceptual duplication should be tracked even when wording differs.

---

# 40. Question Validation

For every question:

1. Locate supporting reference material.
2. Verify the intended answer.
3. Verify distractors or accepted answers.
4. Verify the explanation.
5. Check ambiguity.
6. Check question-type fitness.
7. Check cognitive demand.
8. Check difficulty.
9. Check redundancy.
10. Check timed feasibility where applicable.

---

# 41. Ambiguity Audit

Ask:

> Could a knowledgeable student reasonably argue that another answer is correct?

If yes:

- rewrite;
- narrow the prompt;
- improve distractors;
- or change the question type.

Never hide ambiguity behind the answer key.

---

# 42. Distractor Quality

Distractors should represent:

- common misconceptions;
- related concepts;
- partial misunderstandings;
- correct concepts applied incorrectly;
- concepts from another context.

Do not use ridiculous distractors.

---

# 43. Question Shuffling

Use proper randomization such as Fisher-Yates.

Do not mutate the original bank unnecessarily.

Stable question IDs must remain unchanged.

---

# 44. Answer Shuffling

When answer choices are shuffled:

- preserve the correct answer;
- preserve explanation;
- recalculate the correct-answer index;
- preserve question ID.

Never leave a stale correct-answer index after shuffling.

---

# 45. Chapter Modes and Locking

A chapter may have:

```text
Experience:
Interactive / Form

Time:
Timed / Untimed

Type:
Multiple Choice / Identification / True/False / Enumeration / Mixed
```

Once an attempt starts:

- type cannot change;
- experience mode cannot change;
- time mode cannot change;
- other exams cannot be opened;
- Answer Key cannot be opened;
- answer-revealing material cannot be opened.

Reset must clear the attempt.

---

# 46. Timed Behavior

Timed questions receive exactly 10 seconds by default.

When the timer reaches zero:

1. mark incorrect;
2. award zero;
3. reveal the answer;
4. display `Time's up!`;
5. lock the question;
6. permit continuation.

Clear old timers before starting new ones.

Never allow stale timers to affect another question.

---

# 47. Untimed Behavior

Untimed mode must:

- display no countdown;
- have no hidden timeout;
- allow unlimited response time;
- preserve normal validation behavior.

---

# 48. Overall Exam

The Overall Exam is always:

```text
UNTIMED
```

It should:

- combine chapters intelligently;
- use its own blueprint;
- avoid random concatenation;
- maintain chapter metadata;
- control redundancy;
- preserve balanced coverage.

It should use Form-style layout by default unless another behavior is explicitly configured.

---

# 49. Dedicated Identification Tab

Provide:

```text
Identification
```

as a dedicated examination area.

It should show each chapter's available Identification questions.

Example:

```text
Identification

Chapter 1 — 8 questions
[Start]

Chapter 2 — 6 questions
[Start]

Chapter 3 — 10 questions
[Start]
```

The dedicated Identification tab must use the Identification Fitness Gate.

It must NOT simply take random chapter questions and convert them to Identification.

---

# 50. Identification State Independence

Identification exams have independent state.

Do not reuse:

```text
chapter MC score
chapter currentQuestion
chapter selectedAnswer
chapter timer
chapter completion state
```

unless a separate attempt state is intentionally created.

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

---

# 51. Results

Results should show:

```text
Final Score
Correct
Incorrect
Unanswered
Percentage
```

For partial-credit Enumeration:

```text
Points Earned
Points Possible
```

For Identification, show:

```text
Student Answer
Accepted Answer
Correctness
Explanation
```

only after submission.

---

# 52. Encyclopedia

Provide an Encyclopedia containing important terms.

Each entry:

```text
Term
Definition
Useful explanation
Chapter
```

Definitions must be reference-grounded.

The Encyclopedia may support Identification preparation, but it must not expose answers during an active protected exam.

---

# 53. Coverage Planning

Before writing questions, create a coverage matrix.

Example:

```text
Concept A → 4
Concept B → 3
Concept C → 5
Process D → 4
Comparison E → 3
```

Prioritize major concepts.

Do not allow minor concepts to dominate merely because they are easy to turn into questions.

---

# 54. Exam-to-Exam Variation

Different attempts may vary questions, but coverage must remain educationally equivalent.

Do not use randomness as a substitute for blueprinting.

Correct:

```text
Blueprint
→ valid candidates
→ quality filter
→ coverage selection
→ random choice among comparable candidates
```

Incorrect:

```text
Question bank
→ random shuffle
→ first N
```

---

# 55. Adaptive Reinforcement

If previous performance exists, future Interactive exams may increase coverage of weak concepts.

Example:

```text
Permissions → weak
Archives → strong
Navigation → strong
```

The next exam may increase valid coverage of Permissions.

Do not repeatedly ask the same question.

Adaptive behavior should target:

- weak concepts;
- missed distinctions;
- weak question types;
- important underrepresented concepts.

---

# 56. Per-Mode Question Pools

Maintain logical pools:

```text
chapterBank
interactiveBank
formBank
timedBank
untimedBank
identificationBank
multipleChoiceBank
trueFalseBank
enumerationBank
```

These may be filtered views over one underlying bank.

Selection must still apply mode-specific constraints.

---

# 57. Mode-Specific Generation

### Interactive + Timed

Prefer:

- concise questions;
- clear answer targets;
- quickly validated responses.

### Interactive + Untimed

Allow:

- deeper application;
- comparison;
- more complex reasoning.

### Form + Timed

Prefer:

- concise questions;
- balanced complete coverage;
- manageable reading burden.

### Form + Untimed

Allow:

- comprehensive coverage;
- longer application;
- richer Enumeration.

### Identification

Prefer:

- named terms;
- uniquely identifiable concepts;
- explicit definitions;
- defensible expected answers.

---

# 58. Generation Audit

Before displaying an exam, check:

```text
[ ] Question count
[ ] Chapter/topic coverage
[ ] Important concept coverage
[ ] Question-type fitness
[ ] Difficulty distribution
[ ] Cognitive distribution
[ ] Duplicate concepts
[ ] Near-duplicate wording
[ ] Source support
[ ] Answer uniqueness
[ ] MC distractor quality
[ ] Identification fitness
[ ] Identification accepted answers
[ ] Enumeration scoring
[ ] True/False ambiguity
[ ] Timed feasibility
[ ] Answer-position balance
```

If the audit fails, replace the problematic questions rather than blindly reshuffling the whole exam.

---

# 59. Targeted Regeneration

When validation fails:

1. Identify the failed constraint.
2. Identify the responsible question.
3. Remove or revise that question.
4. Select a better candidate.
5. Re-run validation.
6. Repeat as necessary.

If the source cannot support the requested exam structure, report the limitation.

Never invent material to satisfy a target count.

---

# 60. Final Question-Type Audit

Before accepting every question, ask:

### Identification

> Is the expected answer a uniquely identifiable term/concept?

> Does the prompt identify the term rather than ask for information about it?

> Could another reasonable term satisfy the prompt?

> Is the answer explicitly supported by the reference?

If not, convert to Multiple Choice or reject.

### Multiple Choice

> Are there four plausible choices?

> Is exactly one defensible?

> Is the question testing a useful distinction rather than superficial recall?

### True/False

> Is the statement precisely supported?

> Is there only one truth value?

### Enumeration

> Does the reference explicitly provide the required set?

> Is scoring defined?

---

# 61. Final Anti-Guessing Standard

A student who does not know the material should not reliably identify answers from:

- answer length;
- answer position;
- grammar;
- technical vocabulary;
- professional wording;
- specificity;
- keyword repetition;
- absolute wording;
- formatting.

For Identification, a student should not be able to exploit:

- vague prompts;
- textbook sentence matching;
- arbitrary synonym acceptance;
- obvious answer leakage;
- inconsistent answer specificity.

---

# 62. UI Requirements

The interface must be:

- professional;
- academic;
- readable;
- responsive;
- accessible;
- restrained.

Avoid:

- excessive gradients;
- excessive animation;
- unnecessary glassmorphism;
- cluttered dashboards;
- decorative effects that reduce readability.

Use clear states for:

```text
Default
Hover
Focus
Selected
Correct
Incorrect
Disabled
Submitted
Locked
```

Do not communicate correctness through color alone.

---

# 63. Componentization

When using React or another framework, use sensible separation:

```text
ExamApp
├── Navigation
├── ChapterSelector
├── ExamTypeSelector
├── ExperienceModeSelector
├── TimeModeSelector
├── ChapterExam
│   ├── QuestionCard
│   ├── AnswerControls
│   ├── Timer
│   ├── Progress
│   └── Feedback
├── IdentificationExam
├── OverallExam
├── AnswerKey
├── Encyclopedia
└── Results
```

Avoid over-componentization.

---

# 64. Data Integrity

The question bank is authoritative.

UI operations such as:

- shuffle;
- filtering;
- sorting;
- rendering;
- pagination;

must not alter the underlying correctness data.

Use stable IDs and immutable transformations where practical.

---

# 65. No Fake Functionality

Every displayed control must work.

If the UI contains:

```text
Start
Next
Shuffle
Reset
Review
Answer Key
Encyclopedia
Submit
```

each must perform its intended action.

---

# 66. Final Generation Pipeline

Use this sequence:

```text
REFERENCE
   ↓
Analyze chapters
   ↓
Extract concepts
   ↓
Extract explicit definitions / terms / lists
   ↓
Determine learning targets
   ↓
Assign question-type fitness
   ↓
Apply Identification Fitness Gate
   ↓
Build concept coverage matrix
   ↓
Build exam blueprint
   ↓
Generate candidates
   ↓
Validate source support
   ↓
Validate answerability
   ↓
Validate question-type fitness
   ↓
Validate difficulty / cognitive demand
   ↓
Remove redundancy
   ↓
Select high-utility questions
   ↓
Generate distractors where needed
   ↓
Balance answer positions
   ↓
Audit anti-guessing
   ↓
Audit timed feasibility
   ↓
Audit Identification answers
   ↓
Final validation
   ↓
Build UI
   ↓
Connect state
   ↓
Implement modes
   ↓
Implement locking
   ↓
Implement scoring
   ↓
Implement shuffle/reset
   ↓
Implement Overall Exam
   ↓
Implement Answer Key
   ↓
Implement Encyclopedia
   ↓
DELIVER
```

---

# 67. Priority Rules

When requirements conflict:

1. Reference accuracy
2. Question-type fitness
3. Exactly one defensible answer
4. Learning-target coverage
5. Assessment quality
6. Strong distractors
7. Anti-guessing parity
8. Difficulty/cognitive balance
9. Answer distribution
10. UI appearance

Never sacrifice correctness merely to make an exam harder or to reach an arbitrary count.

---

# 68. Final Delivery Checklist

```text
[ ] Reference analyzed
[ ] Chapters identified
[ ] Concepts extracted
[ ] Explicit definitions extracted
[ ] Named terms identified
[ ] Lists/categories identified
[ ] Question-type fitness assigned
[ ] Identification Fitness Gate applied
[ ] Identification questions are uniquely answerable
[ ] Identification answers are reference-grounded
[ ] Identification synonyms are not invented
[ ] Textbook-dependent benefits/advantages/etc. are not improperly forced into Identification
[ ] Multiple Choice used for source-dependent distinctions where appropriate
[ ] ~30 questions/chapter where supported
[ ] Four choices for MC
[ ] Exactly one MC answer
[ ] Strong distractors
[ ] True/False unambiguous
[ ] Enumeration source-grounded
[ ] Enumeration scoring defined
[ ] Duplicate concepts controlled
[ ] Near-duplicate wording controlled
[ ] Coverage blueprint created
[ ] Difficulty audited
[ ] Cognitive demand audited
[ ] Timed feasibility audited
[ ] Answer lengths audited
[ ] Grammar audited
[ ] Specificity audited
[ ] Technicality audited
[ ] Answer positions balanced
[ ] Answer patterns audited
[ ] Interactive mode works
[ ] Form mode works
[ ] Timed mode works
[ ] Untimed mode works
[ ] Chapter locking works
[ ] Reset works
[ ] Shuffle works
[ ] Overall Exam is always Untimed
[ ] Dedicated Identification tab exists
[ ] Identification state is independent
[ ] Answer Key works
[ ] Encyclopedia works
[ ] Results work
[ ] Review works
[ ] Responsive UI works
[ ] Empty/error states are handled
[ ] No unsupported facts
[ ] No fake functionality
```

---

# 69. Final Principle

The system should behave like an assessment designer, not a random question picker.

Most importantly:

> **Identification asks the student to name a clearly identifiable term or concept. Multiple Choice tests information where the answer depends more heavily on distinctions, benefits, characteristics, comparisons, applications, or other source-dependent context.**

The system must never force a question into Identification simply because the sentence can be grammatically phrased as:

> "What is...?"

The final exam should reward actual understanding of the reference rather than memorization of arbitrary wording or exploitation of question-format patterns.

A successful exam is:

```text
REFERENCE-GROUNDED
+
COVERAGE-DRIVEN
+
QUESTION-TYPE FIT
+
UNAMBIGUOUS
+
DIFFICULT BUT FAIR
+
ANTI-GUESSING
+
INTERACTIVE
```
