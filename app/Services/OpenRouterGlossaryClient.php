<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterGlossaryClient
{
    public function answer(string $question, string $context, string $boundaryLabel, array $history = []): array
    {
        $config = config('services.openrouter');

        if (empty($config['api_key'])) {
            throw new RuntimeException('OPENROUTER_API_KEY is not configured.');
        }

        $messages = [
            [
                'role' => 'system',
                'content' => <<<PROMPT
You answer questions about a novel using only the supplied evidence.
The reader has reached {$boundaryLabel}. Never reveal information from later chapters,
infer hidden identities, future events, endings, or answer from general knowledge.
If the evidence does not support a safe answer, return availability "not_available" or
"insufficient_evidence" and do not explain what the hidden answer is.
Return JSON only:
{"answer":"string","availability":"answered|not_available|insufficient_evidence","citations":[{"chapter_id":0,"chapter_number":0,"quote":"exact quote from supplied evidence"}]}
Every citation quote must be copied exactly from the supplied evidence.
PROMPT,
            ],
        ];

        foreach (array_slice($history, -6) as $message) {
            if (is_array($message) && in_array($message['role'] ?? null, ['user', 'assistant'], true) && is_string($message['content'] ?? null)) {
                $messages[] = ['role' => $message['role'], 'content' => mb_substr($message['content'], 0, 2000)];
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => "Evidence:\n{$context}\n\nQuestion:\n".mb_substr($question, 0, 1000),
        ];

        $request = Http::baseUrl(rtrim($config['base_url'], '/'))
            ->withToken($config['api_key'])
            ->acceptJson()
            ->timeout($config['timeout'])
            ->retry($config['max_retries'], 1000, throw: false);

        $response = $request->post('/chat/completions', [
                'model' => $config['model'],
                'temperature' => 0.05,
                'max_tokens' => min($config['max_tokens'], 1800),
                'reasoning' => ['effort' => $config['reasoning_effort']],
                'response_format' => ['type' => 'json_object'],
                'messages' => $messages,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenRouter request failed: '.$response->status());
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        $decoded = json_decode((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim((string) $content)), true);

        if (!is_array($decoded)) {
            throw new RuntimeException('OpenRouter returned invalid companion JSON.');
        }

        return [
            'answer' => is_string($decoded['answer'] ?? null) ? trim($decoded['answer']) : '',
            'availability' => in_array($decoded['availability'] ?? null, ['answered', 'not_available', 'insufficient_evidence'], true)
                ? $decoded['availability']
                : 'insufficient_evidence',
            'citations' => is_array($decoded['citations'] ?? null) ? $decoded['citations'] : [],
        ];
    }

    public function extract(string $title, string $content, string $chapterLabel): array
    {
        $config = config('services.openrouter');

        if (empty($config['api_key'])) {
            throw new RuntimeException('OPENROUTER_API_KEY is not configured.');
        }

        $prompt = <<<PROMPT
You extract factual, spoiler-sensitive glossary data from a novel chapter.
Return JSON only with this exact top-level shape:
{"entries":[{"type":"character|item|place|faction|creature|skill|system|event|rule|concept|other","name":"string","aliases":["string"],"facts":[{"key":"string","value":"string","confidence":0.0,"quote":"exact contiguous quote from chapter","context":"short context"}]}]}

Rules:
- Include only information explicitly supported by the chapter text.
- Do not infer motives, identities, future events, relationships, or powers.
- Prefer important durable facts over scene narration and fluff.
- Keep facts atomic. Update-worthy changes should use stable keys such as status, location, ability, relationship, rank, appearance, or rule.
- Do not merge distinct entities. Do not generalize levels/ranks unless the chapter explicitly defines the grouping.
- Every quote must be an exact contiguous substring of the supplied chapter.
- Confidence must reflect evidence quality, not certainty about future story developments.

Novel: {$title}
Chapter: {$chapterLabel}
Chapter text:
{$content}
PROMPT;

        $request = Http::baseUrl(rtrim($config['base_url'], '/'))
            ->withToken($config['api_key'])
            ->acceptJson()
            ->timeout($config['timeout'])
            ->retry($config['max_retries'], 1000, throw: false);

        $response = $request->post('/chat/completions', [
                'model' => $config['model'],
                'temperature' => $config['temperature'],
                'max_tokens' => $config['max_tokens'],
                'reasoning' => ['effort' => $config['reasoning_effort']],
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a precise literary information extraction engine.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenRouter request failed: '.$response->status().' '.$response->body());
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenRouter returned an empty extraction response.');
        }

        $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
        $decoded = json_decode($content, true);

        if (!is_array($decoded) || !isset($decoded['entries']) || !is_array($decoded['entries'])) {
            throw new RuntimeException('OpenRouter returned invalid glossary JSON.');
        }

        return $this->validate($decoded, $content);
    }

    private function validate(array $payload, string $raw): array
    {
        $entries = [];

        foreach ($payload['entries'] as $entry) {
            if (!is_array($entry) || !is_string($entry['name'] ?? null) || !is_array($entry['facts'] ?? null)) {
                continue;
            }

            $facts = [];
            foreach ($entry['facts'] as $fact) {
                if (!is_array($fact)) {
                    continue;
                }

                $quote = $fact['quote'] ?? null;
                if (!is_string($fact['key'] ?? null) || !is_string($fact['value'] ?? null) || !is_string($quote)) {
                    continue;
                }

                $confidence = filter_var($fact['confidence'] ?? null, FILTER_VALIDATE_FLOAT);
                if ($confidence === false || $confidence < 0 || $confidence > 1 || trim($quote) === '') {
                    continue;
                }

                $facts[] = [
                    'key' => trim($fact['key']),
                    'value' => trim($fact['value']),
                    'confidence' => (float) $confidence,
                    'quote' => trim($quote),
                    'context' => is_string($fact['context'] ?? null) ? trim($fact['context']) : null,
                ];
            }

            if ($facts !== []) {
                $entries[] = [
                    'type' => is_string($entry['type'] ?? null) ? trim($entry['type']) : 'other',
                    'name' => trim($entry['name']),
                    'aliases' => array_values(array_filter($entry['aliases'] ?? [], 'is_string')),
                    'facts' => $facts,
                ];
            }
        }

        return ['entries' => $entries, 'raw_size' => strlen($raw)];
    }
}
