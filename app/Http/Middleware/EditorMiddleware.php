<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EditorMiddleware
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

        // Check if user can review chapters (editor or admin)
        if (!$request->user()->canReviewChapters()) {
            return response()->json([
                'success' => false,
                'message' => 'Editor privileges required',
                'current_role' => $request->user()->role,
                'required_roles' => ['editor', 'admin']
            ], 403);
        }

        return $next($request);
    }
}
