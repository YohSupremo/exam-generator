<?php
declare(strict_types=1);

/**
 * thesis/api/problems.php
 * Returns a list of currently-relevant societal/academic research problems
 * filtered by location (country/city) and field of study.
 *
 * POST /thesis/api/problems.php
 * Body (JSON): { location: string, field: string, mode: "ai"|"news" }
 * Response: { ok: true, problems: [ { title, description, keywords[] } ] }
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

$location = trim($body['location'] ?? 'Philippines');
$field    = trim($body['field'] ?? 'Computer Science');
$mode     = trim($body['mode'] ?? 'ai'); // 'ai' or 'news'

if ($location === '') {
    $location = 'Philippines';
}
if ($field === '') {
    $field = 'Computer Science';
}

// ------------------------------------------------------------------
// Optional: Live news via GNews free tier (requires GNEWS_API_KEY in .env)
// ------------------------------------------------------------------
$newsContext = '';
if ($mode === 'news' || $mode === 'both') {
    $gnewsKey = getenv('GNEWS_API_KEY') ?: '';
    if ($gnewsKey !== '') {
        $q   = urlencode("$field research problem $location");
        $url = "https://gnews.io/api/v4/search?q=$q&lang=en&max=5&token=$gnewsKey";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $newsRaw = curl_exec($ch);
        curl_close($ch);
        $newsData = json_decode($newsRaw, true);
        if (!empty($newsData['articles'])) {
            $headlines = array_slice($newsData['articles'], 0, 5);
            $newsContext = "\n\nRecent news headlines for context:\n";
            foreach ($headlines as $article) {
                $newsContext .= '- ' . $article['title'] . "\n";
            }
        }
    }
}

// ------------------------------------------------------------------
// Build the AI prompt
// ------------------------------------------------------------------
$systemPrompt = <<<SYSTEM
You are a senior academic research consultant specializing in identifying high-impact, researchable thesis topics. You have deep knowledge of societal challenges, government data, academic literature, and current events across all countries and major cities.

When given a location and academic field, you identify real, current, and researchable problems that students in that location actually face and can meaningfully study.

Always respond in valid JSON only. No markdown. No explanation outside the JSON.
SYSTEM;

$userPrompt = <<<USER
Identify 6 current, specific, and researchable societal or academic problems in "{$location}" related to the field of "{$field}".{$newsContext}

For each problem, provide:
1. A clear problem title (not a thesis title yet—just the problem name)
2. A 2-sentence description of why this is a significant current issue in {$location}
3. 3-5 relevant keywords/tags

Return ONLY a JSON object in this exact format:
{
  "location": "{$location}",
  "field": "{$field}",
  "problems": [
    {
      "id": 1,
      "title": "Problem Title Here",
      "description": "Two-sentence description of the problem and its significance in {$location}.",
      "keywords": ["keyword1", "keyword2", "keyword3"],
      "severity": "high|medium",
      "trend": "rising|stable"
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
    temperature: 0.75,
    maxTokens: 1800
);

if (!$result['ok']) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

// Parse the JSON response
$text    = $result['text'];
// Strip any accidental markdown fences
$text    = preg_replace('/^```(?:json)?\s*/m', '', $text);
$text    = preg_replace('/```\s*$/m', '', $text);
$decoded = json_decode(trim($text), true);

if (!is_array($decoded) || empty($decoded['problems'])) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'AI returned unparseable JSON. Raw: ' . substr($text, 0, 300)]);
    exit;
}

echo json_encode([
    'ok'       => true,
    'location' => $decoded['location'] ?? $location,
    'field'    => $decoded['field'] ?? $field,
    'problems' => $decoded['problems'],
    'model'    => $result['model'],
    'mode'     => $mode,
]);
