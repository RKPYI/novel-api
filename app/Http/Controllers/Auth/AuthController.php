<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'provider' => 'email',
        ]);

        $user->sendEmailVerificationNotification();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'email_verified' => $user->hasVerifiedEmail(),
                'role' => $user->role,
                'avatar' => $user->avatar,
                'bio' => $user->bio,
                'is_admin' => $user->isAdmin(),
            ],
            'token' => $token,
            'verification_notice' => 'Please check your email for verification link.'
        ], 201);
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User not found'
            ], 401);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'email_verified' => $user->hasVerifiedEmail(),
                'role' => $user->role,
                'avatar' => $user->avatar,
                'bio' => $user->bio,
                'is_admin' => $user->isAdmin(),
            ],
            'token' => $token,
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get current user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'email_verified' => $user->hasVerifiedEmail(),
                'role' => $user->role,
                'avatar' => $user->avatar,
                'bio' => $user->bio,
                'provider' => $user->provider,
                'provider_id' => $user->provider_id,
                'is_admin' => $user->isAdmin(),
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->validated());

        // Keep user cache entries consistent after profile edits.
        Cache::forget("user_{$user->id}");
        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'bio' => $user->bio,
                'is_admin' => $user->isAdmin(),
            ]
        ]);
    }

    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle(Request $request): JsonResponse
    {
        $state = $request->get('telescope') === 'true' ? 'telescope' : null;

        $driver = Socialite::driver('google');
        if (method_exists($driver, 'stateless')) {
            $driver = call_user_func([$driver, 'stateless']);
        }

        if ($state) {
            $driver->with(['state' => $state]);
        }

        $url = $driver->redirect()->getTargetUrl();

        return response()->json([
            'url' => $url
        ]);
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $driver = Socialite::driver('google');
            if (method_exists($driver, 'stateless')) {
                $driver = call_user_func([$driver, 'stateless']);
            }

            $googleUser = $driver->user();

            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                if (!$user->provider_id) {
                    $user->update([
                        'provider' => 'google',
                        'provider_id' => $googleUser->getId(),
                        'avatar' => $user->avatar ?: $googleUser->getAvatar(),
                        'last_login_at' => now(),
                    ]);
                } else {
                    $user->update(['last_login_at' => now()]);
                }
            } else {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                    'last_login_at' => now(),
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'bio' => $user->bio,
                'is_admin' => $user->isAdmin(),
            ];

            $state = $request->get('state');
            if ($state === 'telescope') {
                $redirectUrl = url('/telescope/login-callback?' . http_build_query([
                    'token' => $token,
                    'user' => base64_encode(json_encode($userData))
                ]));

                return redirect($redirectUrl);
            }

            $frontendUrl = config('frontend.url');
            $redirectUrl = $frontendUrl . '/auth/google/callback?' . http_build_query([
                'success' => 'true',
                'token' => $token,
                'user' => base64_encode(json_encode($userData))
            ]);

            return redirect($redirectUrl);

        } catch (\Exception $e) {
            Log::error('Google OAuth failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            $state = $request->get('state');
            if ($state === 'telescope') {
                return redirect('/telescope/login?error=authentication_failed&message=' . urlencode($e->getMessage()));
            }

            $frontendUrl = config('frontend.url');
            $redirectUrl = $frontendUrl . '/auth/google/callback?' . http_build_query([
                'error' => 'authentication_failed',
                'message' => $e->getMessage()
            ]);

            return redirect($redirectUrl);
        }
    }

    /**
     * Change password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->provider === 'google' && !$user->password) {
            return response()->json([
                'message' => 'Cannot change password for Google-authenticated users without existing password'
            ], 400);
        }

        $data = $request->validated();

        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($data['new_password'])
        ]);

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Send email verification notification
     */
    public function sendEmailVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email sent successfully'
        ]);
    }

    /**
     * Verify user email
     */
    public function verifyEmail(Request $request, $id, $hash): RedirectResponse
    {
        $user = User::find($id);

        $frontendUrl = config('frontend.url');

        if (!$user) {
            return redirect($frontendUrl . '/verify-email?status=error&message=Invalid verification link');
        }

        if (!hash_equals((string) $hash, sha1($user->email))) {
            return redirect($frontendUrl . '/verify-email?status=error&message=Invalid verification link');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect($frontendUrl . '/verify-email?status=already_verified&message=Email is already verified');
        }

        $user->markEmailAsVerified();

        return redirect($frontendUrl . '/verify-email?status=success&message=Email verified successfully');
    }

    /**
     * Resend email verification
     */
    public function resendEmailVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email resent successfully'
        ]);
    }
}
