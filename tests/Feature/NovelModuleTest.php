<?php

namespace Tests\Feature;

use App\Models\Novel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NovelModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_create_novel_and_author_fallback_uses_user_name(): void
    {
        $author = $this->makeUserWithRole(User::ROLE_AUTHOR);
        Sanctum::actingAs($author);

        $response = $this->postJson('/api/novels', [
            'title' => 'My First Novel',
            'description' => 'A starter novel',
            'status' => 'ongoing',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('novel.title', 'My First Novel')
            ->assertJsonPath('novel.author', $author->name);

        $this->assertDatabaseHas('novels', [
            'title' => 'My First Novel',
            'author' => $author->name,
            'user_id' => $author->id,
        ]);
    }

    public function test_non_author_cannot_create_novel(): void
    {
        $regularUser = $this->makeUserWithRole(User::ROLE_USER);
        Sanctum::actingAs($regularUser);

        $response = $this->postJson('/api/novels', [
            'title' => 'Forbidden Novel',
            'description' => 'Should fail',
        ]);

        $response->assertForbidden();
    }

    public function test_create_novel_rejects_title_over_255_characters(): void
    {
        $author = $this->makeUserWithRole(User::ROLE_AUTHOR);
        Sanctum::actingAs($author);

        $response = $this->postJson('/api/novels', [
            'title' => str_repeat('A', 256),
            'description' => 'Boundary test',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['title']);
    }

    public function test_owner_can_update_novel(): void
    {
        $author = $this->makeUserWithRole(User::ROLE_AUTHOR);
        $novel = $this->makeNovelFor($author, ['title' => 'Before Update']);

        Sanctum::actingAs($author);

        $response = $this->putJson("/api/novels/{$novel->slug}", [
            'title' => 'After Update',
            'status' => 'completed',
        ]);

        $response->assertOk()->assertJsonPath('novel.title', 'After Update');

        $this->assertDatabaseHas('novels', [
            'id' => $novel->id,
            'title' => 'After Update',
            'status' => 'completed',
        ]);
    }

    public function test_non_owner_cannot_update_novel(): void
    {
        $owner = $this->makeUserWithRole(User::ROLE_AUTHOR);
        $otherAuthor = $this->makeUserWithRole(User::ROLE_AUTHOR);
        $novel = $this->makeNovelFor($owner);

        Sanctum::actingAs($otherAuthor);

        $response = $this->putJson("/api/novels/{$novel->slug}", [
            'title' => 'Unauthorized Update',
        ]);

        $response->assertForbidden();
    }

    public function test_search_requires_query_parameter(): void
    {
        $response = $this->getJson('/api/novels/search');

        $response
            ->assertStatus(400)
            ->assertJsonPath('message', 'Search query is required')
            ->assertJsonPath('novels', []);
    }

    public function test_bulk_delete_with_mixed_ownership_deletes_only_authorized_novels(): void
    {
        $authorA = $this->makeUserWithRole(User::ROLE_AUTHOR, ['email' => 'author-a@test.com']);
        $authorB = $this->makeUserWithRole(User::ROLE_AUTHOR, ['email' => 'author-b@test.com']);

        $ownedNovel = $this->makeNovelFor($authorA, ['title' => 'Owned Novel']);
        $foreignNovel = $this->makeNovelFor($authorB, ['title' => 'Foreign Novel']);

        Sanctum::actingAs($authorA);

        $response = $this->postJson('/api/novels/bulk-delete', [
            'novel_ids' => [$ownedNovel->id, $foreignNovel->id],
        ]);

        $response->assertStatus(207);

        $this->assertDatabaseMissing('novels', ['id' => $ownedNovel->id]);
        $this->assertDatabaseHas('novels', ['id' => $foreignNovel->id]);
    }

    private function makeUserWithRole(int $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'provider' => 'email',
        ], $attributes));
    }

    private function makeNovelFor(User $user, array $attributes = []): Novel
    {
        return Novel::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Novel ' . fake()->unique()->words(3, true),
            'author' => $user->name,
            'description' => 'Description',
            'status' => 'ongoing',
        ], $attributes));
    }
}
