<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\AuthorApplicationController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\NovelController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReadingProgressController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserLibraryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::get('google', [AuthController::class, 'redirectToGoogle']);
    Route::get('google/callback', [AuthController::class, 'handleGoogleCallback']);
});

// Email verification routes
Route::prefix('auth')->group(function () {
    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
});

// Protected auth routes
Route::prefix('auth')->middleware(['auth:sanctum'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::put('profile', [AuthController::class, 'updateProfile']);
    Route::put('change-password', [AuthController::class, 'changePassword']);
    Route::post('email/verification-notification', [AuthController::class, 'sendEmailVerification']);
    Route::post('email/resend-verification', [AuthController::class, 'resendEmailVerification']);
});

// User profile routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('user/profile/stats', [UserController::class, 'getProfileStats']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum']);

// Author application routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('author/apply', [AuthorApplicationController::class, 'apply']);
    Route::get('author/application-status', [AuthorApplicationController::class, 'getStatus']);
});

// Author stats and novels routes
Route::middleware(['auth:sanctum', 'author'])->group(function () {
    Route::get('author/stats', [AuthorController::class, 'getStats']);
    Route::get('author/novels', [AuthorController::class, 'getNovels']);
});

// Admin routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Admin dashboard and system management
    Route::get('admin/dashboard/stats', [AdminController::class, 'getDashboardStats']);
    Route::get('admin/activity', [AdminController::class, 'getRecentActivity']);
    Route::get('admin/system-health', [AdminController::class, 'getSystemHealth']);

    // User management
    Route::get('admin/users', [AdminController::class, 'getUserManagement']);
    Route::put('admin/users/{user}', [AdminController::class, 'updateUser']);

    // Content moderation
    Route::get('admin/moderation', [AdminController::class, 'getModerationQueue']);

    // Author applications
    Route::get('admin/author-applications', [AuthorApplicationController::class, 'adminIndex']);
    Route::get('admin/author-applications/{application}', [AuthorApplicationController::class, 'adminShow']);
    Route::post('admin/author-applications/{application}/approve', [AuthorApplicationController::class, 'approve']);
    Route::post('admin/author-applications/{application}/reject', [AuthorApplicationController::class, 'reject']);
    Route::put('admin/author-applications/{application}/notes', [AuthorApplicationController::class, 'updateNotes']);
});

// Search operations
Route::get('novels/search', [NovelController::class, 'search']);

// Novel routes - read operations
Route::get('novels/popular', [NovelController::class, 'popular']);
Route::get('novels/latest', [NovelController::class, 'latest']);
Route::get('novels/recently-updated', [NovelController::class, 'recentlyUpdated']);
Route::get('novels/recommendations', [NovelController::class, 'recommendations']);
Route::get('novels/genres', [NovelController::class, 'genres']);
Route::get('novels', [NovelController::class, 'index']);
Route::get('novels/{slug}/related', [NovelController::class, 'related']);
Route::get('novels/{slug}', [NovelController::class, 'show']);

// Author+ novel routes (author, moderator, admin can create/edit)
Route::middleware(['auth:sanctum', 'author'])->group(function () {
    Route::post('novels', [NovelController::class, 'store']);
    Route::put('novels/{slug}', [NovelController::class, 'update']);
    Route::delete('novels/{slug}', [NovelController::class, 'destroy']);
    Route::post('novels/bulk-delete', [NovelController::class, 'bulkDestroy']);
});

// Chapter routes - read operations
Route::get('novels/{novel:slug}/chapters', [ChapterController::class, 'index']);
Route::get('novels/{novel:slug}/chapters/{chapterNumber}', [ChapterController::class, 'show']);

// Chapter routes - author operations
Route::middleware(['auth:sanctum', 'author'])->group(function () {
    Route::post('novels/{novel:slug}/chapters', [ChapterController::class, 'store']);
    Route::put('novels/{novel:slug}/chapters/{chapter}', [ChapterController::class, 'update']);
    Route::delete('novels/{novel:slug}/chapters/{chapter}', [ChapterController::class, 'destroy']);
    Route::post('novels/{novel:slug}/chapters/bulk-delete', [ChapterController::class, 'bulkDestroy']);
});

// Comment routes - read operations
Route::get('novels/{novel:slug}/comments', [CommentController::class, 'index']);
Route::get('novels/{novel:slug}/chapters/{chapterNumber}/comments', [CommentController::class, 'index']);

// Comment write operations
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('comments', [CommentController::class, 'store']);
    Route::put('comments/{comment}', [CommentController::class, 'update']);
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);
    Route::post('comments/{comment}/vote', [CommentController::class, 'vote']);
    Route::get('comments/{comment}/vote', [CommentController::class, 'getUserVote']);
    Route::get('comments/votes', [CommentController::class, 'bulkVotes']);
});

// Routes that require email verification
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // These actions require verified email
    Route::post('comments/verified-only', [CommentController::class, 'store']); // Example protected route
});

// Admin/Moderator comment routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('admin/comments', [CommentController::class, 'adminIndex']);
    Route::put('admin/comments/{comment}/toggle-approval', [CommentController::class, 'adminToggleApproval']);
});

// Rating routes - read operations
Route::get('novels/{novel:slug}/ratings', [RatingController::class, 'index']);

// Rating write operations
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('ratings', [RatingController::class, 'store']);
    Route::get('novels/{novel:slug}/my-rating', [RatingController::class, 'getUserRating']);
    Route::delete('ratings/{rating}', [RatingController::class, 'destroy']);
    Route::get('my-ratings', [RatingController::class, 'getUserRatings']);
});

// Reading progress routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('reading-progress/user', [ReadingProgressController::class, 'getUserProgress']);
    Route::get('reading-progress/{novel:slug}', [ReadingProgressController::class, 'getProgress']);
    Route::post('reading-progress', [ReadingProgressController::class, 'createProgress']);
    Route::put('reading-progress', [ReadingProgressController::class, 'updateProgress']);
    Route::delete('reading-progress/{novel:slug}', [ReadingProgressController::class, 'deleteProgress']);
});

// User Library routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('library', [UserLibraryController::class, 'index']);
    Route::post('library', [UserLibraryController::class, 'store']);
    Route::put('library/{library}', [UserLibraryController::class, 'update']);
    Route::delete('library/{library}', [UserLibraryController::class, 'destroy']);
    Route::get('library/novel/{novel:slug}/status', [UserLibraryController::class, 'checkStatus']);
    Route::post('library/novel/{novel:slug}/toggle-favorite', [UserLibraryController::class, 'toggleFavorite']);
    Route::get('library/statuses', [UserLibraryController::class, 'getStatuses']);
});

// Notification routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
    Route::put('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('notifications/clear-read', [NotificationController::class, 'clearRead']);
    Route::put('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::put('notifications/{notification}/unread', [NotificationController::class, 'markAsUnread']);
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);
});
