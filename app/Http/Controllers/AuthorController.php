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

        $userId = $user->id;

        // Get base novel IDs for this author
        $novelIds = Novel::where('user_id', $userId)->pluck('id');

        // Content statistics
        $contentStats = Novel::where('user_id', $userId)
            ->selectRaw('
                COUNT(*) as total_novels,
                SUM(total_chapters) as total_chapters,
                SUM(views) as total_views,
                AVG(CASE WHEN rating > 0 THEN rating END) as average_rating,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_novels,
                COUNT(CASE WHEN status = "ongoing" THEN 1 END) as ongoing_novels
            ')
            ->first();

        // Get total words written across all chapters
        $totalWords = DB::table('chapters')
            ->whereIn('novel_id', $novelIds)
            ->sum('word_count');

        // Engagement statistics
        $totalComments = DB::table('comments')
            ->whereIn('novel_id', $novelIds)
            ->count();

        $totalRatings = DB::table('ratings')
            ->whereIn('novel_id', $novelIds)
            ->count();

        $fiveStarRatings = DB::table('ratings')
            ->whereIn('novel_id', $novelIds)
            ->where('rating', 5)
            ->count();

        // Reader engagement from library
        $libraryStats = DB::table('user_libraries')
            ->whereIn('novel_id', $novelIds)
            ->selectRaw('
                COUNT(*) as total_library_adds,
                COUNT(CASE WHEN is_favorite = 1 THEN 1 END) as total_favorites,
                COUNT(CASE WHEN status = "reading" THEN 1 END) as currently_reading,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_readers,
                COUNT(CASE WHEN status = "want_to_read" THEN 1 END) as want_to_read
            ')
            ->first();

        // Get top performing novel
        $topNovel = Novel::where('user_id', $userId)
            ->orderBy('views', 'desc')
            ->first(['id', 'title', 'slug', 'views', 'rating', 'rating_count']);

        if ($topNovel) {
            $topNovel->comments_count = DB::table('comments')
                ->where('novel_id', $topNovel->id)
                ->count();
        }

        // Calculate novels with high ratings (>= 4.0)
        $highRatedNovels = Novel::where('user_id', $userId)
            ->where('rating', '>=', 4.0)
            ->count();

        return response()->json([
            'content_stats' => [
                'total_novels' => (int) $contentStats->total_novels,
                'total_chapters' => (int) ($contentStats->total_chapters ?? 0),
                'total_words' => (int) $totalWords,
                'completed_novels' => (int) $contentStats->completed_novels,
                'ongoing_novels' => (int) $contentStats->ongoing_novels,
                'avg_chapters_per_novel' => $contentStats->total_novels > 0
                    ? round(($contentStats->total_chapters ?? 0) / $contentStats->total_novels, 1)
                    : 0,
            ],
            'engagement_stats' => [
                'total_views' => (int) ($contentStats->total_views ?? 0),
                'total_comments' => (int) $totalComments,
                'total_ratings' => (int) $totalRatings,
                'total_library_adds' => (int) ($libraryStats->total_library_adds ?? 0),
                'total_favorites' => (int) ($libraryStats->total_favorites ?? 0),
            ],
            'quality_stats' => [
                'average_rating' => $contentStats->average_rating ? round($contentStats->average_rating, 2) : null,
                'novels_above_4_stars' => (int) $highRatedNovels,
                'five_star_ratings' => (int) $fiveStarRatings,
            ],
            'reader_engagement' => [
                'currently_reading' => (int) ($libraryStats->currently_reading ?? 0),
                'completed_readers' => (int) ($libraryStats->completed_readers ?? 0),
                'want_to_read' => (int) ($libraryStats->want_to_read ?? 0),
            ],
            'top_novel' => $topNovel ? [
                'id' => $topNovel->id,
                'title' => $topNovel->title,
                'slug' => $topNovel->slug,
                'views' => (int) $topNovel->views,
                'rating' => $topNovel->rating ? round($topNovel->rating, 2) : null,
                'rating_count' => (int) $topNovel->rating_count,
                'comments' => (int) $topNovel->comments_count,
            ] : null,
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
