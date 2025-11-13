<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'provider' => 'email',
        ]);

        // Send email verification
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
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();
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
                'is_admin' => $user->isAdmin(),
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'bio' => 'nullable|string|max:500',
            'avatar' => 'nullable|url|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->only(['name', 'bio', 'avatar']));

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
        // Check if this is for Telescope access
        $state = $request->get('telescope') === 'true' ? 'telescope' : null;

        $driver = Socialite::driver('google')->stateless();

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
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Update existing user with Google info if not already set
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
                // Create new user
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

            // Check if this is a Telescope login request
            $state = $request->get('state');
            if ($state === 'telescope') {
                // Redirect to Telescope login page with token and user data
                $redirectUrl = url('/telescope/login-callback?' . http_build_query([
                    'token' => $token,
                    'user' => base64_encode(json_encode($userData))
                ]));

                return redirect($redirectUrl);
            }

            // Regular frontend redirect
            $frontendUrl = env('FRONTEND_URL', 'https://rantale.randk.me');
            $redirectUrl = $frontendUrl . '/auth/google/callback?' . http_build_query([
                'success' => 'true',
                'token' => $token,
                'user' => base64_encode(json_encode($userData))
            ]);

            return redirect($redirectUrl);

            // return response()->json([
            //     'message' => 'Google login successful',
            //     'user' => [
            //         'id' => $user->id,
            //         'name' => $user->name,
            //         'email' => $user->email,
            //         'role' => $user->role,
            //         'avatar' => $user->avatar,
            //         'bio' => $user->bio,
            //         'is_admin' => $user->isAdmin(),
            //     ],
            //     'token' => $token,
            // ]);

        } catch (\Exception $e) {
            // Check if this is a Telescope login request
            $state = $request->get('state');
            if ($state === 'telescope') {
                return redirect('/telescope/login?error=authentication_failed');
            }

            $frontendUrl = env('FRONTEND_URL', 'https://rantale.randk.me');
            $redirectUrl = $frontendUrl . '/auth/google/callback?' . http_build_query([
                'error' => 'authentication_failed',
                'message' => 'Google authentication failed'
            ]);

            return redirect($redirectUrl);

            // return response()->json([
            //     'message' => 'Google authentication failed',
            //     'error' => $e->getMessage()
            // ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        // Users who signed up with Google don't have passwords
        if ($user->provider === 'google' && !$user->password) {
            return response()->json([
                'message' => 'Cannot change password for Google-authenticated users without existing password'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
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
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::find($id);

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

        if (!$user) {
            // Redirect to frontend with error
            return redirect($frontendUrl . '/verify-email?status=error&message=Invalid verification link');
        }

        if (!hash_equals((string) $hash, sha1($user->email))) {
            // Redirect to frontend with error
            return redirect($frontendUrl . '/verify-email?status=error&message=Invalid verification link');
        }

        if ($user->hasVerifiedEmail()) {
            // Redirect to frontend - already verified
            return redirect($frontendUrl . '/verify-email?status=already_verified&message=Email is already verified');
        }

        $user->markEmailAsVerified();

        // Redirect to frontend with success
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
