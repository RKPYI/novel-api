<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Novel;
use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Rating;
use App\Models\UserLibrary;
use App\Models\AuthorApplication;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Get admin dashboard statistics
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Overall platform statistics
        $stats = [
            // User statistics
            'users' => [
                'total' => User::count(),
                'new_this_month' => User::whereMonth('created_at', now()->month)->count(),
                'verified' => User::whereNotNull('email_verified_at')->count(),
                'active_today' => User::whereDate('last_login_at', now()->toDateString())->count(),
                'by_role' => [
                    'users' => User::where('role', User::ROLE_USER)->count(),
                    'authors' => User::where('role', User::ROLE_AUTHOR)->count(),
                    'moderators' => User::where('role', User::ROLE_MODERATOR)->count(),
                    'admins' => User::where('role', User::ROLE_ADMIN)->count(),
                ]
            ],

            // Content statistics
            'content' => [
                'novels' => Novel::count(),
                'chapters' => Chapter::count(),
                'comments' => Comment::count(),
                'ratings' => Rating::count(),
                'pending_comments' => Comment::where('is_approved', false)->count(),
                'novels_this_month' => Novel::whereMonth('created_at', now()->month)->count(),
            ],

            // Engagement statistics
            'engagement' => [
                'total_views' => Novel::sum('views'),
                'total_library_entries' => UserLibrary::count(),
                'average_rating' => round(Rating::avg('rating'), 2),
                'top_genres' => DB::table('genre_novel')
                    ->join('genres', 'genre_novel.genre_id', '=', 'genres.id')
                    ->select('genres.name', DB::raw('COUNT(*) as count'))
                    ->groupBy('genres.name')
                    ->orderBy('count', 'desc')
                    ->limit(5)
                    ->get(),
            ],

            // Author applications
            'author_applications' => [
                'pending' => AuthorApplication::where('status', AuthorApplication::STATUS_PENDING)->count(),
                'approved_this_month' => AuthorApplication::where('status', AuthorApplication::STATUS_APPROVED)
                    ->whereMonth('reviewed_at', now()->month)->count(),
                'total_approved' => AuthorApplication::where('status', AuthorApplication::STATUS_APPROVED)->count(),
            ]
        ];

        return response()->json($stats);
    }

    /**
     * Get recent activity feed for admin
     */
    public function getRecentActivity(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $limit = $request->query('limit', 20);

        // Get recent activities from different models
        $recentUsers = User::select('id', 'name', 'email', 'created_at', DB::raw("'user_registered' as activity_type"))
            ->latest()
            ->limit(5)
            ->get();

        $recentNovels = Novel::select('id', 'title', 'author', 'created_at', DB::raw("'novel_created' as activity_type"))
            ->latest()
            ->limit(5)
            ->get();

        $recentComments = Comment::with('user:id,name', 'novel:id,title')
            ->select('id', 'user_id', 'novel_id', 'content', 'created_at', DB::raw("'comment_posted' as activity_type"))
            ->latest()
            ->limit(5)
            ->get();

        $recentApplications = AuthorApplication::with('user:id,name')
            ->select('id', 'user_id', 'status', 'created_at', DB::raw("'application_submitted' as activity_type"))
            ->latest()
            ->limit(5)
            ->get();

        // Combine and sort all activities
        $activities = collect()
            ->merge($recentUsers)
            ->merge($recentNovels)
            ->merge($recentComments)
            ->merge($recentApplications)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();

        return response()->json([
            'message' => 'Recent activity retrieved successfully',
            'activities' => $activities
        ]);
    }

    /**
     * Get user management data
     */
    public function getUserManagement(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $page = $request->query('page', 1);
        $search = $request->query('search');
        $role = $request->query('role');
        $status = $request->query('status'); // active, inactive, unverified

        $query = User::select('id', 'name', 'email', 'role', 'is_active', 'email_verified_at', 'last_login_at', 'created_at');

        // Apply filters
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (isset($role) && $role !== 'all') {
            $query->where('role', $role);
        }

        if ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'unverified') {
            $query->whereNull('email_verified_at');
        } elseif ($status === 'active') {
            $query->where('is_active', true)->whereNotNull('email_verified_at');
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(25);

        return response()->json([
            'message' => 'User management data retrieved successfully',
            'users' => $users
        ]);
    }

    /**
     * Update user role or status
     */
    public function updateUser(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'role' => 'sometimes|integer|in:0,1,2,3',
            'is_active' => 'sometimes|boolean',
        ]);

        $updateData = $request->only(['role', 'is_active']);
        $user->update($updateData);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_active', 'email_verified_at'])
        ]);
    }

    /**
     * Get content moderation queue
     */
    public function getModerationQueue(Request $request): JsonResponse
    {
        if (!$request->user()->canModerate()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $type = $request->query('type', 'all'); // comments, novels, all

        $data = [];

        if ($type === 'all' || $type === 'comments') {
            $data['pending_comments'] = Comment::with(['user:id,name', 'novel:id,title'])
                ->where('is_approved', false)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        if ($type === 'all' || $type === 'novels') {
            // For now, all novels are auto-approved, but this could be extended
            $data['recent_novels'] = Novel::select('id', 'title', 'author', 'status', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        return response()->json([
            'message' => 'Moderation queue retrieved successfully',
            'moderation_data' => $data
        ]);
    }

    /**
     * Get system health and performance metrics
     */
    public function getSystemHealth(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Basic system metrics
        $health = [
            'database' => [
                'status' => 'healthy', // Would implement actual health check
                'total_tables' => DB::select("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ?", [config('database.connections.mysql.database')])[0]->count ?? 'unknown',
            ],
            'cache' => [
                'status' => 'healthy', // Would implement cache health check
            ],
            'storage' => [
                'status' => 'healthy', // Would implement storage health check
            ],
            'recent_errors' => [
                // Could pull from logs
                'count_today' => 0,
                'critical_errors' => 0,
            ]
        ];

        return response()->json([
            'message' => 'System health retrieved successfully',
            'health' => $health
        ]);
    }
}
