<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Mockery;

class AuthModuleTest extends FeatureTestCase
{
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

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->makeUserWithRole(User::ROLE_USER);
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully');

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_me_returns_current_user_details(): void
    {
        $user = $this->makeUserWithRole(User::ROLE_AUTHOR, [
            'bio' => 'Author bio',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.is_admin', false)
            ->assertJsonPath('user.role', User::ROLE_AUTHOR);
    }

    public function test_change_password_updates_password_successfully(): void
    {
        $user = $this->makeUserWithRole(User::ROLE_USER, [
            'password' => Hash::make('CurrentPass123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/auth/change-password', [
            'current_password' => 'CurrentPass123',
            'new_password' => 'NewSecurePass123',
            'new_password_confirmation' => 'NewSecurePass123',
        ]);

        $response->assertOk()->assertJsonPath('message', 'Password changed successfully');
        $this->assertTrue(Hash::check('NewSecurePass123', $user->fresh()->password));
    }

    public function test_email_verification_notification_and_resend_routes_are_supported(): void
    {
        Notification::fake();

        $user = $this->makeUserWithRole(User::ROLE_USER, [
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Verification email sent successfully');

        $this->postJson('/api/auth/email/resend-verification')
            ->assertOk()
            ->assertJsonPath('message', 'Verification email resent successfully');
    }

    public function test_google_redirect_returns_auth_url(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('redirect')->andReturn(new class
        {
            public function getTargetUrl(): string
            {
                return 'https://accounts.google.com/o/oauth2/auth';
            }
        });

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->getJson('/api/auth/google')
            ->assertOk()
            ->assertJsonPath('url', 'https://accounts.google.com/o/oauth2/auth');
    }

    public function test_google_callback_redirects_with_success_payload(): void
    {
        $googleUser = new class
        {
            public function getEmail(): string { return 'google@example.com'; }
            public function getName(): string { return 'Google User'; }
            public function getId(): string { return 'google-123'; }
            public function getAvatar(): string { return 'https://example.com/avatar.png'; }
        };

        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('success=true', $response->getTargetUrl());
        $this->assertStringContainsString('token=', $response->getTargetUrl());
    }
}
