<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ReadingProgress;
use App\Models\UserLibrary;
use App\Models\Rating;
use App\Models\Comment;
use App\Models\Novel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserController extends Controller
{
    /**
     * Get comprehensive user profile statistics
     *
     * Returns detailed reading statistics, library info, activity metrics, and preferences
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getProfileStats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Reading Progress Statistics
        $readingProgressStats = $this->getReadingProgressStats($user);

        // Library Statistics
        $libraryStats = $this->getLibraryStats($user);

        // Activity Statistics
        $activityStats = $this->getActivityStats($user);

        // Genre Preferences
        $genrePreferences = $this->getGenrePreferences($user);

        // Recent Activity Timeline
        $recentActivity = $this->getRecentActivity($user);

        return response()->json([
            'user_id' => $user->id,
            'username' => $user->name,
            'member_since' => $user->created_at->format('Y-m-d'),
            'reading_progress' => $readingProgressStats,
            'library' => $libraryStats,
            'activity' => $activityStats,
            'genre_preferences' => $genrePreferences,
            'recent_activity' => $recentActivity,
        ]);
    }

    /**
     * Get reading progress statistics
     *
     * @param User $user
     * @return array
     */
    private function getReadingProgressStats(User $user): array
    {
        $readingProgress = ReadingProgress::where('user_id', $user->id)
            ->with(['novel', 'chapter'])
            ->get();

        $totalNovelsRead = $readingProgress->count();

        // Count completed novels (where current chapter is the last chapter)
        $completedNovels = $readingProgress->filter(function ($progress) {
            $novel = $progress->novel;
            if (!$novel) return false;

            return $progress->chapter->chapter_number >= $novel->total_chapters;
        })->count();

        // Count in-progress novels
        $inProgressNovels = $totalNovelsRead - $completedNovels;

        // Total chapters read (sum of current chapter numbers)
        $totalChaptersRead = $readingProgress->sum(function ($progress) {
            return $progress->chapter ? $progress->chapter->chapter_number : 0;
        });

        // Last read information
        $lastRead = $readingProgress->sortByDesc('last_read_at')->first();
        $lastReadInfo = null;

        if ($lastRead && $lastRead->novel) {
            $lastReadInfo = [
                'novel_id' => $lastRead->novel->id,
                'novel_title' => $lastRead->novel->title,
                'novel_slug' => $lastRead->novel->slug,
                'chapter_number' => $lastRead->chapter ? $lastRead->chapter->chapter_number : null,
                'chapter_title' => $lastRead->chapter ? $lastRead->chapter->title : null,
                'last_read_at' => $lastRead->last_read_at?->format('Y-m-d H:i:s'),
            ];
        }

        // Average progress across all novels
        $averageProgress = $readingProgress->avg(function ($progress) {
            $novel = $progress->novel;
            if (!$novel || !$novel->total_chapters || $novel->total_chapters == 0) {
                return 0;
            }

            $currentChapter = $progress->chapter ? $progress->chapter->chapter_number : 0;
            return ($currentChapter / $novel->total_chapters) * 100;
        });

        return [
            'total_novels_reading' => $totalNovelsRead,
            'completed_novels' => $completedNovels,
            'in_progress_novels' => $inProgressNovels,
            'total_chapters_read' => $totalChaptersRead,
            'average_completion_rate' => round($averageProgress ?? 0, 2),
            'last_read' => $lastReadInfo,
        ];
    }

    /**
     * Get library statistics
     *
     * @param User $user
     * @return array
     */
    private function getLibraryStats(User $user): array
    {
        $library = UserLibrary::where('user_id', $user->id)->get();

        $totalInLibrary = $library->count();
        $favoriteCount = $library->where('is_favorite', true)->count();

        // Count by status
        $statusCounts = $library->groupBy('status')->map->count();

        return [
            'total_novels' => $totalInLibrary,
            'favorites' => $favoriteCount,
            'by_status' => [
                'reading' => $statusCounts->get('reading', 0),
                'completed' => $statusCounts->get('completed', 0),
                'want_to_read' => $statusCounts->get('want_to_read', 0),
                'on_hold' => $statusCounts->get('on_hold', 0),
                'dropped' => $statusCounts->get('dropped', 0),
            ],
        ];
    }

    /**
     * Get activity statistics
     *
     * @param User $user
     * @return array
     */
    private function getActivityStats(User $user): array
    {
        $totalComments = Comment::where('user_id', $user->id)->count();
        $totalRatings = Rating::where('user_id', $user->id)->count();

        // Average rating given
        $averageRatingGiven = Rating::where('user_id', $user->id)->avg('rating');

        // Comments this month
        $commentsThisMonth = Comment::where('user_id', $user->id)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        // Ratings this month
        $ratingsThisMonth = Rating::where('user_id', $user->id)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        // Reading days this month (distinct days with reading progress updates)
        $readingDaysThisMonth = ReadingProgress::where('user_id', $user->id)
            ->whereMonth('last_read_at', Carbon::now()->month)
            ->whereYear('last_read_at', Carbon::now()->year)
            ->get()
            ->groupBy(function ($item) {
                return Carbon::parse($item->last_read_at)->format('Y-m-d');
            })
            ->count();

        return [
            'total_comments' => $totalComments,
            'total_ratings' => $totalRatings,
            'average_rating_given' => round($averageRatingGiven ?? 0, 2),
            'this_month' => [
                'comments' => $commentsThisMonth,
                'ratings' => $ratingsThisMonth,
                'reading_days' => $readingDaysThisMonth,
            ],
        ];
    }

    /**
     * Get genre preferences based on library and reading progress
     *
     * @param User $user
     * @return array
     */
    private function getGenrePreferences(User $user): array
    {
        // Get novels from library and reading progress
        $libraryNovelIds = UserLibrary::where('user_id', $user->id)
            ->pluck('novel_id');

        $readingProgressNovelIds = ReadingProgress::where('user_id', $user->id)
            ->pluck('novel_id');

        $allNovelIds = $libraryNovelIds->merge($readingProgressNovelIds)->unique();

        // Get genres from these novels
        $genreCounts = DB::table('genre_novel')
            ->whereIn('novel_id', $allNovelIds)
            ->select('genre_id', DB::raw('count(*) as count'))
            ->groupBy('genre_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $topGenres = [];
        foreach ($genreCounts as $genreCount) {
            $genre = DB::table('genres')->where('id', $genreCount->genre_id)->first();
            if ($genre) {
                $topGenres[] = [
                    'id' => $genre->id,
                    'name' => $genre->name,
                    'count' => $genreCount->count,
                ];
            }
        }

        return $topGenres;
    }

    /**
     * Get recent activity timeline
     *
     * @param User $user
     * @return array
     */
    private function getRecentActivity(User $user): array
    {
        $activities = [];

        // Recent reading progress (last 5)
        $recentReads = ReadingProgress::where('user_id', $user->id)
            ->with(['novel:id,title,slug', 'chapter:id,chapter_number,title'])
            ->orderByDesc('last_read_at')
            ->limit(5)
            ->get();

        foreach ($recentReads as $read) {
            if ($read->novel) {
                $activities[] = [
                    'type' => 'reading',
                    'timestamp' => $read->last_read_at?->format('Y-m-d H:i:s'),
                    'novel' => [
                        'title' => $read->novel->title,
                        'slug' => $read->novel->slug,
                    ],
                    'chapter' => $read->chapter ? [
                        'number' => $read->chapter->chapter_number,
                        'title' => $read->chapter->title,
                    ] : null,
                ];
            }
        }

        // Recent comments (last 5)
        $recentComments = Comment::where('user_id', $user->id)
            ->with(['novel:id,title,slug'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        foreach ($recentComments as $comment) {
            if ($comment->novel) {
                $activities[] = [
                    'type' => 'comment',
                    'timestamp' => $comment->created_at->format('Y-m-d H:i:s'),
                    'novel' => [
                        'title' => $comment->novel->title,
                        'slug' => $comment->novel->slug,
                    ],
                    'content' => substr($comment->content, 0, 100) . (strlen($comment->content) > 100 ? '...' : ''),
                ];
            }
        }

        // Recent ratings (last 5)
        $recentRatings = Rating::where('user_id', $user->id)
            ->with(['novel:id,title,slug'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        foreach ($recentRatings as $rating) {
            if ($rating->novel) {
                $activities[] = [
                    'type' => 'rating',
                    'timestamp' => $rating->created_at->format('Y-m-d H:i:s'),
                    'novel' => [
                        'title' => $rating->novel->title,
                        'slug' => $rating->novel->slug,
                    ],
                    'rating' => $rating->rating,
                ];
            }
        }

        // Sort all activities by timestamp
        usort($activities, function ($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });

        // Return only the 10 most recent
        return array_slice($activities, 0, 10);
    }
}
