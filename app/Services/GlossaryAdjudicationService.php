<?php

namespace App\Services;

use App\Models\GlossaryProposal;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GlossaryAdjudicationService
{
    public function assess(GlossaryProposal $proposal): array
    {
        $config = config('services.openrouter');
        if (empty($config['api_key'])) {
            throw new RuntimeException('OPENROUTER_API_KEY is not configured.');
        }

        $proposal->load(['entity', 'fact', 'observation.chapter']);
        $response = Http::baseUrl(rtrim($config['base_url'], '/'))
            ->withToken($config['api_key'])
            ->acceptJson()
            ->timeout($config['timeout'])
            ->retry($config['max_retries'], 1000, throw: false)
            ->post('/chat/completions', [
                'model' => $config['model'],
                'temperature' => 0,
                'max_tokens' => 800,
                'reasoning' => ['effort' => $config['reasoning_effort']],
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Assess a glossary conflict using only supplied evidence. Return JSON only: {"recommendation":"approve|reject|needs_review","explanation":"short evidence-based explanation"}. Never invent facts.'],
                    ['role' => 'user', 'content' => json_encode([
                        'entity' => $proposal->entity?->only(['canonical_name', 'type', 'aliases']),
                        'current_fact' => $proposal->fact?->only(['fact_key', 'value', 'authority']),
                        'proposed_fact' => $proposal->after_data,
                        'observation' => $proposal->observation?->only(['entity_name', 'value', 'quote', 'context']),
                        'chapter' => $proposal->observation?->chapter?->only(['chapter_number', 'title']),
                    ], JSON_THROW_ON_ERROR)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Glossary adjudication request failed: '.$response->status());
        }

        $decoded = json_decode((string) data_get($response->json(), 'choices.0.message.content'), true);
        if (!is_array($decoded) || !in_array($decoded['recommendation'] ?? null, ['approve', 'reject', 'needs_review'], true)) {
            throw new RuntimeException('Glossary adjudication returned invalid JSON.');
        }

        return [
            'recommendation' => $decoded['recommendation'],
            'explanation' => is_string($decoded['explanation'] ?? null) ? trim($decoded['explanation']) : 'No explanation returned.',
        ];
    }
}
