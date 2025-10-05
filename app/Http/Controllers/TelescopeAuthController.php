<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TelescopeAuthController extends Controller
{
    /**
     * Show the Telescope login page
     */
    public function showLogin()
    {
        // If already authenticated and admin, redirect to Telescope
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect('/telescope');
        }

        return view('telescope-login');
    }

    /**
     * Handle Telescope login (creates a session)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Attempt to authenticate
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        // Check if user is admin
        if (!$user->isAdmin()) {
            Auth::logout();
            return response()->json([
                'message' => 'Access denied. Only administrators can access Telescope.'
            ], 403);
        }

        // Regenerate session to prevent fixation
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->isAdmin(),
            ],
            'redirect' => '/telescope'
        ]);
    }

    /**
     * Handle Google OAuth callback for Telescope
     */
    public function handleGoogleCallback(Request $request)
    {
        $token = $request->get('token');
        $userData = $request->get('user');

        if (!$token || !$userData) {
            return redirect('/telescope/login?error=authentication_failed');
        }

        try {
            $user = json_decode(base64_decode($userData), true);

            if (!$user['is_admin']) {
                return redirect('/telescope/login?error=not_admin');
            }

            // Find and login the user via session
            $dbUser = User::find($user['id']);

            if (!$dbUser) {
                return redirect('/telescope/login?error=user_not_found');
            }

            Auth::login($dbUser);
            $request->session()->regenerate();

            return redirect('/telescope');

        } catch (\Exception $e) {
            return redirect('/telescope/login?error=invalid_data');
        }
    }

    /**
     * Handle Telescope access check
     */
    public function checkAccess(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'authenticated' => false,
                'message' => 'Not authenticated'
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'authenticated' => true,
                'admin' => false,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        return response()->json([
            'authenticated' => true,
            'admin' => true,
            'message' => 'Access granted',
            'redirect' => '/telescope'
        ]);
    }
}
