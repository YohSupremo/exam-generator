<?php
declare(strict_types=1);

/**
 * thesis/api/generate.php
 * Generates multiple thesis title suggestions based on the selected problem,
 * field of study, and preferred title framework.
 *
 * POST /thesis/api/generate.php
 * Body (JSON): {
 *   problem: string,          // selected problem title/description
 *   field: string,            // academic field
 *   location: string,         // country or city
 *   framework: string,        // correlational|comparative|developmental|descriptive|mixed
 *   count: int,               // how many titles to generate (5-10)
 *   keywords: string[],       // optional seed keywords
 *   level: string,            // undergraduate|masters|phd
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__) . '/lib/groq.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

$problem   = trim($body['problem']   ?? '');
$field     = trim($body['field']     ?? 'Computer Science');
$location  = trim($body['location']  ?? 'Philippines');
$framework = trim($body['framework'] ?? 'mixed');
$count     = max(3, min(6, (int)($body['count'] ?? 6)));  // cap at 6 to stay within token budget
$keywords  = array_filter(array_map('trim', (array)($body['keywords'] ?? [])));
$level     = trim($body['level']     ?? 'undergraduate');

if ($problem === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'A research problem is required.']);
    exit;
}

// ------------------------------------------------------------------
// Framework descriptions to guide the AI
// ------------------------------------------------------------------
$frameworkDescriptions = [
    'correlational'  => 'Correlational – examines the relationship between two or more variables. Titles should name both variables and the population.',
    'comparative'    => 'Comparative – compares two groups, methods, branches, or systems. Titles should name what is being compared.',
    'developmental'  => 'Developmental – an applied, build-oriented study. The deliverable must be a concrete artifact: a Management System, Information System, Web Application, Mobile App, Dashboard, Monitoring Platform, Decision Support System, Detection System, Automation Tool, or Framework. Vary the wording: use "Design and Implementation of", "An Automated ... for", "... : A Management System for", "Prototyping a ...", "Development and Evaluation of". Do NOT simply write "Development of a ... for ..." every time.',
    'descriptive'    => 'Descriptive – describes prevalence, current state, or characteristics. Titles should use Assessment, Profile, Status, or Survey wording.',
    'experimental'   => 'Experimental/Quasi-experimental – tests the effect of an intervention. Titles should name the intervention and the outcome.',
    'mixed'          => 'Mixed – deliberately combine applied system-building titles with correlational, comparative, descriptive, and experimental titles. Do not let one style dominate.',
];

$frameworkGuide = $frameworkDescriptions[$framework] ?? $frameworkDescriptions['mixed'];
$keywordHint    = !empty($keywords) ? 'Incorporate these keywords where natural: ' . implode(', ', $keywords) . '.' : '';

$levelMap = [
    'undergraduate' => 'undergraduate thesis (Bachelor\'s level)',
    'masters'       => 'Master\'s thesis',
    'phd'           => 'Ph.D. dissertation',
];
$levelLabel = $levelMap[$level] ?? 'undergraduate thesis';

// Rotate a pool of genuinely different title constructions so the AI cannot
// fall back into one template ("Development of a X for Y") for every result.
// The pool is scoped to the chosen framework so a "Developmental" request
// still yields applied system papers, but with varied, realistic wording.
$patternPools = [
    'developmental' => [
        'Design and Implementation of a [specific system] for [population] in [location]',
        '[Specific System Name]: A Management System for [process] among [population] in [location]',
        'An Automated [system/tool] for [process]: A Case Study of [population] in [location]',
        'Development and Evaluation of a [platform/framework] for [population] in [location]',
        'A Decision Support System for [decision] in [domain] within [location]',
        'A [web/mobile] Application for [service] among [population] in [location]',
        'Prototyping an Information System for [process] in [location]: A Usability Study',
        'Building a [system] Dashboard for [population]: Design and Assessment in [location]',
    ],
    'correlational' => [
        'Relationship Between [variable A] and [variable B] Among [population] in [location]',
        'Assessment of [variables] Using a [model/framework] among [population] in [location]',
        'Modeling [problem] with [technique]: Implications for [population] in [location]',
        'Predictors of [outcome] Among [population] in [location]',
        'Correlates of [variable] in [domain]: Evidence from [location]',
        'Influence of [factor] on [outcome] Among [population] in [location]',
    ],
    'comparative' => [
        'Comparative Analysis of [approach A] and [approach B] for [process] in [location]',
        'A Comparison of [system A] and [system B] in [domain]: Evidence from [location]',
        '[Approach A] versus [Approach B] for [problem]: A Comparative Study in [location]',
        'Comparing [method A] and [method B] Among [population] in [location]',
        'Effectiveness of [A] versus [B] in [domain]: A Comparative Assessment',
        'Cross-Sectional Comparison of [groups] on [variable] in [location]',
    ],
    'descriptive' => [
        'Status of [phenomenon] Among [population] in [location]',
        'A Survey of [practice] in [domain]: Baseline Study in [location]',
        'Profile of [population] on [variable]: A Descriptive Study in [location]',
        'Prevalence and Determinants of [condition] Among [population] in [location]',
        'Current Practices in [process]: An Assessment of [population] in [location]',
        'Assessing [variable] Levels Among [population] in [location]',
    ],
    'experimental' => [
        'Effects of [intervention] on [outcome]: Evidence from [population] in [location]',
        'The Impact of [intervention] on [outcome]: A Quasi-Experimental Study in [location]',
        'Evaluating [intervention] Against [baseline] Among [population] in [location]',
        'Effectiveness of [program] in Improving [outcome] Among [population]',
        'A Controlled Test of [intervention] for [problem] in [location]',
        'Measuring the Effect of [factor] on [outcome] in [domain]: Field Experiment in [location]',
    ],
];
$patternPools['mixed'] = array_merge(...array_values($patternPools));

$patternPool = $patternPools[$framework] ?? $patternPools['mixed'];
shuffle($patternPool);
$assignedPatterns = array_slice($patternPool, 0, $count);
$patternList = '';
foreach ($assignedPatterns as $idx => $pat) {
    $patternList .= ($idx + 1) . '. ' . $pat . "\n";
}

$systemPrompt = <<<SYSTEM
You are a seasoned academic thesis advisor with 20+ years of experience mentoring students in the Philippines and abroad. You write realistic, publishable thesis titles that read like actual BS/MA/PhD research proposals — not padded, generic sentences.

A great thesis title must be:
- Concrete: name the actual artifact, system, model, or variables being studied
- Specific: include the target population and the Philippines location when given
- Measurable and doable within 6–12 months
- Original and not a copy of an existing published title
- Natural academic English, 10–18 words, no unnecessary jargon

TITLE DIVERSITY IS A HARD REQUIREMENT:
- Never produce two titles with the same opening phrase.
- Use "Development of a/an" at most ONCE in the entire set.
- Replace bracketed placeholders with field-appropriate specifics; adapt a pattern if it does not fit the field, but keep the opening style distinct.
- For applied/IT fields, prefer real system titles such as "... Management System", "... Information System", "... Monitoring Platform", "... Decision Support System", or "... Mobile Application" over vague "Development of a X for Y" wording.

BAD (do not imitate):
- "Development of a Cybersecurity Awareness Curriculum for Philippine Bank Employees in Metro Manila"
- "Development of a Secure Multi-Factor Authentication Framework for Philippine Banks' Mobile Apps"

GOOD (imitate this level of specificity):
- "Design and Implementation of a Fraud Detection Management System for Philippine Commercial Banks"
- "Real-Time Transaction Monitoring Platform for Philippine Banks: A Case Study of Metro Manila Branches"
- "An Automated Threat Detection Tool for Online Banking: Evaluating Detection Accuracy in the Philippines"

Always respond in valid JSON only. No markdown. No explanation outside JSON.
SYSTEM;

$userPrompt = <<<USER
Generate exactly {$count} DISTINCT thesis title suggestions for a {$levelLabel} in {$location}.

Research Problem: {$problem}
Academic Field: {$field}
Framework Preference: {$frameworkGuide}
{$keywordHint}

Use these assigned title constructions — one per title, in order. Do not reuse an opening style:
{$patternList}
For each title:
1. A polished thesis title (10–18 words, include population and location)
2. Framework type (one word: Correlational, Comparative, Developmental, Descriptive, or Experimental)
3. Variables: independent, dependent, and moderating (use null string if none)
4. Rationale: MAX 12 words explaining why this title is researchable
5. Novelty score 1–10

CRITICAL: You MUST complete all {$count} title objects and close the JSON array and object properly before stopping. Titles must not be near-duplicates of one another.

Return ONLY a JSON object in this exact format:
{
  "titles": [
    {
      "id": 1,
      "title": "Specific System/Model Title for [Population] in [Location]",
      "framework": "Developmental",
      "variables": {
        "independent": "Variable name",
        "dependent": "Variable name",
        "moderating": "null"
      },
      "rationale": "Twelve words max rationale here.",
      "novelty_score": 8
    }
  ]
}
USER;

/**
 * Parse the AI response into titles. Handles clean JSON, truncated JSON,
 * and responses that contain only a subset of complete title objects.
 */
