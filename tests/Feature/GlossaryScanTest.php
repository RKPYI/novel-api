<?php

namespace Tests\Feature;

use App\Jobs\ExtractChapterGlossary;
use App\Models\Chapter;
use App\Models\GlossaryExtractionRun;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

class GlossaryScanTest extends FeatureTestCase
{
    public function test_resume_scan_only_queues_chapters_without_a_successful_latest_run(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole(User::ROLE_ADMIN);
        $novel = $this->makeNovelFor($admin);
        $chapters = collect(range(1, 3))->map(
            fn (int $number): Chapter => Chapter::create([
                'novel_id' => $novel->id,
                'title' => 'Chapter '.$number,
                'content' => 'Published content '.$number,
                'chapter_number' => $number,
                'status' => Chapter::STATUS_APPROVED,
                'published_at' => now(),
            ])
        );

        GlossaryExtractionRun::create([
            'novel_id' => $novel->id,
            'chapter_id' => $chapters[0]->id,
            'status' => 'completed',
            'model' => 'test-model',
            'attempts' => 1,
            'finished_at' => now(),
        ]);
        GlossaryExtractionRun::create([
            'novel_id' => $novel->id,
            'chapter_id' => $chapters[1]->id,
            'status' => 'failed',
            'model' => 'test-model',
            'attempts' => 1,
            'finished_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/novels/{$novel->slug}/glossary/resume");

        $response->assertAccepted()
            ->assertJsonPath('queued_chapters', 2)
            ->assertJsonPath('skipped_chapters', 1)
            ->assertJsonPath('mode', 'resume');

        Queue::assertPushed(ExtractChapterGlossary::class, 2);
        Queue::assertPushed(ExtractChapterGlossary::class, fn (ExtractChapterGlossary $job): bool => $job->chapterId === $chapters[1]->id);
        Queue::assertPushed(ExtractChapterGlossary::class, fn (ExtractChapterGlossary $job): bool => $job->chapterId === $chapters[2]->id);
    }
}
