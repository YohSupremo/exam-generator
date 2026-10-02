<?php
declare(strict_types=1);

/**
 * thesis/api/analyze.php
 * Analyzes a thesis title and returns a detailed quality report covering
 * specificity, researchability, originality, and improvement suggestions.
 *
 * POST /thesis/api/analyze.php
 * Body (JSON): { title: string, field: string, level: string }
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

$raw   = file_get_contents('php://input');
$body  = json_decode($raw, true) ?? [];
$title = trim($body['title'] ?? '');
$field = trim($body['field'] ?? '');
$level = trim($body['level'] ?? 'undergraduate');

if ($title === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Title is required.']);
    exit;
}

// ------------------------------------------------------------------
// Local heuristic checks (instant, no API cost)
// ------------------------------------------------------------------
$wordCount   = str_word_count($title);
$charCount   = strlen($title);
$hasColon    = str_contains($title, ':');
$vaguePhrases = ['effect of', 'impact of', 'study of', 'analysis of', 'a study on'];
$isVague      = false;
foreach ($vaguePhrases as $phrase) {
    if (stripos($title, $phrase) !== false) {
        $isVague = true;
        break;
    }
}

$systemPrompt = <<<SYSTEM
You are an expert academic thesis evaluator. You score thesis titles on multiple criteria used by Philippine and international research committees.

Always respond in valid JSON only. No markdown, no extra text.
SYSTEM;

$userPrompt = <<<USER
Analyze this thesis title for academic quality:

Title: "{$title}"
Field: {$field}
Level: {$level}

Score the title on these 5 criteria (0–20 points each, total /100):
1. Specificity – Does it clearly identify the topic, population, and context?
2. Researchability – Can it be studied with a clear methodology?
3. Novelty – Is it original and not a rehash of common topics?
4. Clarity – Is it concise (10–18 words) and jargon-free?
5. Scope – Is the scope appropriate for the academic level?

Also provide:
- 3 concrete improvement suggestions to make the title stronger
- 2 alternative improved versions of the title
- A one-line verdict on its readiness for submission

Return ONLY this JSON:
{
  "scores": {
    "specificity": 16,
    "researchability": 14,
    "novelty": 12,
    "clarity": 18,
    "scope": 15,
    "total": 75
  },
  "grade": "B+",
  "verdict": "One-line verdict here.",
  "suggestions": [
    "Suggestion 1",
    "Suggestion 2",
    "Suggestion 3"
  ],
  "alternatives": [
    "Improved Title Version 1",
    "Improved Title Version 2"
  ],
  "detected_framework": "Correlational",
  "word_count": 14,
  "too_vague": false,
  "too_broad": false
}
USER;

$client = new ThesisGroqClient();
$result = $client->chat(
    [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => $userPrompt],
    ],
    temperature: 0.4,
    maxTokens: 1200
);

if (!$result['ok']) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

$text    = $result['text'];
$text    = preg_replace('/^```(?:json)?\s*/m', '', $text);
$text    = preg_replace('/```\s*$/m', '', $text);
$decoded = json_decode(trim($text), true);

if (!is_array($decoded) || empty($decoded['scores'])) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'AI returned unparseable JSON.']);
    exit;
}

// Merge in local heuristics
$decoded['word_count'] = $wordCount;
$decoded['has_colon']  = $hasColon;
$decoded['too_vague']  = $decoded['too_vague'] ?? $isVague;

echo json_encode([
    'ok'       => true,
    'title'    => $title,
    'analysis' => $decoded,
    'model'    => $result['model'],
]);
