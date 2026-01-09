<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Novel;
use Illuminate\Http\Request;
use App\Helpers\CacheHelper;

class ChapterController extends Controller
{
    /**
     * Display a listing of chapters for a novel.
     */
    public function index(Novel $novel)
    {
        // Cache chapters list for 30 minutes
        $cacheKey = "chapters_novel_{$novel->id}";

        $chapters = CacheHelper::remember($cacheKey, now()->addMinutes(30), function () use ($novel) {
            return Chapter::where('novel_id', $novel->id)
                ->select('id', 'title', 'chapter_number', 'word_count')
                ->orderBy('chapter_number')
                ->get()
                ->map(function ($chapter) {
                    return [
                        'id' => $chapter->id,
                        'title' => $chapter->title,
                        'chapter_number' => $chapter->chapter_number,
                        'word_count' => $chapter->word_count,
                    ];
                });
    }, ["chapters_novel_{$novel->id}"]);

        return response()->json([
            'message' => 'Chapters for novel: ' . $novel->title,
            'novel' => [
                'title' => $novel->title,
                'slug' => $novel->slug,
                'author' => $novel->author,
            ],
            'chapters' => $chapters,
        ]);
    }



    /**
     * Store a newly created chapter in storage.
     */
    public function store(Request $request, Novel $novel)
    {
        // Check if user can edit this novel (owner or admin)
        $user = $request->user();
        if ($novel->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'You can only add chapters to your own novels'
            ], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'chapter_number' => 'nullable|integer|min:1',
            'is_free' => 'boolean',
            'published_at' => 'nullable|date'
        ]);

        // Auto-generate chapter number if not provided
        if (!$request->chapter_number) {
            $lastChapter = Chapter::where('novel_id', $novel->id)
                ->orderBy('chapter_number', 'desc')
                ->first();
            $chapterNumber = $lastChapter ? $lastChapter->chapter_number + 1 : 1;
        } else {
            $chapterNumber = $request->chapter_number;

            // Check if chapter number already exists
            $existingChapter = Chapter::where('novel_id', $novel->id)
                ->where('chapter_number', $chapterNumber)
                ->first();

            if ($existingChapter) {
                return response()->json([
                    'message' => 'Chapter number already exists',
                    'existing_chapter' => $existingChapter
                ], 409);
            }
        }

        // Calculate word count
        $wordCount = str_word_count(strip_tags($request->content));

        $chapter = Chapter::create([
            'novel_id' => $novel->id,
            'title' => $request->title,
            'content' => $request->content,
            'chapter_number' => $chapterNumber,
            'word_count' => $wordCount,
            'is_free' => $request->boolean('is_free', true),
            'published_at' => $request->published_at ? now() : null,
        ]);

        // Clear cache for this novel's chapters
        CacheHelper::flush(["chapters_novel_{$novel->id}"], ["chapters_novel_{$novel->id}"]);

        // Send notifications to users who have this novel in their library
        $this->notifyUsersAboutNewChapter($novel, $chapter);

        return response()->json([
            'message' => 'Chapter created successfully',
            'chapter' => $chapter
        ], 201);
    }

    /**
     * Display the specified chapter by chapter number.
     */
    public function show(Novel $novel, $chapterNumber)
    {
        // Find the chapter first (not cached) to increment views and get fresh data
        $chapter = Chapter::where('novel_id', $novel->id)
            ->where('chapter_number', $chapterNumber)
            ->first();

        if (!$chapter) {
            return response()->json([
                'message' => 'Chapter not found'
            ], 404);
        }

        // Increment view count and refresh the model to get updated views
        $chapter->increment('views');
        $chapter->refresh();

        // Cache the navigation data (previous/next chapters) for 1 hour
        $cacheKey = "chapter_nav_{$novel->id}_{$chapterNumber}";

        $navigation = CacheHelper::remember($cacheKey, now()->addHour(), function () use ($novel, $chapter) {
            // Get previous chapter (chapter with smaller chapter_number)
            $previousChapter = Chapter::where('novel_id', $novel->id)
                ->where('chapter_number', '<', $chapter->chapter_number)
                ->orderBy('chapter_number', 'desc')
                ->first();

            // Get next chapter (chapter with larger chapter_number)
            $nextChapter = Chapter::where('novel_id', $novel->id)
                ->where('chapter_number', '>', $chapter->chapter_number)
                ->orderBy('chapter_number', 'asc')
                ->first();

            return [
                'previous_chapter' => $previousChapter ? $previousChapter->chapter_number : null,
                'next_chapter' => $nextChapter ? $nextChapter->chapter_number : null,
            ];
        }, ["chapter_nav_{$novel->id}"]);

        // Merge navigation data with fresh chapter data
        $chapterData = $chapter->toArray();
        $chapterData['previous_chapter'] = $navigation['previous_chapter'];
        $chapterData['next_chapter'] = $navigation['next_chapter'];

        return response()->json([
            'message' => 'Chapter details',
            'novel' => [
                'id' => $novel->id,
                'title' => $novel->title,
                'slug' => $novel->slug,
                'author' => $novel->author,
            ],
            'chapter' => $chapterData,
        ], 200);
    }

    /**
     * Update the specified chapter.
     */
    public function update(Request $request, Novel $novel, Chapter $chapter)
    {
        // Check if chapter belongs to novel
        if ($chapter->novel_id !== $novel->id) {
            return response()->json([
                'message' => 'Chapter does not belong to this novel'
            ], 404);
        }

        // Check if user can edit this novel (owner or admin)
        $user = $request->user();
        if ($novel->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'You can only edit chapters of your own novels'
            ], 403);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'chapter_number' => 'sometimes|integer|min:1',
            'is_free' => 'sometimes|boolean',
            'published_at' => 'nullable|date'
        ]);

        // Check if new chapter number conflicts with existing chapters
        if ($request->has('chapter_number') && $request->chapter_number !== $chapter->chapter_number) {
            $existingChapter = Chapter::where('novel_id', $novel->id)
                ->where('chapter_number', $request->chapter_number)
                ->where('id', '!=', $chapter->id)
                ->first();

            if ($existingChapter) {
                return response()->json([
                    'message' => 'Chapter number already exists',
                    'existing_chapter' => $existingChapter
                ], 409);
            }
        }

        $updateData = $request->only(['title', 'content', 'chapter_number', 'is_free', 'published_at']);

        // Recalculate word count if content is updated
        if ($request->has('content')) {
            $updateData['word_count'] = str_word_count(strip_tags($request->content));
        }

        $oldChapterNumber = $chapter->chapter_number;
        $chapter->update($updateData);

        // Clear cache for this novel's chapters list
        CacheHelper::flush(["chapters_novel_{$novel->id}"], ["chapters_novel_{$novel->id}"]);

        // Clear cache for the old chapter detail
        CacheHelper::forget("chapter_{$novel->id}_{$oldChapterNumber}");
        CacheHelper::forget("chapter_nav_{$novel->id}_{$oldChapterNumber}");

        // If chapter number changed, also clear the new chapter number cache
        if ($request->has('chapter_number') && $request->chapter_number != $oldChapterNumber) {
            CacheHelper::forget("chapter_{$novel->id}_{$request->chapter_number}");
            CacheHelper::forget("chapter_nav_{$novel->id}_{$request->chapter_number}");
        }

        return response()->json([
            'message' => 'Chapter updated successfully',
            'chapter' => $chapter->fresh()
        ]);
    }

    /**
     * Remove the specified chapter.
     */
    public function destroy(Request $request, Novel $novel, Chapter $chapter)
    {
        // Check if chapter belongs to novel
        if ($chapter->novel_id !== $novel->id) {
            return response()->json([
                'message' => 'Chapter does not belong to this novel'
            ], 404);
        }

        // Check if user can edit this novel (owner or admin)
        $user = $request->user();
        if ($novel->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'You can only delete chapters from your own novels'
            ], 403);
        }

        $chapterTitle = $chapter->title;
        $chapterNumber = $chapter->chapter_number;

        $chapter->delete();

        // Clear cache for this novel's chapters list
    CacheHelper::flush(["chapters_novel_{$novel->id}"], ["chapters_novel_{$novel->id}"]);

        // Clear cache for this specific chapter detail
    CacheHelper::forget("chapter_{$novel->id}_{$chapterNumber}");
    CacheHelper::forget("chapter_nav_{$novel->id}_{$chapterNumber}");

        return response()->json([
            'message' => "Chapter '{$chapterTitle}' (#{$chapterNumber}) deleted successfully"
        ]);
    }

    /**
     * Bulk delete chapters
     */
    public function bulkDestroy(Request $request, Novel $novel)
    {
        // Check if user can edit this novel (owner or admin)
        $user = $request->user();
        if ($novel->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'You can only delete chapters from your own novels'
            ], 403);
        }

        $request->validate([
            'chapter_ids' => 'required|array|min:1',
            'chapter_ids.*' => 'required|integer|exists:chapters,id'
        ]);

        $chapterIds = $request->chapter_ids;

        // Verify all chapters belong to the specified novel and count them
        $deletedCount = Chapter::whereIn('id', $chapterIds)
            ->where('novel_id', $novel->id)
            ->count();

        if ($deletedCount !== count($chapterIds)) {
            return response()->json([
                'message' => 'One or more chapters do not belong to this novel or do not exist'
            ], 400);
        }

        // Perform bulk delete (single DELETE query for performance)
        // Note: This bypasses model events, so we manually update total_chapters below
        Chapter::whereIn('id', $chapterIds)
            ->where('novel_id', $novel->id)
            ->delete();

        // Manually decrement total_chapters by the number of deleted chapters
        // This is safe because we validated all chapters belong to this novel above
        $novel->decrement('total_chapters', $deletedCount);
        $novel->touch(); // Update the updated_at timestamp

        // Clear cache for this novel's chapters
    CacheHelper::flush(["chapters_novel_{$novel->id}"], ["chapters_novel_{$novel->id}"]);

        return response()->json([
            'message' => "Successfully deleted {$deletedCount} chapter(s)",
            'deleted_count' => $deletedCount
        ]);
    }

    /**
     * Send notifications to users about new chapter
     */
    private function notifyUsersAboutNewChapter(Novel $novel, Chapter $chapter)
    {
        // Get all users who have this novel in their library with status 'reading'
        $userIds = \App\Models\UserLibrary::where('novel_id', $novel->id)
            ->where('status', \App\Models\UserLibrary::STATUS_READING)
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            \App\Models\Notification::createNewChapterNotification($userId, $novel, $chapter);
        }
    }
}
