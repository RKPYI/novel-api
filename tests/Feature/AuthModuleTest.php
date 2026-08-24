<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Auth Tester',
            'email' => 'auth@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'auth@test.com')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', [
            'email' => 'auth@test.com',
            'provider' => 'email',
        ]);
    }

    public function test_register_rejects_password_below_minimum_length(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Auth Tester',
            'email' => 'short-pass@test.com',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplicate@test.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Duplicate',
            'email' => 'duplicate@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@test.com',
            'password' => Hash::make('Password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@test.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_login_updates_last_login_at_and_returns_token(): void
    {
        $user = User::factory()->create([
            'email' => 'success-login@test.com',
            'password' => Hash::make('Password123'),
            'last_login_at' => null,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'success-login@test.com',
            'password' => 'Password123',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_multiple_times_creates_multiple_tokens_for_same_user(): void
    {
        $user = User::factory()->create([
            'email' => 'concurrent-login@test.com',
            'password' => Hash::make('Password123'),
        ]);

        $first = $this->postJson('/api/auth/login', [
            'email' => 'concurrent-login@test.com',
            'password' => 'Password123',
        ]);

        $second = $this->postJson('/api/auth/login', [
            'email' => 'concurrent-login@test.com',
            'password' => 'Password123',
        ]);

        $first->assertOk();
        $second->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->assertNotSame(
            $first->json('token'),
            $second->json('token')
        );

        $this->assertEquals($user->id, $user->fresh()->id);
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CurrentPass123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/auth/change-password', [
            'current_password' => 'WrongCurrent123',
            'new_password' => 'NewSecurePass123',
            'new_password_confirmation' => 'NewSecurePass123',
        ]);

        $response->assertStatus(400)->assertJsonPath('message', 'Current password is incorrect');
    }
}
