<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Novel;
use App\Models\User;
use App\Models\Volume;
use App\Services\VolumeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VolumeModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabling_volumes_assigns_existing_chapters_to_volume_one(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author);

        Chapter::create([
            'novel_id' => $novel->id,
            'title' => 'Chapter 1',
            'content' => 'content',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        app(VolumeService::class)->enableVolumes($novel->fresh());

        $novel->refresh();
        $this->assertTrue($novel->uses_volumes);
        $this->assertDatabaseHas('volumes', ['novel_id' => $novel->id, 'volume_number' => 1]);
        $this->assertDatabaseHas('chapters', [
            'novel_id' => $novel->id,
            'chapter_number' => 1,
            'volume_id' => Volume::where('novel_id', $novel->id)->value('id'),
        ]);
    }

    public function test_disabling_volumes_renumbers_chapters_globally(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author, ['uses_volumes' => true]);

        $volumeOne = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 1,
            'title' => 'Volume 1',
        ]);

        $volumeTwo = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 2,
            'title' => 'Volume 2',
        ]);

        $chapterOne = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeOne->id,
            'title' => 'V1C1',
            'content' => 'a',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        $chapterTwo = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeTwo->id,
            'title' => 'V2C1',
            'content' => 'b',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        app(VolumeService::class)->disableVolumes($novel->fresh());

        $novel->refresh();
        $chapterOne->refresh();
        $chapterTwo->refresh();

        $this->assertFalse($novel->uses_volumes);
        $this->assertNull($chapterOne->volume_id);
        $this->assertNull($chapterTwo->volume_id);
        $this->assertSame(1, $chapterOne->chapter_number);
        $this->assertSame(2, $chapterTwo->chapter_number);
        $this->assertDatabaseCount('volumes', 0);
    }

    public function test_same_chapter_number_can_exist_in_different_volumes(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author, ['uses_volumes' => true]);

        $volumeOne = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 1,
            'title' => 'Volume 1',
        ]);

        $volumeTwo = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 2,
            'title' => 'Volume 2',
        ]);

        Sanctum::actingAs($author);

        $first = $this->postJson("/api/novels/{$novel->slug}/chapters", [
            'title' => 'Vol 1 Ch 1',
            'content' => 'content one',
            'chapter_number' => 1,
            'volume_id' => $volumeOne->id,
            'save_as_draft' => true,
        ]);

        $second = $this->postJson("/api/novels/{$novel->slug}/chapters", [
            'title' => 'Vol 2 Ch 1',
            'content' => 'content two',
            'chapter_number' => 1,
            'volume_id' => $volumeTwo->id,
            'save_as_draft' => true,
        ]);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertDatabaseCount('chapters', 2);
    }

    public function test_volume_chapter_show_and_navigation_across_volume_boundary(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author, ['uses_volumes' => true]);

        $volumeOne = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 1,
            'title' => 'Volume 1',
        ]);

        $volumeTwo = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 2,
            'title' => 'Volume 2',
        ]);

        Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeOne->id,
            'title' => 'End of Vol 1',
            'content' => 'a',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeTwo->id,
            'title' => 'Start of Vol 2',
            'content' => 'b',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        $response = $this->getJson("/api/novels/{$novel->slug}/volumes/1/chapters/1");
        $response->assertOk()
            ->assertJsonPath('chapter.next_chapter', 1)
            ->assertJsonPath('chapter.next_volume', 2)
            ->assertJsonPath('chapter.previous_chapter', null);

        $response = $this->getJson("/api/novels/{$novel->slug}/volumes/2/chapters/1");
        $response->assertOk()
            ->assertJsonPath('chapter.previous_chapter', 1)
            ->assertJsonPath('chapter.previous_volume', 1)
            ->assertJsonPath('chapter.next_chapter', null);
    }

    public function test_public_chapter_list_groups_by_volume(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author, ['uses_volumes' => true]);

        $volume = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 1,
            'title' => 'Volume 1',
        ]);

        Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volume->id,
            'title' => 'Published',
            'content' => 'content',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        $response = $this->getJson("/api/novels/{$novel->slug}/chapters");
        $response->assertOk()
            ->assertJsonPath('uses_volumes', true)
            ->assertJsonCount(1, 'volumes')
            ->assertJsonPath('volumes.0.volume_number', 1);
    }

    public function test_flat_novel_chapter_list_remains_backward_compatible(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author);

        Chapter::create([
            'novel_id' => $novel->id,
            'title' => 'Flat Chapter',
            'content' => 'content',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        $response = $this->getJson("/api/novels/{$novel->slug}/chapters");
        $response->assertOk()
            ->assertJsonPath('uses_volumes', false)
            ->assertJsonCount(1, 'chapters')
            ->assertJsonMissingPath('volumes');
    }

    public function test_move_chapter_between_volumes_renumbers_both_volumes(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author, ['uses_volumes' => true]);

        $volumeOne = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 1,
            'title' => 'Volume 1',
        ]);

        $volumeTwo = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 2,
            'title' => 'Volume 2',
        ]);

        $chapterOne = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeOne->id,
            'title' => 'V1C1',
            'content' => 'a',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        $chapterTwo = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeOne->id,
            'title' => 'V1C2',
            'content' => 'b',
            'chapter_number' => 2,
            'word_count' => 1,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        $chapterThree = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeOne->id,
            'title' => 'V1C3',
            'content' => 'c',
            'chapter_number' => 3,
            'word_count' => 1,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeTwo->id,
            'title' => 'V2C1',
            'content' => 'd',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($author);

        $response = $this->postJson("/api/novels/{$novel->slug}/chapters/{$chapterTwo->id}/move-volume", [
            'volume_id' => $volumeTwo->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('chapter.volume_number', 2)
            ->assertJsonPath('chapter.chapter_number', 2);

        $chapterOne->refresh();
        $chapterThree->refresh();
        $chapterTwo->refresh();

        $this->assertSame(1, $chapterOne->chapter_number);
        $this->assertSame(2, $chapterThree->chapter_number);
        $this->assertSame($volumeOne->id, $chapterOne->volume_id);
        $this->assertSame($volumeOne->id, $chapterThree->volume_id);
        $this->assertSame($volumeTwo->id, $chapterTwo->volume_id);
        $this->assertSame(2, $chapterTwo->chapter_number);
    }

    public function test_move_chapter_to_specific_position_in_target_volume(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author, ['uses_volumes' => true]);

        $volumeOne = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 1,
            'title' => 'Volume 1',
        ]);

        $volumeTwo = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 2,
            'title' => 'Volume 2',
        ]);

        $movingChapter = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeOne->id,
            'title' => 'Moving',
            'content' => 'move me',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_DRAFT,
        ]);

        $targetA = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeTwo->id,
            'title' => 'V2A',
            'content' => 'a',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_DRAFT,
        ]);

        $targetB = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeTwo->id,
            'title' => 'V2B',
            'content' => 'b',
            'chapter_number' => 2,
            'word_count' => 1,
            'status' => Chapter::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($author);

        $response = $this->postJson("/api/novels/{$novel->slug}/chapters/{$movingChapter->id}/move-volume", [
            'volume_id' => $volumeTwo->id,
            'chapter_number' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('chapter.chapter_number', 1);

        $movingChapter->refresh();
        $targetA->refresh();
        $targetB->refresh();

        $this->assertSame($volumeTwo->id, $movingChapter->volume_id);
        $this->assertSame(1, $movingChapter->chapter_number);
        $this->assertSame(2, $targetA->chapter_number);
        $this->assertSame(3, $targetB->chapter_number);
    }

    public function test_bulk_move_chapters_between_volumes_preserves_order(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author, ['uses_volumes' => true]);

        $volumeOne = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 1,
            'title' => 'Volume 1',
        ]);

        $volumeTwo = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 2,
            'title' => 'Volume 2',
        ]);

        $chapterOne = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeOne->id,
            'title' => 'V1C1',
            'content' => 'a',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_DRAFT,
        ]);

        $chapterTwo = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeOne->id,
            'title' => 'V1C2',
            'content' => 'b',
            'chapter_number' => 2,
            'word_count' => 1,
            'status' => Chapter::STATUS_DRAFT,
        ]);

        $chapterThree = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeOne->id,
            'title' => 'V1C3',
            'content' => 'c',
            'chapter_number' => 3,
            'word_count' => 1,
            'status' => Chapter::STATUS_DRAFT,
        ]);

        $existingTarget = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volumeTwo->id,
            'title' => 'V2C1',
            'content' => 'd',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($author);

        $response = $this->postJson("/api/novels/{$novel->slug}/chapters/bulk-move-volume", [
            'chapter_ids' => [$chapterTwo->id, $chapterThree->id],
            'volume_id' => $volumeTwo->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('moved_count', 2);

        $chapterOne->refresh();
        $chapterTwo->refresh();
        $chapterThree->refresh();
        $existingTarget->refresh();

        $this->assertSame($volumeOne->id, $chapterOne->volume_id);
        $this->assertSame(1, $chapterOne->chapter_number);
        $this->assertSame($volumeTwo->id, $existingTarget->volume_id);
        $this->assertSame(1, $existingTarget->chapter_number);
        $this->assertSame($volumeTwo->id, $chapterTwo->volume_id);
        $this->assertSame(2, $chapterTwo->chapter_number);
        $this->assertSame($volumeTwo->id, $chapterThree->volume_id);
        $this->assertSame(3, $chapterThree->chapter_number);
    }

    private function makeAuthor(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_AUTHOR,
            'provider' => 'email',
        ], $attributes));
    }

    private function makeNovelFor(User $user, array $attributes = []): Novel
    {
        return Novel::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Novel ' . fake()->unique()->words(3, true),
            'author' => $user->name,
            'description' => 'Novel for volume tests',
            'status' => 'ongoing',
        ], $attributes));
    }
}