function parseTitleResponse(string $text): ?array
{
    $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
    $text = preg_replace('/```\s*$/m', '', $text);
    $text = trim($text);

    // Attempt 1: clean parse
    $decoded = json_decode($text, true);
    if (is_array($decoded) && !empty($decoded['titles'])) {
        return $decoded;
    }

    // Attempt 2: salvage every fully-closed title object from a truncated reply
    $salvaged = [];
    if (preg_match_all('/\{[^{}]*"id"\s*:\s*\d+[^{}]*"novelty_score"\s*:\s*\d+[^{}]*\}/s', $text, $matches)) {
        foreach ($matches[0] as $raw) {
            $obj = json_decode($raw, true);
            if (is_array($obj) && !empty($obj['title'])) {
                $salvaged[] = $obj;
            }
        }
    }

    // Attempt 3: close the truncated JSON by appending missing brackets
    if (empty($salvaged)) {
        $repaired      = $text;
        $open_braces   = substr_count($repaired, '{') - substr_count($repaired, '}');
        $open_brackets = substr_count($repaired, '[') - substr_count($repaired, ']');
        if (preg_match('/"[^"]*$/', $repaired)) {
            $repaired .= '"';
        }
        $repaired .= str_repeat('}', max(0, $open_braces));
        $repaired .= str_repeat(']', max(0, $open_brackets));
        if ($open_braces > 0) {
            $repaired .= '}';
        }
        $decoded2 = json_decode($repaired, true);
        if (is_array($decoded2) && !empty($decoded2['titles'])) {
            $salvaged = array_filter($decoded2['titles'], fn($t) => !empty($t['title']));
        }
    }

    return !empty($salvaged) ? ['titles' => array_values($salvaged)] : null;
}

