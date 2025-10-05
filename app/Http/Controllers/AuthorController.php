<?php

namespace App\Http\Controllers;

use App\Models\Novel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AuthorController extends Controller
{
    /**
     * Get author statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ensure user can create novels (author, moderator, or admin)
        if (!$user->canCreateNovels()) {
            return response()->json([
                'message' => 'Unauthorized. Author privileges required.'
            ], 403);
        }

        // Get novels by this author (using user_id)
        $authorNovels = Novel::where('user_id', $user->id);

        // Calculate statistics
        $totalNovels = $authorNovels->count();
        $totalViews = $authorNovels->sum('views');

        // For now, we'll set followers to 0 since we don't have a following system yet
        $totalFollowers = 0;
        $monthlyFollowers = null;

        // Calculate monthly views (views gained in the last 30 days)
        // Since we don't have view tracking by date, we'll return null for now
        $monthlyViews = null;

        // Calculate average rating across all author's novels
        $averageRating = $authorNovels->whereNotNull('rating')
            ->where('rating', '>', 0)
            ->avg('rating');

        // Round to 2 decimal places if not null
        $averageRating = $averageRating ? round($averageRating, 2) : null;

        return response()->json([
            'total_novels' => $totalNovels,
            'total_views' => $totalViews,
            'total_followers' => $totalFollowers,
            'monthly_views' => $monthlyViews,
            'monthly_followers' => $monthlyFollowers,
            'average_rating' => $averageRating,
        ]);
    }

    /**
     * Get author's novels with extended stats
     */
    public function getNovels(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ensure user can create novels (author, moderator, or admin)
        if (!$user->canCreateNovels()) {
            return response()->json([
                'message' => 'Unauthorized. Author privileges required.'
            ], 403);
        }

        // Get novels by this author with additional statistics
        $novels = Novel::where('user_id', $user->id)
            ->with(['genres'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($novel) {
                // Use the total_chapters field that's automatically maintained
                $novel->chapters_count = (int) $novel->total_chapters;
                $novel->views_count = (int) $novel->views;
                $novel->rating_avg = ($novel->rating_count > 0) ? (float) $novel->rating : null;

                return $novel;
            });

        return response()->json([
            'message' => 'Author novels retrieved successfully',
            'novels' => $novels
        ]);
    }
}
