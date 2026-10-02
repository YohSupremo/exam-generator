<?php
declare(strict_types=1);

// Load shared config for API keys
require_once dirname(__DIR__, 2) . '/lib/config.php';

class ThesisGroqClient
{
    private const BASE_URL  = 'https://api.groq.com/openai/v1/chat/completions';
    // Use the same model pool as the main app (Groq account-specific)
    private const DEFAULT_MODEL = 'openai/gpt-oss-20b';
    private const FALLBACK_MODELS = [
        'openai/gpt-oss-120b',
        'qwen/qwen3.8-27b',
        'llama-3.3-70b-versatile',
        'llama3-groq-70b-8192-tool-use-preview',
    ];
    private const TIMEOUT = 45;

    private string $apiKey;
    private string $model;

    public function __construct(string $model = self::DEFAULT_MODEL)
    {
        $this->apiKey = GROQ_API_KEY;
        // If caller passed the old hardcoded llama name, remap to the working default
        $this->model  = ($model === 'llama-3.1-8b-instant') ? self::DEFAULT_MODEL : $model;
    }

    /**
     * Send a chat completion request and return the response text.
     */
    public function chat(array $messages, float $temperature = 0.85, int $maxTokens = 2048): array
    {
        if (empty($this->apiKey)) {
            return ['ok' => false, 'text' => '', 'model' => $this->model, 'error' => 'GROQ_API_KEY is not configured.'];
        }

        $modelsToTry = array_unique([$this->model, ...self::FALLBACK_MODELS]);

        foreach ($modelsToTry as $model) {
            $result = $this->doRequest($model, $messages, $temperature, $maxTokens);
            if ($result['ok']) {
                return $result;
            }
            // Retry on: rate limit (429), overload (503), model not found (404)
            if (isset($result['http_code']) && in_array($result['http_code'], [404, 429, 503], true)) {
                continue;
            }
            return $result;
        }

        return ['ok' => false, 'text' => '', 'model' => $this->model, 'error' => 'All models exhausted. Please try again shortly.'];
    }

    private function doRequest(string $model, array $messages, float $temperature, int $maxTokens): array
    {
        $payload = json_encode([
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
        ]);

        $ch = curl_init(self::BASE_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['ok' => false, 'text' => '', 'model' => $model, 'error' => 'cURL error: ' . $curlErr];
        }

        $decoded = json_decode($raw, true);

        if ($httpCode !== 200 || !isset($decoded['choices'][0]['message']['content'])) {
            $errMsg = $decoded['error']['message'] ?? ("HTTP $httpCode: " . substr($raw, 0, 200));
            return ['ok' => false, 'text' => '', 'model' => $model, 'error' => $errMsg, 'http_code' => $httpCode];
        }

        return [
            'ok'    => true,
            'text'  => trim($decoded['choices'][0]['message']['content']),
            'model' => $model,
        ];
    }
}
