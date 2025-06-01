<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\NovelController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReadingProgressController;
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
    Route::post('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum']);

// Search operations
Route::get('novels/search', [NovelController::class, 'search']);

// Novel routes - read operations
Route::get('novels/popular', [NovelController::class, 'popular']);
Route::get('novels/latest', [NovelController::class, 'latest']);
Route::get('novels/recommendations', [NovelController::class, 'recommendations']);
Route::get('novels/genres', [NovelController::class, 'genres']);
Route::get('novels', [NovelController::class, 'index']);
Route::get('novels/{slug}', [NovelController::class, 'show']);

// Admin-only novel routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('novels', [NovelController::class, 'store']);
    Route::put('novels/{slug}', [NovelController::class, 'update']);
    Route::delete('novels/{slug}', [NovelController::class, 'destroy']);
});

// Chapter routes - read operations
Route::get('novels/{novel:slug}/chapters', [ChapterController::class, 'index']);
Route::get('novels/{novel:slug}/chapters/{chapterNumber}', [ChapterController::class, 'show']);

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
});

// Routes that require email verification
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // These actions require verified email
    Route::post('comments/verified-only', [CommentController::class, 'store']); // Example protected route
});

// Admin comment routes
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
