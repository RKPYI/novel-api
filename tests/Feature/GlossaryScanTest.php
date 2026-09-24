<?php

namespace Tests\Feature;

use App\Jobs\AdjudicateGlossaryProposal;
use App\Jobs\ExtractChapterGlossary;
use App\Models\Chapter;
use App\Models\GlossaryEntity;
use App\Models\GlossaryExtractionRun;
use App\Models\GlossaryFact;
use App\Models\GlossaryObservation;
use App\Models\GlossaryProposal;
use App\Models\User;
use App\Services\GlossaryAdjudicationService;
use App\Services\GlossaryReconciliationService;
use Illuminate\Support\Facades\Queue;
use Mockery;

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

    public function test_false_conflict_detection_ignores_different_fact_categories(): void
    {
        $service = new GlossaryReconciliationService();
        $fact = new GlossaryFact([
            'status' => 'active',
            'authority' => 'ai',
        ]);

        $method = new \ReflectionMethod($service, 'shouldTriggerConflictReview');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($service, $fact, ['value' => 'Spent the night at sea on a boat as punishment.'], 'Lives in a shabby yard with a coverage of only some twenty square meters, behind which is a bottomless cliff.'));
        $this->assertFalse($method->invoke($service, $fact, ['value' => 'Lives in a shabby yard with a coverage of only some twenty square meters, behind which is a bottomless cliff.'], 'Spent the night at sea on a boat as punishment.'));
    }

    public function test_auto_approves_chapter_scoped_correction(): void
    {
        $admin = $this->makeUserWithRole(User::ROLE_ADMIN);
        $novel = $this->makeNovelFor($admin);
        $entity = GlossaryEntity::create([
            'novel_id' => $novel->id,
            'type' => 'character',
            'canonical_name' => 'Han Fei',
            'normalized_name' => 'han fei',
            'aliases' => ['Han Fei'],
            'status' => 'active',
            'authority' => 'ai',
        ]);
        $fact = GlossaryFact::create([
            'novel_id' => $novel->id,
            'entity_id' => $entity->id,
            'fact_key' => 'residence',
            'value' => 'Lives in a shabby yard with a coverage of only some twenty square meters, behind which is a bottomless cliff.',
            'confidence' => 0.95,
            'status' => 'active',
            'authority' => 'ai',
        ]);
        $chapter = Chapter::create([
            'novel_id' => $novel->id,
            'title' => 'Chapter 10',
            'content' => 'Han Fei spent the night at sea on a boat as punishment.',
            'chapter_number' => 10,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);
        $observation = GlossaryObservation::create([
            'novel_id' => $novel->id,
            'chapter_id' => $chapter->id,
            'extraction_run_id' => GlossaryExtractionRun::create([
                'novel_id' => $novel->id,
                'chapter_id' => $chapter->id,
                'model' => 'test-model',
                'status' => 'completed',
                'attempts' => 1,
                'finished_at' => now(),
            ])->id,
            'entity_type' => 'character',
            'entity_name' => 'Han Fei',
            'normalized_name' => 'han fei',
            'fact_key' => 'residence',
            'value' => 'Spent the night at sea on a boat as punishment.',
            'quote' => 'Han Fei spent the night at sea on a boat as punishment.',
            'context' => 'Punishment scene',
            'confidence' => 0.9,
        ]);
        $proposal = GlossaryProposal::create([
            'novel_id' => $novel->id,
            'observation_id' => $observation->id,
            'entity_id' => $entity->id,
            'fact_id' => $fact->id,
            'operation' => 'update_fact',
            'status' => 'pending',
            'confidence' => 0.9,
            'before_data' => ['value' => $fact->value],
            'after_data' => ['value' => 'Spent the night at sea on a boat as punishment.', 'fact_key' => 'residence'],
            'reason' => 'A later observation conflicts with the current canonical fact.',
        ]);

        $service = Mockery::mock(GlossaryAdjudicationService::class);
        $service->shouldReceive('assess')->once()->andReturn([
            'recommendation' => 'approve',
            'classification' => 'chapter_scoped_correction',
            'explanation' => 'This is a later correction about punishment rather than a direct contradiction of the residence fact.',
        ]);

        (new AdjudicateGlossaryProposal($proposal->id))->handle($service);

        $fact->refresh();
        $this->assertSame('approved', $proposal->fresh()->status);
        $this->assertStringContainsString('sea', $fact->value);
    }

    public function test_auto_creates_new_fact_slot_for_separate_fact(): void
    {
        $admin = $this->makeUserWithRole(User::ROLE_ADMIN);
        $novel = $this->makeNovelFor($admin);
        $entity = GlossaryEntity::create([
            'novel_id' => $novel->id,
            'type' => 'character',
            'canonical_name' => 'Han Fei',
            'normalized_name' => 'han fei',
            'aliases' => ['Han Fei'],
            'status' => 'active',
            'authority' => 'ai',
        ]);
        $fact = GlossaryFact::create([
            'novel_id' => $novel->id,
            'entity_id' => $entity->id,
            'fact_key' => 'residence',
            'value' => 'Lives in a shabby yard with a coverage of only some twenty square meters.',
            'confidence' => 0.95,
            'status' => 'active',
            'authority' => 'ai',
        ]);
        $chapter = Chapter::create([
            'novel_id' => $novel->id,
            'title' => 'Chapter 11',
            'content' => 'He was punished by being sent to sea overnight.',
            'chapter_number' => 11,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);
        $observation = GlossaryObservation::create([
            'novel_id' => $novel->id,
            'chapter_id' => $chapter->id,
            'extraction_run_id' => GlossaryExtractionRun::create([
                'novel_id' => $novel->id,
                'chapter_id' => $chapter->id,
                'model' => 'test-model',
                'status' => 'completed',
                'attempts' => 1,
                'finished_at' => now(),
            ])->id,
            'entity_type' => 'character',
            'entity_name' => 'Han Fei',
            'normalized_name' => 'han fei',
            'fact_key' => 'residence',
            'value' => 'He was punished by being sent to sea overnight.',
            'quote' => 'He was punished by being sent to sea overnight.',
            'context' => 'Punishment',
            'confidence' => 0.9,
        ]);
        $proposal = GlossaryProposal::create([
            'novel_id' => $novel->id,
            'observation_id' => $observation->id,
            'entity_id' => $entity->id,
            'fact_id' => $fact->id,
            'operation' => 'update_fact',
            'status' => 'pending',
            'confidence' => 0.9,
            'before_data' => ['value' => $fact->value],
            'after_data' => ['value' => 'He was punished by being sent to sea overnight.', 'fact_key' => 'residence'],
            'reason' => 'A later observation conflicts with the current canonical fact.',
        ]);

        $service = Mockery::mock(GlossaryAdjudicationService::class);
        $service->shouldReceive('assess')->once()->andReturn([
            'recommendation' => 'approve',
            'classification' => 'new_fact_slot',
            'explanation' => 'This is a separate punishment fact rather than the same residence fact.',
        ]);

        (new AdjudicateGlossaryProposal($proposal->id))->handle($service);

        $this->assertSame('approved', $proposal->fresh()->status);
        $this->assertSame(2, GlossaryFact::where('entity_id', $entity->id)->count());
        $this->assertDatabaseHas('glossary_facts', ['entity_id' => $entity->id, 'value' => 'He was punished by being sent to sea overnight.']);
    }
}