/** First three significant words of a title, used as a template fingerprint. */
function titleOpening(string $title): string
{
    $clean = strtolower((string)preg_replace('/[^a-z0-9 ]+/i', ' ', $title));
    $words = preg_split('/\s+/', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return implode(' ', array_slice($words, 0, 3));
}

/** True when the model collapsed into one repeated opening template. */
function titlesAreRepetitive(array $titles): bool
{
    $seen = [];
    $devCount = 0;
    foreach ($titles as $t) {
        $title = (string)($t['title'] ?? '');
        $key = titleOpening($title);
        if ($key === '' || isset($seen[$key])) {
            return true;
        }
        $seen[$key] = true;
        if (preg_match('/^\s*development\s+of\b/i', $title)) {
            $devCount++;
        }
    }
    return $devCount > 1;
}

$client = new ThesisGroqClient();
$result = $client->chat(
    [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => $userPrompt],
    ],
    temperature: 0.9,
    maxTokens: 4096
);

if (!$result['ok']) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

$decoded = parseTitleResponse($result['text']);
if ($decoded === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'AI returned unparseable JSON. Raw: ' . substr($result['text'], 0, 300)]);
    exit;
}

// ── Diversity guard: retry once if every title used the same template ────────
if (titlesAreRepetitive($decoded['titles'])) {
    $rejected = implode("\n", array_map(
        fn($t) => '- ' . (string)($t['title'] ?? ''),
        $decoded['titles']
    ));
    $strictPrompt = $userPrompt
        . "\n\nSTRICT OVERRIDE — your previous attempt reused one template:\n{$rejected}\n"
        . "Rewrite all {$count} titles. Every title must start with a DIFFERENT three-word opening. "
        . "Use \"Development of\" at most once. Use concrete artifacts such as Management System, "
        . "Information System, Monitoring Platform, Decision Support System, or Mobile Application "
        . "where they fit the field. Return the same JSON format only.";

    $retry = $client->chat(
        [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $strictPrompt],
        ],
        temperature: 1.0,
        maxTokens: 4096
    );
    if ($retry['ok']) {
        $retryDecoded = parseTitleResponse($retry['text']);
        if ($retryDecoded !== null && !titlesAreRepetitive($retryDecoded['titles'])) {
            $decoded = $retryDecoded;
            $result  = $retry;
        }
    }
}

echo json_encode([
    'ok'       => true,
    'titles'   => $decoded['titles'],
    'problem'  => $problem,
    'field'    => $field,
    'location' => $location,
    'model'    => $result['model'],
]);
