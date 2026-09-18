<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\GlossaryExtractionRun;
use RuntimeException;

class GlossaryExtractionService
{
    public function __construct(
        private readonly OpenRouterGlossaryClient $client,
        private readonly GlossaryReconciliationService $reconciliation,
    )
    {
    }

    public function process(Chapter $chapter): GlossaryExtractionRun
    {
        if (!$chapter->isPublished()) {
            throw new RuntimeException('Only published chapters can be scanned.');
        }

        $run = GlossaryExtractionRun::create([
            'novel_id' => $chapter->novel_id,
            'chapter_id' => $chapter->id,
            'model' => config('services.openrouter.model'),
            'status' => 'running',
            'started_at' => now(),
            'attempts' => 1,
        ]);

        try {
            $chapterLabel = $chapter->volume
                ? sprintf('Volume %d, Chapter %d', $chapter->volume->volume_number, $chapter->chapter_number)
                : 'Chapter '.$chapter->chapter_number;
            $payload = $this->client->extract($chapter->novel->title, $chapter->content, $chapterLabel);

            $this->reconciliation->reconcile($chapter, $run, $payload);

            $run->update([
                'status' => 'completed',
                'metrics' => ['entries' => count($payload['entries']), 'raw_size' => $payload['raw_size']],
                'finished_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
            throw $exception;
        }

        return $run->fresh();
    }

}
