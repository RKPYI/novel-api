<?php

namespace App\Http\Controllers;

use App\Models\ReadingProgress;
use App\Models\Novel;
use App\Models\Chapter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReadingProgressController extends Controller
{
    /**
     * Get reading progress for a specific novel and user
     */
    public function getProgress(Request $request, Novel $novel): JsonResponse
    {
        $userId = $request->user()->id;

        $progress = ReadingProgress::where('user_id', $userId)
            ->where('novel_id', $novel->id)
            ->with(['chapter:id,chapter_number,title'])
            ->first();

        if (!$progress) {
            return response()->json([
                'novel_slug' => $novel->slug,
                'user_id' => $userId,
                'current_chapter' => null,
                'progress_percentage' => 0,
                'last_read_at' => null,
                'total_chapters' => Chapter::where('novel_id', $novel->id)->count()
            ]);
        }

        // Calculate progress percentage
        $totalChapters = Chapter::where('novel_id', $novel->id)->count();
        $progressPercentage = $totalChapters > 0 ?
            round(($progress->chapter->chapter_number / $totalChapters) * 100, 2) : 0;

        return response()->json([
            'novel_slug' => $novel->slug,
            'user_id' => $userId,
            'current_chapter' => $progress->chapter,
            'progress_percentage' => $progressPercentage,
            'last_read_at' => $progress->updated_at,
            'total_chapters' => $totalChapters
        ]);
    }

    /**
     * Update reading progress
     */
    public function updateProgress(Request $request): JsonResponse
    {
        $request->validate([
            'novel_slug' => 'required|string',
            'chapter_number' => 'required|integer',
        ]);

        $userId = $request->user()->id;
        $novelSlug = $request->input('novel_slug');
        $chapterNumber = $request->input('chapter_number');

        // Find the novel by slug
        $novel = Novel::where('slug', $novelSlug)->first();
        if (!$novel) {
            return response()->json([
                'error' => 'Novel not found'
            ], 404);
        }

        // Find the chapter by novel and chapter number
        $chapter = Chapter::where('novel_id', $novel->id)
            ->where('chapter_number', $chapterNumber)
            ->first();

        if (!$chapter) {
            return response()->json([
                'error' => 'Chapter not found'
            ], 404);
        }

        // Get current progress to check if we should update
        $currentProgress = ReadingProgress::where('user_id', $userId)
            ->where('novel_id', $novel->id)
            ->with(['chapter'])
            ->first();

        $shouldUpdateProgress = false;
        $message = 'Current reading position retrieved';

        // Update progress only if:
        // 1. No progress exists (first time reading), OR
        // 2. Moving forward to a higher chapter number
        if (!$currentProgress) {
            $shouldUpdateProgress = true;
            $message = 'Reading progress created successfully';
        } elseif ($chapterNumber > $currentProgress->chapter->chapter_number) {
            $shouldUpdateProgress = true;
            $message = 'Reading progress updated successfully';
        } else {
            // User is going backward or jumping - don't update progress
            $message = 'Reading position noted (progress preserved)';
        }

        if ($shouldUpdateProgress) {
            // Update or create reading progress
            $progress = ReadingProgress::updateOrCreate(
                [
                    'user_id' => $userId,
                    'novel_id' => $novel->id
                ],
                [
                    'chapter_id' => $chapter->id,
                    'last_read_at' => now(),
                    'updated_at' => now()
                ]
            );
        } else {
            // Keep existing progress but refresh the object
            $progress = $currentProgress;
        }

        // Calculate progress percentage based on the SAVED progress chapter
        $totalChapters = Chapter::where('novel_id', $novel->id)->count();
        $progressPercentage = $totalChapters > 0 ?
            round(($progress->chapter->chapter_number / $totalChapters) * 100, 2) : 0;

        return response()->json([
            'message' => $message,
            'progress' => [
                'novel_slug' => $novel->slug,
                'user_id' => $userId,
                'current_chapter' => $progress->chapter, // The saved progress chapter
                'requested_chapter' => $chapter, // The chapter they navigated to
                'progress_percentage' => $progressPercentage,
                'last_read_at' => $progress->updated_at,
                'total_chapters' => $totalChapters,
                'progress_updated' => $shouldUpdateProgress
            ]
        ]);
    }

    /**
     * Get all reading progress for a user
     */
    public function getUserProgress(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $progressList = ReadingProgress::where('user_id', $userId)
            ->with([
                'novel:id,title,author,cover_image,slug',
                'chapter:id,chapter_number,title'
            ])
            ->orderBy('updated_at', 'desc')
            ->get();

        $formattedProgress = $progressList->map(function ($progress) {
            $totalChapters = Chapter::where('novel_id', $progress->novel_id)->count();
            $progressPercentage = $totalChapters > 0 ?
                round(($progress->chapter->chapter_number / $totalChapters) * 100, 2) : 0;

            return [
                'novel' => $progress->novel,
                'current_chapter' => $progress->chapter,
                'progress_percentage' => $progressPercentage,
                'last_read_at' => $progress->updated_at,
                'total_chapters' => $totalChapters
            ];
        });

        return response()->json([
            'user_id' => $userId,
            'reading_progress' => $formattedProgress
        ]);
    }

    /**
     * Delete reading progress for a novel
     */
    public function deleteProgress(Request $request, Novel $novel): JsonResponse
    {
        $userId = $request->user()->id;

        $deleted = ReadingProgress::where('user_id', $userId)
            ->where('novel_id', $novel->id)
            ->delete();

        if ($deleted) {
            return response()->json([
                'message' => 'Reading progress deleted successfully'
            ]);
        } else {
            return response()->json([
                'message' => 'No reading progress found to delete'
            ], 404);
        }
    }

    /**
     * Create initial reading progress when user starts reading a novel
     */
    public function createProgress(Request $request): JsonResponse
    {
        $request->validate([
            'novel_slug' => 'required|string',
        ]);

        $userId = $request->user()->id;
        $novelSlug = $request->input('novel_slug');

        // Find the novel by slug
        $novel = Novel::where('slug', $novelSlug)->first();
        if (!$novel) {
            return response()->json([
                'error' => 'Novel not found'
            ], 404);
        }

        // Check if progress already exists
        $existingProgress = ReadingProgress::where('user_id', $userId)
            ->where('novel_id', $novel->id)
            ->first();

        if ($existingProgress) {
            return response()->json([
                'message' => 'Reading progress already exists for this novel',
                'progress' => [
                    'novel_slug' => $novel->slug,
                    'user_id' => $userId,
                    'current_chapter' => $existingProgress->chapter,
                    'progress_percentage' => 0,
                    'last_read_at' => $existingProgress->updated_at
                ]
            ], 409); // Conflict status code
        }

        // Get the first chapter of the novel
        $firstChapter = Chapter::where('novel_id', $novel->id)
            ->orderBy('chapter_number', 'asc')
            ->first();

        if (!$firstChapter) {
            return response()->json([
                'error' => 'No chapters found for this novel'
            ], 404);
        }

        // Create initial reading progress
        $progress = ReadingProgress::create([
            'user_id' => $userId,
            'novel_id' => $novel->id,
            'chapter_id' => $firstChapter->id,
            'last_read_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Calculate total chapters
        $totalChapters = Chapter::where('novel_id', $novel->id)->count();

        return response()->json([
            'message' => 'Reading progress created successfully',
            'progress' => [
                'novel_slug' => $novel->slug,
                'user_id' => $userId,
                'current_chapter' => $firstChapter,
                'progress_percentage' => $totalChapters > 0 ? round((1 / $totalChapters) * 100, 2) : 0,
                'last_read_at' => $progress->updated_at,
                'total_chapters' => $totalChapters
            ]
        ], 201); // Created status code
    }
}
