<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\ChapterReview;
use App\Models\Novel;
use App\Models\ReadingProgress;
use App\Models\User;
use App\Models\Volume;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VolumeContextEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recently_updated_includes_volume_context(): void
    {
        Cache::flush();

        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author, ['uses_volumes' => true]);

        $volume = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 2,
            'title' => 'Volume 2',
        ]);

        Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volume->id,
            'title' => 'Latest Chapter',
            'content' => 'content',
            'chapter_number' => 3,
            'word_count' => 10,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/novels/recently-updated?limit=10');

        $response
            ->assertOk()
            ->assertJsonPath('novels.0.id', $novel->id)
            ->assertJsonPath('novels.0.uses_volumes', true)
            ->assertJsonPath('novels.0.latest_chapter_number', 3)
            ->assertJsonPath('novels.0.latest_chapter_volume_number', 2);
    }

    public function test_recently_updated_returns_null_volume_for_flat_novels(): void
    {
        Cache::flush();

        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author, ['uses_volumes' => false]);

        Chapter::create([
            'novel_id' => $novel->id,
            'title' => 'Flat Chapter',
            'content' => 'content',
            'chapter_number' => 5,
            'word_count' => 10,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/novels/recently-updated?limit=10');

        $response
            ->assertOk()
            ->assertJsonPath('novels.0.id', $novel->id)
            ->assertJsonPath('novels.0.uses_volumes', false)
            ->assertJsonPath('novels.0.latest_chapter_number', 5)
            ->assertJsonPath('novels.0.latest_chapter_volume_number', null);
    }

    public function test_review_history_includes_volume_context(): void
    {
        $author = $this->makeAuthor();
        $editor = $this->makeEditor();
        $novel = $this->makeNovelFor($author, ['uses_volumes' => true]);

        $volume = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 1,
            'title' => 'Volume 1',
        ]);

        $chapter = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volume->id,
            'title' => 'Reviewed Chapter',
            'content' => 'content',
            'chapter_number' => 4,
            'word_count' => 10,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        ChapterReview::create([
            'chapter_id' => $chapter->id,
            'editor_id' => $editor->id,
            'action' => ChapterReview::ACTION_APPROVED,
            'notes' => null,
        ]);

        Sanctum::actingAs($editor);

        $response = $this->getJson('/api/editor/review-history');

        $response
            ->assertOk()
            ->assertJsonPath('reviews.data.0.chapter.chapter_number', 4)
            ->assertJsonPath('reviews.data.0.chapter.volume_number', 1)
            ->assertJsonPath('reviews.data.0.chapter.novel.uses_volumes', true)
            ->assertJsonMissingPath('reviews.data.0.chapter.volume');
    }

    public function test_user_reading_progress_includes_uses_volumes_per_entry(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
            'provider' => 'email',
        ]);
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author, ['uses_volumes' => true, 'total_chapters' => 1]);

        $volume = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => 3,
            'title' => 'Volume 3',
        ]);

        $chapter = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volume->id,
            'title' => 'Progress Chapter',
            'content' => 'content',
            'chapter_number' => 1,
            'word_count' => 10,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        ReadingProgress::create([
            'user_id' => $user->id,
            'novel_id' => $novel->id,
            'chapter_id' => $chapter->id,
            'last_read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reading-progress/user');

        $response
            ->assertOk()
            ->assertJsonPath('reading_progress.0.uses_volumes', true)
            ->assertJsonPath('reading_progress.0.novel.uses_volumes', true)
            ->assertJsonPath('reading_progress.0.current_chapter.volume_number', 3);
    }

    private function makeAuthor(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_AUTHOR,
            'provider' => 'email',
        ], $attributes));
    }

    private function makeEditor(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_EDITOR,
            'provider' => 'email',
        ], $attributes));
    }

    private function makeNovelFor(User $user, array $attributes = []): Novel
    {
        return Novel::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Novel ' . fake()->unique()->words(3, true),
            'author' => $user->name,
            'description' => 'Novel for volume context endpoint tests',
            'status' => 'ongoing',
        ], $attributes));
    }
}
