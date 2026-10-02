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
    'correlational'  => 'Correlational – examines relationship/association between two or more variables. Example: "The Relationship Between X and Y Among Z"',
    'comparative'    => 'Comparative – compares two groups, methods, or systems. Example: "A Comparative Analysis of X and Y in Z"',
    'developmental'  => 'Developmental – involves creating, designing, or developing a system/tool/curriculum. Example: "Development of an X System for Y"',
    'descriptive'    => 'Descriptive – describes the current state, prevalence, or characteristics of a phenomenon. Example: "An Assessment of X Among Y in Z"',
    'experimental'   => 'Experimental/Quasi-experimental – tests the effect of an intervention. Example: "Effects of X Intervention on Y Among Z"',
    'mixed'          => 'Mixed – use a variety of frameworks: correlational, comparative, developmental, descriptive, and experimental',
];

$frameworkGuide = $frameworkDescriptions[$framework] ?? $frameworkDescriptions['mixed'];
$keywordHint    = !empty($keywords) ? 'Incorporate these keywords where natural: ' . implode(', ', $keywords) . '.' : '';

$levelMap = [
    'undergraduate' => 'undergraduate thesis (Bachelor\'s level)',
    'masters'       => 'Master\'s thesis',
    'phd'           => 'Ph.D. dissertation',
];
$levelLabel = $levelMap[$level] ?? 'undergraduate thesis';

$systemPrompt = <<<SYSTEM
You are a seasoned academic thesis advisor with 20+ years of experience mentoring students in the Philippines, Southeast Asia, and globally. You specialize in crafting highly specific, researchable, and publishable thesis titles.

A great thesis title must be:
- Specific (not vague): Include the target population, location, and variable
- Measurable and researchable within 6–12 months
- Original and not a copy of existing published work
- Appropriate for the academic level
- Properly formatted (no unnecessary jargon, no overly long titles >20 words)

Always respond in valid JSON only. No markdown. No explanation outside JSON.
SYSTEM;

$userPrompt = <<<USER
Generate exactly {$count} high-quality thesis title suggestions for a {$levelLabel} in {$location}.

Research Problem: {$problem}
Academic Field: {$field}
Framework Preference: {$frameworkGuide}
{$keywordHint}

For each title:
1. A polished thesis title (10–18 words, include population and location)
2. Framework type (one word: Correlational, Comparative, Developmental, Descriptive, or Experimental)
3. Variables: independent, dependent, and moderating (use null string if none)
4. Rationale: MAX 12 words explaining why this title is researchable
5. Novelty score 1–10

CRITICAL: You MUST complete all {$count} title objects and close the JSON array and object properly before stopping.

Return ONLY a JSON object in this exact format:
{
  "titles": [
    {
      "id": 1,
      "title": "Full Thesis Title Here: A Study Among [Population] in [Location]",
      "framework": "Correlational",
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

$text    = $result['text'];
$text    = preg_replace('/^```(?:json)?\s*/m', '', $text);
$text    = preg_replace('/```\s*$/m', '', $text);
$text    = trim($text);

// ── Attempt 1: clean parse ──────────────────────────────────────────────────
$decoded = json_decode($text, true);

// ── Attempt 2: truncation repair ────────────────────────────────────────────
// The response may be cut off mid-object. Extract every fully-closed title
// object (has all required keys and ends with a closing brace) via regex.
if (!is_array($decoded) || empty($decoded['titles'])) {
    $salvaged = [];
    // Match each {...} block that starts with an "id" key (complete object)
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
        $repaired = $text;
        // Count unmatched braces/brackets
        $open_braces   = substr_count($repaired, '{') - substr_count($repaired, '}');
        $open_brackets = substr_count($repaired, '[') - substr_count($repaired, ']');
        // Close last incomplete string value if truncated mid-string
        if (preg_match('/"[^"]*$/', $repaired)) {
            $repaired .= '"';
        }
        // Close open objects and arrays
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
    if (!empty($salvaged)) {
        $decoded = ['titles' => array_values($salvaged)];
    }
}

if (!is_array($decoded) || empty($decoded['titles'])) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'AI returned unparseable JSON. Raw: ' . substr($text, 0, 300)]);
    exit;
}

echo json_encode([
    'ok'       => true,
    'titles'   => $decoded['titles'],
    'problem'  => $problem,
    'field'    => $field,
    'location' => $location,
    'model'    => $result['model'],
]);
