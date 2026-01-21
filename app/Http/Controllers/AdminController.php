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

        // Database health check
        $databaseStatus = 'healthy';
        $totalTables = 'unknown';
        try {
            DB::connection()->getPdo();
            $tablesResult = DB::select("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ?", [config('database.connections.mysql.database')]);
            $totalTables = $tablesResult[0]->count ?? 'unknown';
        } catch (\Exception $e) {
            $databaseStatus = 'unhealthy';
        }

        // Cache health check
        $cacheStatus = 'healthy';
        try {
            $testKey = 'health_check_' . time();
            cache()->put($testKey, 'test', 5);
            $retrieved = cache()->get($testKey);
            cache()->forget($testKey);
            if ($retrieved !== 'test') {
                $cacheStatus = 'unhealthy';
            }
        } catch (\Exception $e) {
            $cacheStatus = 'unhealthy';
        }

        // Storage health check
        $storageStatus = 'healthy';
        try {
            $storagePath = storage_path('app');
            if (!is_writable($storagePath)) {
                $storageStatus = 'unhealthy';
            }
        } catch (\Exception $e) {
            $storageStatus = 'unhealthy';
        }

        // Recent errors from logs
        $errorMessages = [];
        $criticalErrorMessages = [];
        $countToday = 0;
        $criticalErrors = 0;

        // Critical error patterns - infrastructure failures that should be treated as critical
        $criticalPatterns = [
            'RedisException',
            'No connection could be made',
            'Connection refused',
            'Database.*not connected',
            'SQLSTATE',
            'could not connect to server',
            'SSL certificate problem',
            'cURL error 60',
            'OAuth.*failed',
            'Storage.*not writable',
            'disk.*full',
            'Memory exhausted',
            'Maximum execution time',
        ];

        try {
            $logPath = storage_path('logs/laravel.log');
            if (file_exists($logPath)) {
                $today = now()->toDateString();

                // Read file from bottom to get recent errors first
                $file = new \SplFileObject($logPath);
                $file->seek(PHP_INT_MAX);
                $lastLine = $file->key();

                // Read last 1000 lines or entire file if smaller
                $startLine = max(0, $lastLine - 1000);
                $lines = [];

                $file->seek($startLine);
                while (!$file->eof()) {
                    $lines[] = $file->current();
                    $file->next();
                }

                // Reverse to get newest first
                $lines = array_reverse($lines);

                $currentError = '';
                $currentLevel = '';
                $currentTimestamp = '';

                foreach ($lines as $line) {
                    // Check if line starts a new log entry
                    if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[^\]]+)\]\s+(\w+)\.(\w+):(.*)/', $line, $matches)) {
                        // Save previous error if it was from today and an error level
                        if ($currentError && strpos($currentTimestamp, $today) !== false &&
                            in_array($currentLevel, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])) {

                            // Check if ERROR message contains critical patterns
                            $isCriticalError = in_array($currentLevel, ['CRITICAL', 'ALERT', 'EMERGENCY']);
                            
                            if (!$isCriticalError && $currentLevel === 'ERROR') {
                                foreach ($criticalPatterns as $pattern) {
                                    if (preg_match('/' . $pattern . '/i', $currentError)) {
                                        $isCriticalError = true;
                                        break;
                                    }
                                }
                            }

                            $errorData = [
                                'timestamp' => $currentTimestamp,
                                'level' => $currentLevel,
                                'message' => trim($currentError),
                                'is_infrastructure_failure' => $isCriticalError && $currentLevel === 'ERROR',
                            ];

                            if ($isCriticalError) {
                                if (count($criticalErrorMessages) < 10) {
                                    $criticalErrorMessages[] = $errorData;
                                }
                            } elseif ($currentLevel === 'ERROR' && count($errorMessages) < 20) {
                                $errorMessages[] = $errorData;
                            }
                        }

                        // Start new error
                        $currentTimestamp = $matches[1];
                        $currentLevel = $matches[3];
                        $currentError = $matches[4];

                        // Count if from today and is error level
                        if (strpos($currentTimestamp, $today) !== false) {
                            if (in_array($currentLevel, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])) {
                                $countToday++;
                                
                                // Also check if ERROR contains critical patterns for counting
                                $isCritical = in_array($currentLevel, ['CRITICAL', 'ALERT', 'EMERGENCY']);
                                if (!$isCritical && $currentLevel === 'ERROR') {
                                    foreach ($criticalPatterns as $pattern) {
                                        if (preg_match('/' . $pattern . '/i', $currentError)) {
                                            $isCritical = true;
                                            break;
                                        }
                                    }
                                }
                                
                                if ($isCritical) {
                                    $criticalErrors++;
                                }
                            }
                        }
                    } else {
                        // Continuation of previous error (stack trace, etc.)
                        $currentError .= "\n" . $line;
                    }

                    // Stop if we have enough errors from today
                    if (count($errorMessages) >= 20 && count($criticalErrorMessages) >= 10 && strpos($currentTimestamp, $today) === false) {
                        break;
                    }
                }

                // Don't forget the last error if it's from today and an error level
                if ($currentError && strpos($currentTimestamp, $today) !== false &&
                    in_array($currentLevel, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])) {

                    // Check if ERROR message contains critical patterns
                    $isCriticalError = in_array($currentLevel, ['CRITICAL', 'ALERT', 'EMERGENCY']);
                    
                    if (!$isCriticalError && $currentLevel === 'ERROR') {
                        foreach ($criticalPatterns as $pattern) {
                            if (preg_match('/' . $pattern . '/i', $currentError)) {
                                $isCriticalError = true;
                                break;
                            }
                        }
                    }

                    $errorData = [
                        'timestamp' => $currentTimestamp,
                        'level' => $currentLevel,
                        'message' => trim($currentError),
                        'is_infrastructure_failure' => $isCriticalError && $currentLevel === 'ERROR',
                    ];

                    if ($isCriticalError) {
                        if (count($criticalErrorMessages) < 10) {
                            $criticalErrorMessages[] = $errorData;
                        }
                    } elseif ($currentLevel === 'ERROR' && count($errorMessages) < 20) {
                        $errorMessages[] = $errorData;
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail if we can't read logs
        }

        $health = [
            'database' => [
                'status' => $databaseStatus,
                'total_tables' => $totalTables,
            ],
            'cache' => [
                'status' => $cacheStatus,
            ],
            'storage' => [
                'status' => $storageStatus,
            ],
            'recent_errors' => [
                'count_today' => $countToday,
                'critical_errors' => $criticalErrors,
                'critical_messages' => $criticalErrorMessages,
                'error_messages' => $errorMessages,
            ]
        ];

        return response()->json([
            'message' => 'System health retrieved successfully',
            'health' => $health
        ]);
    }
}
