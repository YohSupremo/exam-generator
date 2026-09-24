<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');

$quotaFile = DATA_DIR . '/ai_quota.json';
$quota = [];
if (is_file($quotaFile)) {
    $quota = json_decode((string)file_get_contents($quotaFile), true) ?: [];
}



if (empty($quota)) {
    $quota = [
        'model' => defined('OPENCODE_MODEL') ? OPENCODE_MODEL : 'groq/openai/gpt-oss-20b',
        'provider' => (defined('GROQ_API_KEY') && GROQ_API_KEY !== '') ? 'Groq LPU Engine' : 'Local Offline Engine',
        'ratelimit' => [
            'limit_tokens' => 8000,
            'remaining_tokens' => 8000,
            'reset_tokens' => '0s',
            'limit_requests' => 1000,
            'remaining_requests' => 1000,
            'reset_requests' => '0s',
        ],
        'status' => 'Active',
        'updated_at' => date('c'),
    ];
}

$quota['lifetime_tokens'] = exam_lifetime_tokens();

if (ob_get_level() > 0) {
    ob_end_clean();
}
echo json_encode($quota, JSON_UNESCAPED_UNICODE);
exit;
