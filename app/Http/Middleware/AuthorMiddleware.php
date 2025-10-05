<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Check if user can create novels (author, moderator, or admin)
        if (!$request->user()->canCreateNovels()) {
            return response()->json([
                'success' => false,
                'message' => 'Author privileges required. Please apply for author status.',
                'current_role' => $request->user()->role,
                'required_roles' => ['author', 'moderator', 'admin']
            ], 403);
        }

        return $next($request);
    }
}