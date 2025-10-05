<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\Novel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class RatingController extends Controller
{
    /**
     * Get ratings for a novel
     */
    public function index(Novel $novel): JsonResponse
    {
        $ratings = Rating::with(['user:id,name,avatar'])
            ->where('novel_id', $novel->id)
            ->whereNotNull('review') // Only show ratings with reviews
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Get rating distribution in a single query using GROUP BY
        $breakdown = Rating::where('novel_id', $novel->id)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $stats = [
            'average_rating' => $novel->rating,
            'total_ratings' => $novel->rating_count,
            'rating_breakdown' => [
                '5' => $breakdown[5] ?? 0,
                '4' => $breakdown[4] ?? 0,
                '3' => $breakdown[3] ?? 0,
                '2' => $breakdown[2] ?? 0,
                '1' => $breakdown[1] ?? 0,
            ]
        ];

        return response()->json([
            'ratings' => $ratings,
            'stats' => $stats
        ]);
    }

    /**
     * Store or update a rating
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), Rating::validationRules());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $request->user()->id;
        $novelId = $request->novel_id;

        // Check if user has already rated this novel
        $existingRating = Rating::where('user_id', $userId)
            ->where('novel_id', $novelId)
            ->first();

        if ($existingRating) {
            // Update existing rating
            $existingRating->update([
                'rating' => $request->rating,
                'review' => $request->review,
            ]);
            $rating = $existingRating;
            $message = 'Rating updated successfully';
        } else {
            // Create new rating
            $rating = Rating::create([
                'user_id' => $userId,
                'novel_id' => $novelId,
                'rating' => $request->rating,
                'review' => $request->review,
            ]);
            $message = 'Rating created successfully';
        }

        // Update novel's average rating
        $novel = Novel::find($novelId);
        $novel->updateRating();

        $rating->load(['user:id,name,avatar', 'novel:id,title']);

        return response()->json([
            'message' => $message,
            'rating' => $rating,
            'novel_stats' => [
                'average_rating' => $novel->rating,
                'total_ratings' => $novel->rating_count,
            ]
        ], $existingRating ? 200 : 201);
    }

    /**
     * Get user's rating for a novel
     */
    public function getUserRating(Request $request, Novel $novel): JsonResponse
    {
        $rating = Rating::where('user_id', $request->user()->id)
            ->where('novel_id', $novel->id)
            ->first();

        return response()->json([
            'rating' => $rating
        ]);
    }

    /**
     * Delete a rating
     */
    public function destroy(Request $request, Rating $rating): JsonResponse
    {
        $user = $request->user();

        // Check if user owns the rating or is admin
        if ($rating->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $novelId = $rating->novel_id;
        $rating->delete();

        // Update novel's average rating
        $novel = Novel::find($novelId);
        $novel->updateRating();

        return response()->json([
            'message' => 'Rating deleted successfully',
            'novel_stats' => [
                'average_rating' => $novel->rating,
                'total_ratings' => $novel->rating_count,
            ]
        ]);
    }

    /**
     * Get user's all ratings
     */
    public function getUserRatings(Request $request): JsonResponse
    {
        $ratings = Rating::with(['novel:id,title,author,cover_image,slug'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($ratings);
    }
}
