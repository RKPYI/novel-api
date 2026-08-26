<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Helpers\ChapterOrderHelper;
use App\Models\Chapter;
use App\Models\Novel;
use App\Models\Volume;
use App\Services\VolumeService;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    /**
     * Display a listing of chapters for a novel.
     * Public access only shows published (approved) chapters.
     */
    public function index(Novel $novel)
    {
        $cacheKey = "chapters_novel_{$novel->id}_published";

        $payload = CacheHelper::remember($cacheKey, now()->addMinutes(30), function () use ($novel) {
            return $this->buildPublicChapterList($novel);
        }, ["chapters_novel_{$novel->id}"]);

        return response()->json(array_merge([
            'message' => 'Chapters for novel: ' . $novel->title,
            'novel' => [
                'title' => $novel->title,
                'slug' => $novel->slug,
                'author' => $novel->author,
            ],
        ], $payload));
    }

    /**
     * Get all chapters for author's own novel (including unpublished).
     * Used by authors to manage their chapters.
     */
    public function authorIndex(Request $request, Novel $novel)
    {
        $user = $request->user();

        // Check if user owns this novel or is admin
        if ($novel->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'You can only view your own novel chapters'
            ], 403);
        }

        $chapters = ChapterOrderHelper::readingOrderQuery(
            $novel,
            Chapter::query()
                ->where('chapters.novel_id', $novel->id)
                ->with(['latestReview' => function ($query) {
                    $query->select('chapter_reviews.id', 'chapter_reviews.chapter_id', 'action', 'notes', 'chapter_reviews.created_at');
                }, 'volume:id,volume_number,title'])
        )->get();

        $response = [
            'message' => 'All chapters for novel: ' . $novel->title,
            'uses_volumes' => $novel->uses_volumes,
            'novel' => [
                'id' => $novel->id,
                'title' => $novel->title,
                'slug' => $novel->slug,
                'author' => $novel->author,
            ],
        ];

        if ($novel->uses_volumes) {
            $response['volumes'] = $this->groupChaptersByVolume($chapters, includeStatus: true);
            $response['chapters'] = $chapters->map(fn (Chapter $c) => $this->formatAuthorChapter($c))->values();
        } else {
            $response['chapters'] = $chapters->map(fn (Chapter $c) => $this->formatAuthorChapter($c))->values();
        }

        return response()->json($response);
    }

    /**
     * Get a specific chapter for author's own novel (including unpublished) by chapter number.
     * This endpoint is author-only and returns the chapter content.
     */
    public function authorShow(Request $request, Novel $novel, $chapterNumber)
    {
        return $this->authorShowChapter($request, $novel, (int) $chapterNumber, null);
    }

    public function authorShowByVolume(Request $request, Novel $novel, $volumeNumber, $chapterNumber)
    {
        return $this->authorShowChapter($request, $novel, (int) $chapterNumber, (int) $volumeNumber);
    }

    private function authorShowChapter(Request $request, Novel $novel, int $chapterNumber, ?int $volumeNumber)
    {
        $user = $request->user();

        if ($novel->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'You can only view your own novel chapters'
            ], 403);
        }

        $chapter = ChapterOrderHelper::resolveChapter($novel, $chapterNumber, $volumeNumber, publishedOnly: false);

        if (!$chapter) {
            return response()->json(['message' => 'Chapter not found'], 404);
        }

        $chapter->load(['latestReview' => function ($query) {
            $query->select('chapter_reviews.id', 'chapter_reviews.chapter_id', 'action', 'notes', 'chapter_reviews.created_at');
        }, 'volume:id,volume_number,title']);

        $navigation = $this->buildAuthorNavigation($novel, $chapter);
        $chapterData = array_merge($chapter->toArray(), $navigation);

        return response()->json([
            'message' => 'Chapter details (author view)',
            'novel' => [
                'id' => $novel->id,
                'title' => $novel->title,
                'slug' => $novel->slug,
                'author' => $novel->author,
                'uses_volumes' => $novel->uses_volumes,
            ],
            'chapter' => $chapterData,
        ], 200);
    }

    /**
     * Resubmit a chapter for review after revision.
     */
    public function submitForReview(Request $request, Novel $novel, Chapter $chapter)
    {
        $user = $request->user();

        // Check if chapter belongs to novel
        if ($chapter->novel_id !== $novel->id) {
            return response()->json([
                'message' => 'Chapter does not belong to this novel'
            ], 404);
        }

        // Check if user owns this novel or is admin
        if ($novel->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'You can only submit your own chapters for review'
            ], 403);
        }

        // Only draft or revision_requested chapters can be submitted
        if (!in_array($chapter->status, [Chapter::STATUS_DRAFT, Chapter::STATUS_REVISION_REQUESTED])) {
            return response()->json([
                'message' => 'This chapter cannot be submitted for review in its current status',
                'current_status' => $chapter->status
            ], 400);
        }

        // Admin can publish directly
        if ($user->isAdmin()) {
            $chapter->update([
                'status' => Chapter::STATUS_APPROVED,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'published_at' => now(),
            ]);

            // Update novel's published chapter count
            $novel->updateChapterCount();

            return response()->json([
                'message' => 'Chapter approved and published successfully',
                'chapter' => $chapter->fresh()
            ]);
        }

        // Update status to pending review
        $chapter->update([
            'status' => Chapter::STATUS_PENDING_REVIEW,
        ]);

        return response()->json([
            'message' => 'Chapter submitted for review successfully',
            'chapter' => $chapter->fresh()
        ]);
    }

    /**
     * Move a chapter to another volume with automatic renumbering in both volumes.
     */
    public function moveToVolume(Request $request, Novel $novel, Chapter $chapter)
    {
        if ($chapter->novel_id !== $novel->id) {
            return response()->json([
                'message' => 'Chapter does not belong to this novel',
            ], 404);
        }

        $user = $request->user();
        if ($novel->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'You can only move chapters in your own novels',
            ], 403);
        }

        if (!$novel->uses_volumes) {
            return response()->json([
                'message' => 'This novel does not use volumes',
            ], 400);
        }

        $request->validate([
            'volume_id' => 'required|integer|exists:volumes,id',
            'chapter_number' => 'nullable|integer|min:1',
        ]);

        $targetVolume = Volume::where('id', $request->volume_id)
            ->where('novel_id', $novel->id)
            ->first();

        if (!$targetVolume) {
            return response()->json([
                'message' => 'Target volume does not belong to this novel',
            ], 422);
        }

        if ($chapter->volume_id === $targetVolume->id && !$request->filled('chapter_number')) {
            return response()->json([
                'message' => 'Chapter is already in this volume',
            ], 400);
        }

        try {
            $chapter->load('novel');
            $movedChapter = app(VolumeService::class)->moveChapterToVolume(
                $chapter,
                $targetVolume,
                $request->input('chapter_number')
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        CacheHelper::flush(["chapters_novel_{$novel->id}"], ["chapters_novel_{$novel->id}"]);

        return response()->json([
            'message' => 'Chapter moved successfully',
            'chapter' => ChapterOrderHelper::formatChapterSummary(
                $movedChapter->load('volume:id,volume_number,title')
            ),
        ]);
    }

    /**
     * Move multiple chapters to another volume with automatic renumbering.
     */
    public function bulkMoveToVolume(Request $request, Novel $novel)
    {
        $user = $request->user();
        if ($novel->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'You can only move chapters in your own novels',
            ], 403);
        }

        if (!$novel->uses_volumes) {
            return response()->json([
                'message' => 'This novel does not use volumes',
            ], 400);
        }

        $request->validate([
            'chapter_ids' => 'required|array|min:1',
            'chapter_ids.*' => 'required|integer|exists:chapters,id',
            'volume_id' => 'required|integer|exists:volumes,id',
            'chapter_number' => 'nullable|integer|min:1',
        ]);

        $targetVolume = Volume::where('id', $request->volume_id)
            ->where('novel_id', $novel->id)
            ->first();

        if (!$targetVolume) {
            return response()->json([
                'message' => 'Target volume does not belong to this novel',
            ], 422);
        }

        $chapterIds = $request->chapter_ids;

        $allInTarget = Chapter::whereIn('id', $chapterIds)
            ->where('novel_id', $novel->id)
            ->where('volume_id', $targetVolume->id)
            ->count() === count($chapterIds);

        if ($allInTarget && !$request->filled('chapter_number')) {
            return response()->json([
                'message' => 'All selected chapters are already in this volume',
            ], 400);
        }

        try {
            $movedCount = app(VolumeService::class)->bulkMoveChaptersToVolume(
                $novel,
                $chapterIds,
                $targetVolume,
                $request->input('chapter_number')
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        CacheHelper::flush(["chapters_novel_{$novel->id}"], ["chapters_novel_{$novel->id}"]);

        return response()->json([
            'message' => 'Chapters moved successfully',
            'moved_count' => $movedCount,
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
            'volume_id' => 'nullable|integer|exists:volumes,id',
            'volume_number' => 'nullable|integer|min:1',
            'is_free' => 'boolean',
            'published_at' => 'nullable|date',
            'save_as_draft' => 'boolean'
        ]);

        $volume = $this->resolveVolumeForChapter($novel, $request);
        if ($volume === false) {
            return response()->json(['message' => 'Invalid volume for this novel'], 422);
        }

        // Auto-generate chapter number if not provided
        if (!$request->chapter_number) {
            $lastChapterQuery = Chapter::where('novel_id', $novel->id);
            if ($novel->uses_volumes && $volume) {
                $lastChapterQuery->where('volume_id', $volume->id);
            } else {
                $lastChapterQuery->whereNull('volume_id');
            }
            $lastChapter = $lastChapterQuery->orderBy('chapter_number', 'desc')->first();
            $chapterNumber = $lastChapter ? $lastChapter->chapter_number + 1 : 1;
        } else {
            $chapterNumber = $request->chapter_number;

            $existingChapterQuery = Chapter::where('novel_id', $novel->id)
                ->where('chapter_number', $chapterNumber);

            if ($novel->uses_volumes && $volume) {
                $existingChapterQuery->where('volume_id', $volume->id);
            } else {
                $existingChapterQuery->whereNull('volume_id');
            }

            $existingChapter = $existingChapterQuery->first();

            if ($existingChapter) {
                return response()->json([
                    'message' => 'Chapter number already exists',
                    'existing_chapter' => $existingChapter
                ], 409);
            }
        }

        // Calculate word count
        $wordCount = str_word_count(strip_tags($request->content));

        // Determine initial status based on user role and save_as_draft flag
        // Admins can publish directly, authors can save as draft or submit for review
        $saveAsDraft = $request->boolean('save_as_draft', false);

        if ($user->isAdmin()) {
            $status = Chapter::STATUS_APPROVED;
            $publishedAt = $request->published_at ? now() : null;
        } elseif ($saveAsDraft) {
            $status = Chapter::STATUS_DRAFT;
            $publishedAt = null;
        } else {
            $status = Chapter::STATUS_PENDING_REVIEW;
            $publishedAt = null;
        }

        $chapter = Chapter::create([
            'novel_id' => $novel->id,
            'volume_id' => $volume?->id,
            'title' => $request->title,
            'content' => $request->content,
            'chapter_number' => $chapterNumber,
            'word_count' => $wordCount,
            'is_free' => $request->boolean('is_free', true),
            'published_at' => $publishedAt,
            'status' => $status,
            'reviewed_by' => $user->isAdmin() ? $user->id : null,
            'reviewed_at' => $user->isAdmin() ? now() : null,
        ]);

        // Clear cache for this novel's chapters
        CacheHelper::flush(["chapters_novel_{$novel->id}"], ["chapters_novel_{$novel->id}"]);

        // Only notify users and update chapter count if chapter is published (admin created)
        if ($status === Chapter::STATUS_APPROVED && $publishedAt) {
            $novel->updateChapterCount();
            $this->notifyUsersAboutNewChapter($novel, $chapter);
        }

        if ($user->isAdmin()) {
            $message = 'Chapter created and published successfully';
        } elseif ($saveAsDraft) {
            $message = 'Chapter saved as draft successfully';
        } else {
            $message = 'Chapter created successfully and submitted for review';
        }

        return response()->json([
            'message' => $message,
            'chapter' => $chapter
        ], 201);
    }

    /**
     * Display the specified chapter by chapter number.
     * Only shows published (approved) chapters to public.
     */
    public function show(Novel $novel, $chapterNumber)
    {
        return $this->showChapter($novel, (int) $chapterNumber, null);
    }

    public function showByVolume(Novel $novel, $volumeNumber, $chapterNumber)
    {
        return $this->showChapter($novel, (int) $chapterNumber, (int) $volumeNumber);
    }

    private function showChapter(Novel $novel, int $chapterNumber, ?int $volumeNumber)
    {
        if ($novel->uses_volumes && $volumeNumber === null) {
            return response()->json(['message' => 'Chapter not found'], 404);
        }

        if (!$novel->uses_volumes && $volumeNumber !== null) {
            return response()->json(['message' => 'Chapter not found'], 404);
        }

        $chapter = ChapterOrderHelper::resolveChapter($novel, $chapterNumber, $volumeNumber, publishedOnly: true);

        if (!$chapter) {
            return response()->json(['message' => 'Chapter not found'], 404);
        }

        $chapter->loadMissing('novel', 'volume:id,volume_number,title');

        $chapter->timestamps = false;
        $chapter->increment('views');
        $chapter->timestamps = true;

        $cacheKey = "chapter_nav_{$novel->id}_{$volumeNumber}_{$chapterNumber}_published";

        $navigation = CacheHelper::remember($cacheKey, now()->addHour(), function () use ($novel, $chapter) {
            return $this->buildPublishedNavigation($novel, $chapter);
        }, ["chapter_nav_{$novel->id}"]);

        $chapterData = array_merge($chapter->toArray(), $navigation);

        return response()->json([
            'message' => 'Chapter details',
            'novel' => [
                'id' => $novel->id,
                'title' => $novel->title,
                'slug' => $novel->slug,
                'author' => $novel->author,
                'uses_volumes' => $novel->uses_volumes,
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

        // Authors cannot edit chapters that are pending review or pending update
        if (!$user->isAdmin() && !$chapter->canBeEditedByAuthor()) {
            return response()->json([
                'message' => 'You cannot edit this chapter while it is pending review. Please wait for the editor to review it first.',
                'current_status' => $chapter->status
            ], 403);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'chapter_number' => 'sometimes|integer|min:1',
            'volume_id' => 'nullable|integer|exists:volumes,id',
            'volume_number' => 'nullable|integer|min:1',
            'is_free' => 'sometimes|boolean',
            'published_at' => 'nullable|date',
            'save_as_draft' => 'boolean'
        ]);

        // Check if new chapter number conflicts with existing chapters
        if ($request->has('chapter_number') && $request->chapter_number !== $chapter->chapter_number) {
            $targetVolumeId = $chapter->volume_id;

            if ($request->filled('volume_id')) {
                $targetVolume = Volume::where('id', $request->volume_id)->where('novel_id', $novel->id)->first();
                if (!$targetVolume) {
                    return response()->json(['message' => 'Invalid volume for this novel'], 422);
                }
                $targetVolumeId = $targetVolume->id;
            }

            $existingChapterQuery = Chapter::where('novel_id', $novel->id)
                ->where('chapter_number', $request->chapter_number)
                ->where('id', '!=', $chapter->id);

            if ($novel->uses_volumes) {
                $existingChapterQuery->where('volume_id', $targetVolumeId);
            } else {
                $existingChapterQuery->whereNull('volume_id');
            }

            $existingChapter = $existingChapterQuery->first();

            if ($existingChapter) {
                return response()->json([
                    'message' => 'Chapter number already exists',
                    'existing_chapter' => $existingChapter
                ], 409);
            }
        }

        $oldChapterNumber = $chapter->chapter_number;
        $saveAsDraft = $request->boolean('save_as_draft', false);

        // Handle different scenarios based on chapter status and user role
        if ($user->isAdmin()) {
            // Admins can directly update any chapter
            $updateData = $request->only(['title', 'content', 'chapter_number', 'is_free', 'published_at', 'volume_id']);

            if ($request->has('content')) {
                $updateData['word_count'] = str_word_count(strip_tags($request->content));
            }

            // If chapter had pending updates, clear them since admin is directly updating
            if ($chapter->hasPendingUpdate()) {
                $updateData['pending_title'] = null;
                $updateData['pending_content'] = null;
                $updateData['status'] = Chapter::STATUS_APPROVED;
            }

            $chapter->update($updateData);
            $message = 'Chapter updated successfully';

        } elseif ($chapter->isPublished()) {
            // Published chapter - store changes as pending update for review
            $pendingData = [];

            if ($request->has('title')) {
                $pendingData['pending_title'] = $request->title;
            }
            if ($request->has('content')) {
                $pendingData['pending_content'] = $request->content;
            }

            // Only allow updating chapter_number and is_free directly (non-content changes)
            $directUpdateData = $request->only(['chapter_number', 'is_free']);

            if (!empty($pendingData)) {
                $pendingData['status'] = Chapter::STATUS_PENDING_UPDATE;
                $chapter->update(array_merge($directUpdateData, $pendingData));
                $message = 'Your changes have been submitted for review. The current published version will remain visible until your updates are approved.';
            } else {
                $chapter->update($directUpdateData);
                $message = 'Chapter updated successfully';
            }

        } else {
            // Draft or revision_requested - can edit directly
            $updateData = $request->only(['title', 'content', 'chapter_number', 'is_free', 'volume_id']);

            if ($request->has('content')) {
                $updateData['word_count'] = str_word_count(strip_tags($request->content));
            }

            // Handle status change based on save_as_draft flag
            if ($chapter->status === Chapter::STATUS_DRAFT && !$saveAsDraft) {
                // Submitting draft for review
                $updateData['status'] = Chapter::STATUS_PENDING_REVIEW;
                $message = 'Chapter updated and submitted for review';
            } elseif ($chapter->status === Chapter::STATUS_REVISION_REQUESTED && !$saveAsDraft) {
                // Resubmitting after revision
                $updateData['status'] = Chapter::STATUS_PENDING_REVIEW;
                $message = 'Chapter updated and resubmitted for review';
            } else {
                $message = 'Chapter updated successfully';
            }

            $chapter->update($updateData);
        }

        // Clear cache for this novel's chapters list
        CacheHelper::flush(["chapters_novel_{$novel->id}"], ["chapters_novel_{$novel->id}"]);
        CacheHelper::flush(["chapters_novel_{$novel->id}_published"], ["chapters_novel_{$novel->id}"]);

        // Clear cache for the old chapter detail
        CacheHelper::forget("chapter_{$novel->id}_{$oldChapterNumber}");
        CacheHelper::forget("chapter_nav_{$novel->id}_{$oldChapterNumber}");
        CacheHelper::forget("chapter_nav_{$novel->id}_{$oldChapterNumber}_published");

        // If chapter number changed, also clear the new chapter number cache
        if ($request->has('chapter_number') && $request->chapter_number != $oldChapterNumber) {
            CacheHelper::forget("chapter_{$novel->id}_{$request->chapter_number}");
            CacheHelper::forget("chapter_nav_{$novel->id}_{$request->chapter_number}");
            CacheHelper::forget("chapter_nav_{$novel->id}_{$request->chapter_number}_published");
        }

        return response()->json([
            'message' => $message,
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

        // Count only published chapters for decrementing total_chapters
        $publishedDeletedCount = Chapter::whereIn('id', $chapterIds)
            ->where('novel_id', $novel->id)
            ->whereIn('status', [Chapter::STATUS_APPROVED, Chapter::STATUS_PENDING_UPDATE])
            ->whereNotNull('published_at')
            ->count();

        // Perform bulk delete (single DELETE query for performance)
        // Note: This bypasses model events, so we manually update total_chapters below
        Chapter::whereIn('id', $chapterIds)
            ->where('novel_id', $novel->id)
            ->delete();

        // Manually decrement total_chapters by the number of published chapters deleted
        if ($publishedDeletedCount > 0) {
            $novel->decrement('total_chapters', $publishedDeletedCount);
        }
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

    private function buildPublicChapterList(Novel $novel): array
    {
        $publishedQuery = Chapter::query()
            ->where('chapters.novel_id', $novel->id)
            ->whereIn('chapters.status', [Chapter::STATUS_APPROVED, Chapter::STATUS_PENDING_UPDATE])
            ->whereNotNull('chapters.published_at')
            ->with('volume:id,volume_number,title');

        $chapters = ChapterOrderHelper::readingOrderQuery($novel, $publishedQuery)->get();

        if ($novel->uses_volumes) {
            return [
                'uses_volumes' => true,
                'volumes' => $this->groupChaptersByVolume($chapters),
                'chapters' => $chapters->map(fn (Chapter $c) => ChapterOrderHelper::formatChapterSummary($c))->values(),
            ];
        }

        return [
            'uses_volumes' => false,
            'chapters' => $chapters->map(fn (Chapter $c) => ChapterOrderHelper::formatChapterSummary($c))->values(),
        ];
    }

    private function groupChaptersByVolume($chapters, bool $includeStatus = false): array
    {
        $grouped = [];

        foreach ($chapters as $chapter) {
            $volume = $chapter->volume;
            $volumeId = $volume?->id ?? 0;

            if (!isset($grouped[$volumeId])) {
                $grouped[$volumeId] = [
                    'id' => $volume?->id,
                    'novel_id' => $chapter->novel_id,
                    'volume_number' => $volume?->volume_number ?? 0,
                    'title' => $volume?->title ?? 'Uncategorized',
                    'description' => $volume?->description,
                    'chapters' => [],
                ];
            }

            $chapterData = $includeStatus
                ? $this->formatAuthorChapter($chapter)
                : ChapterOrderHelper::formatChapterSummary($chapter);

            $grouped[$volumeId]['chapters'][] = $chapterData;
        }

        return collect($grouped)
            ->sortBy('volume_number')
            ->values()
            ->map(function (array $volume) {
                $volume['chapters'] = collect($volume['chapters'])->values();
                return $volume;
            })
            ->all();
    }

    private function formatAuthorChapter(Chapter $chapter): array
    {
        $data = ChapterOrderHelper::formatChapterSummary($chapter);
        $data['status'] = $chapter->status;
        $data['reviewed_at'] = $chapter->reviewed_at;
        $data['created_at'] = $chapter->created_at;
        $data['published_at'] = $chapter->published_at;

        if ($chapter->relationLoaded('latestReview') && $chapter->latestReview) {
            $data['latest_review'] = $chapter->latestReview;
        }

        return $data;
    }

    private function buildPublishedNavigation(Novel $novel, Chapter $chapter): array
    {
        $previousChapter = ChapterOrderHelper::adjacentPublishedChapter($novel, $chapter, 'previous');
        $nextChapter = ChapterOrderHelper::adjacentPublishedChapter($novel, $chapter, 'next');

        return $this->formatNavigation($novel, $previousChapter, $nextChapter);
    }

    private function buildAuthorNavigation(Novel $novel, Chapter $chapter): array
    {
        $chapters = ChapterOrderHelper::chaptersInReadingOrder(
            $novel,
            Chapter::query()->where('chapters.novel_id', $novel->id)->with('volume:id,volume_number')
        );

        $index = $chapters->search(fn (Chapter $c) => $c->id === $chapter->id);
        $previousChapter = $index !== false && $index > 0 ? $chapters->get($index - 1) : null;
        $nextChapter = $index !== false ? $chapters->get($index + 1) : null;

        return $this->formatNavigation($novel, $previousChapter, $nextChapter);
    }

    private function formatNavigation(Novel $novel, ?Chapter $previousChapter, ?Chapter $nextChapter): array
    {
        if ($novel->uses_volumes) {
            return [
                'previous_chapter' => $previousChapter?->chapter_number,
                'previous_volume' => $previousChapter?->volume?->volume_number,
                'next_chapter' => $nextChapter?->chapter_number,
                'next_volume' => $nextChapter?->volume?->volume_number,
            ];
        }

        return [
            'previous_chapter' => $previousChapter?->chapter_number,
            'next_chapter' => $nextChapter?->chapter_number,
        ];
    }

    /**
     * @return Volume|false|null  Volume model, null for flat novels, false on validation error
     */
    private function resolveVolumeForChapter(Novel $novel, Request $request): Volume|false|null
    {
        if (!$novel->uses_volumes) {
            if ($request->filled('volume_id') || $request->filled('volume_number')) {
                return false;
            }

            return null;
        }

        if ($request->filled('volume_id')) {
            $volume = Volume::where('id', $request->volume_id)->where('novel_id', $novel->id)->first();
            return $volume ?: false;
        }

        if ($request->filled('volume_number')) {
            $volume = Volume::where('novel_id', $novel->id)
                ->where('volume_number', $request->volume_number)
                ->first();
            return $volume ?: false;
        }

        return false;
    }
}
