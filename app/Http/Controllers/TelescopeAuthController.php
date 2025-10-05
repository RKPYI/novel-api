<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
