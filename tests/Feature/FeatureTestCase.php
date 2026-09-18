<?php

namespace Tests\Feature;

use App\Models\Novel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function makeUserWithRole(int $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'provider' => 'email',
        ], $attributes));
    }

    protected function makeNovelFor(User $user, array $attributes = []): Novel
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
