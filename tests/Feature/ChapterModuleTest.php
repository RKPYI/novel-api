<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Novel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChapterModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_create_chapter_as_draft(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author);

        Sanctum::actingAs($author);

        $response = $this->postJson("/api/novels/{$novel->slug}/chapters", [
            'title' => 'Chapter One',
            'content' => 'This is a draft chapter content.',
            'chapter_number' => 1,
            'save_as_draft' => true,
        ]);

        $response->assertCreated()->assertJsonPath('chapter.status', Chapter::STATUS_DRAFT);

        $this->assertDatabaseHas('chapters', [
            'novel_id' => $novel->id,
            'chapter_number' => 1,
            'status' => Chapter::STATUS_DRAFT,
        ]);
    }

    public function test_create_chapter_rejects_chapter_number_below_one(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author);

        Sanctum::actingAs($author);

        $response = $this->postJson("/api/novels/{$novel->slug}/chapters", [
            'title' => 'Invalid Number',
            'content' => 'content',
            'chapter_number' => 0,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['chapter_number']);
    }

    public function test_duplicate_chapter_number_conflict_under_parallel_like_requests(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author);

        Sanctum::actingAs($author);

        $payload = [
            'title' => 'Race Chapter',
            'content' => 'Same chapter number request',
            'chapter_number' => 1,
            'save_as_draft' => true,
        ];

        $first = $this->postJson("/api/novels/{$novel->slug}/chapters", $payload);
        $second = $this->postJson("/api/novels/{$novel->slug}/chapters", $payload);

        $first->assertCreated();
        $second->assertStatus(409)->assertJsonPath('message', 'Chapter number already exists');

        $this->assertDatabaseCount('chapters', 1);
    }

    public function test_public_show_returns_404_for_unpublished_chapter(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author);

        Chapter::create([
            'novel_id' => $novel->id,
            'title' => 'Hidden Draft',
            'content' => 'Draft content',
            'chapter_number' => 1,
            'word_count' => 2,
            'status' => Chapter::STATUS_DRAFT,
            'published_at' => null,
        ]);

        $response = $this->getJson("/api/novels/{$novel->slug}/chapters/1");

        $response->assertNotFound();
    }

    public function test_public_show_increments_views_across_multiple_reads(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author);

        $chapter = Chapter::create([
            'novel_id' => $novel->id,
            'title' => 'Published',
            'content' => 'Published content',
            'chapter_number' => 1,
            'word_count' => 2,
            'views' => 0,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        $this->getJson("/api/novels/{$novel->slug}/chapters/1")->assertOk();
        $this->getJson("/api/novels/{$novel->slug}/chapters/1")->assertOk();

        $this->assertSame(2, $chapter->fresh()->views);
    }

    public function test_author_update_on_published_chapter_creates_pending_update(): void
    {
        $author = $this->makeAuthor();
        $novel = $this->makeNovelFor($author);

        $chapter = Chapter::create([
            'novel_id' => $novel->id,
            'title' => 'Initial Title',
            'content' => 'Original content body',
            'chapter_number' => 1,
            'word_count' => 3,
            'status' => Chapter::STATUS_APPROVED,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($author);

        $response = $this->putJson("/api/novels/{$novel->slug}/chapters/{$chapter->id}", [
            'title' => 'Updated Title',
            'content' => 'Revised content requiring review',
        ]);

        $response->assertOk();

        $chapter->refresh();

        $this->assertSame(Chapter::STATUS_PENDING_UPDATE, $chapter->status);
        $this->assertSame('Updated Title', $chapter->pending_title);
        $this->assertSame('Revised content requiring review', $chapter->pending_content);
        $this->assertSame('Initial Title', $chapter->title);
    }

    public function test_bulk_delete_rejects_chapters_not_belonging_to_target_novel(): void
    {
        $author = $this->makeAuthor();
        $novelA = $this->makeNovelFor($author, ['title' => 'Novel A']);
        $novelB = $this->makeNovelFor($author, ['title' => 'Novel B']);

        $chapterA = Chapter::create([
            'novel_id' => $novelA->id,
            'title' => 'A1',
            'content' => 'a',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_DRAFT,
        ]);

        $chapterB = Chapter::create([
            'novel_id' => $novelB->id,
            'title' => 'B1',
            'content' => 'b',
            'chapter_number' => 1,
            'word_count' => 1,
            'status' => Chapter::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($author);

        $response = $this->postJson("/api/novels/{$novelA->slug}/chapters/bulk-delete", [
            'chapter_ids' => [$chapterA->id, $chapterB->id],
        ]);

        $response->assertStatus(400);

        $this->assertDatabaseHas('chapters', ['id' => $chapterA->id]);
        $this->assertDatabaseHas('chapters', ['id' => $chapterB->id]);
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
            'description' => 'Novel for chapter tests',
            'status' => 'ongoing',
        ], $attributes));
    }
}
