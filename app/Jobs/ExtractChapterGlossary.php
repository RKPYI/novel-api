<?php

namespace App\Jobs;

use App\Models\Chapter;
use App\Services\GlossaryExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExtractChapterGlossary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 420;

    public function __construct(public readonly int $chapterId)
    {
    }

    public function handle(GlossaryExtractionService $service): void
    {
        $chapter = Chapter::with(['novel', 'volume'])->findOrFail($this->chapterId);
        $service->process($chapter);
    }

    public function failed(?Throwable $exception): void
    {
        \App\Models\GlossaryExtractionRun::query()
            ->where('chapter_id', $this->chapterId)
            ->where('status', 'running')
            ->update([
                'status' => 'failed',
                'error' => $exception?->getMessage() ?? 'Glossary extraction job failed.',
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
