<?php

namespace Tests\Unit;

use App\Services\OpenRouterGlossaryClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterGlossaryClientTest extends TestCase
{
    public function test_it_accepts_structured_entries_and_discards_invalid_facts(): void
    {
        config()->set('services.openrouter.api_key', 'test-key');
        config()->set('services.openrouter.base_url', 'https://openrouter.test');

        Http::fake([
            'https://openrouter.test/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'entries' => [[
                                'type' => 'character',
                                'name' => 'Mira',
                                'aliases' => ['The Scout'],
                                'facts' => [
                                    [
                                        'key' => 'status',
                                        'value' => 'Alive',
                                        'confidence' => 0.96,
                                        'quote' => 'Mira was alive.',
                                        'context' => 'The narrator confirms her status.',
                                    ],
                                    [
                                        'key' => 'unsupported',
                                        'value' => 'ignored',
                                        'confidence' => 2,
                                        'quote' => 'invalid',
                                    ],
                                ],
                            ]],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $result = app(OpenRouterGlossaryClient::class)->extract(
            'Test Novel',
            'Mira was alive.',
            'Chapter 1'
        );

        $this->assertSame('Mira', $result['entries'][0]['name']);
        $this->assertCount(1, $result['entries'][0]['facts']);
        $this->assertSame('status', $result['entries'][0]['facts'][0]['key']);
    }
}
