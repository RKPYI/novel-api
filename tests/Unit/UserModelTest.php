<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_helpers_and_accessors_are_correct(): void
    {
        $author = User::factory()->create([
            'role' => User::ROLE_AUTHOR,
            'email_verified_at' => now(),
            'avatar' => '/storage/avatars/test.png',
        ]);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
        ]);

        $this->assertTrue($author->isAuthor());
        $this->assertFalse($author->isAdmin());
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->canReviewChapters());
        $this->assertTrue($author->getIsVerifiedAttribute());
        $this->assertStringContainsString('/storage/avatars/test.png', $author->avatar);
    }

    public function test_google_avatar_urls_are_preserved_without_rewriting(): void
    {
        $user = User::factory()->create([
            'avatar' => 'https://example.com/avatar.png',
        ]);

        $this->assertSame('https://example.com/avatar.png', $user->avatar);
    }
}
