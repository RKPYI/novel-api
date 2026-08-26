<?php

namespace App\Http\Controllers;

use App\Helpers\ChapterOrderHelper;
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
            ->with(['chapter.volume:id,volume_number'])
            ->first();

        if (!$progress) {
            return response()->json([
                'novel_slug' => $novel->slug,
                'user_id' => $userId,
                'current_chapter' => null,
                'progress_percentage' => 0,
                'last_read_at' => null,
                'total_chapters' => $novel->total_chapters ?? 0,
                'uses_volumes' => $novel->uses_volumes,
            ]);
        }

        return response()->json([
            'novel_slug' => $novel->slug,
            'user_id' => $userId,
            'current_chapter' => $this->formatProgressChapter($progress->chapter),
            'progress_percentage' => $this->calculateProgressPercentage($novel, $progress->chapter),
            'last_read_at' => $progress->updated_at,
            'total_chapters' => $novel->total_chapters ?? 0,
            'uses_volumes' => $novel->uses_volumes,
        ]);
    }

    /**
     * Update reading progress
     */
    public function updateProgress(Request $request): JsonResponse
    {
        $request->validate([
            'novel_slug' => 'required|string',
            'chapter_id' => 'nullable|integer|exists:chapters,id',
            'chapter_number' => 'nullable|integer',
            'volume_number' => 'nullable|integer|min:1',
        ]);

        if (!$request->filled('chapter_id') && !$request->filled('chapter_number')) {
            return response()->json(['error' => 'chapter_id or chapter_number is required'], 422);
        }

        $userId = $request->user()->id;
        $novelSlug = $request->input('novel_slug');

        $novel = Novel::where('slug', $novelSlug)->first();
        if (!$novel) {
            return response()->json(['error' => 'Novel not found'], 404);
        }

        if ($request->filled('chapter_id')) {
            $chapter = Chapter::where('id', $request->chapter_id)
                ->where('novel_id', $novel->id)
                ->with('volume:id,volume_number')
                ->first();
        } else {
            $chapter = ChapterOrderHelper::resolveChapter(
                $novel,
                (int) $request->input('chapter_number'),
                $request->filled('volume_number') ? (int) $request->input('volume_number') : null,
                publishedOnly: false
            );
        }

        if (!$chapter) {
            return response()->json(['error' => 'Chapter not found'], 404);
        }

        $currentProgress = ReadingProgress::where('user_id', $userId)
            ->where('novel_id', $novel->id)
            ->with(['chapter.volume:id,volume_number'])
            ->first();

        $shouldUpdateProgress = false;
        $message = 'Current reading position retrieved';

        if (!$currentProgress) {
            $shouldUpdateProgress = true;
            $message = 'Reading progress created successfully';
        } elseif ($this->isChapterAhead($novel, $chapter, $currentProgress->chapter)) {
            $shouldUpdateProgress = true;
            $message = 'Reading progress updated successfully';
        } else {
            $message = 'Reading position noted (progress preserved)';
        }

        if ($shouldUpdateProgress) {
            $progress = ReadingProgress::updateOrCreate(
                [
                    'user_id' => $userId,
                    'novel_id' => $novel->id,
                ],
                [
                    'chapter_id' => $chapter->id,
                    'last_read_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $progress->load(['chapter.volume:id,volume_number']);
        } else {
            $progress = $currentProgress;
        }

        return response()->json([
            'message' => $message,
            'progress' => [
                'novel_slug' => $novel->slug,
                'user_id' => $userId,
                'current_chapter' => $this->formatProgressChapter($progress->chapter),
                'requested_chapter' => $this->formatProgressChapter($chapter),
                'progress_percentage' => $this->calculateProgressPercentage($novel, $progress->chapter),
                'last_read_at' => $progress->updated_at,
                'total_chapters' => $novel->total_chapters ?? 0,
                'uses_volumes' => $novel->uses_volumes,
                'progress_updated' => $shouldUpdateProgress,
            ],
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
                'novel:id,title,author,cover_image,slug,total_chapters,uses_volumes',
                'chapter.volume:id,volume_number',
            ])
            ->orderBy('updated_at', 'desc')
            ->get();

        $formattedProgress = $progressList->map(function ($progress) {
            $totalChapters = $progress->novel->total_chapters ?? 0;

            return [
                'novel' => $progress->novel,
                'current_chapter' => $this->formatProgressChapter($progress->chapter),
                'progress_percentage' => $this->calculateProgressPercentage($progress->novel, $progress->chapter),
                'last_read_at' => $progress->updated_at,
                'total_chapters' => $totalChapters,
                'uses_volumes' => (bool) $progress->novel->uses_volumes,
            ];
        });

        return response()->json([
            'user_id' => $userId,
            'reading_progress' => $formattedProgress,
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
                'message' => 'Reading progress deleted successfully',
            ]);
        }

        return response()->json([
            'message' => 'No reading progress found to delete',
        ], 404);
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

        $novel = Novel::where('slug', $novelSlug)->first();
        if (!$novel) {
            return response()->json(['error' => 'Novel not found'], 404);
        }

        $existingProgress = ReadingProgress::where('user_id', $userId)
            ->where('novel_id', $novel->id)
            ->first();

        if ($existingProgress) {
            $existingProgress->load(['chapter.volume:id,volume_number']);

            return response()->json([
                'message' => 'Reading progress already exists for this novel',
                'progress' => [
                    'novel_slug' => $novel->slug,
                    'user_id' => $userId,
                    'current_chapter' => $this->formatProgressChapter($existingProgress->chapter),
                    'progress_percentage' => $this->calculateProgressPercentage($novel, $existingProgress->chapter),
                    'last_read_at' => $existingProgress->updated_at,
                ],
            ], 409);
        }

        $firstChapter = ChapterOrderHelper::readingOrderQuery(
            $novel,
            Chapter::query()
                ->where('chapters.novel_id', $novel->id)
                ->whereIn('chapters.status', [Chapter::STATUS_APPROVED, Chapter::STATUS_PENDING_UPDATE])
                ->whereNotNull('chapters.published_at')
        )->with('volume:id,volume_number')->first();

        if (!$firstChapter) {
            return response()->json(['error' => 'No chapters found for this novel'], 404);
        }

        $progress = ReadingProgress::create([
            'user_id' => $userId,
            'novel_id' => $novel->id,
            'chapter_id' => $firstChapter->id,
            'last_read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $totalChapters = $novel->total_chapters ?? 0;

        return response()->json([
            'message' => 'Reading progress created successfully',
            'progress' => [
                'novel_slug' => $novel->slug,
                'user_id' => $userId,
                'current_chapter' => $this->formatProgressChapter($firstChapter),
                'progress_percentage' => $totalChapters > 0
                    ? round((1 / $totalChapters) * 100, 2)
                    : 0,
                'last_read_at' => $progress->updated_at,
                'total_chapters' => $totalChapters,
            ],
        ], 201);
    }

    private function formatProgressChapter(?Chapter $chapter): ?array
    {
        if (!$chapter) {
            return null;
        }

        $data = [
            'id' => $chapter->id,
            'novel_id' => $chapter->novel_id,
            'chapter_number' => $chapter->chapter_number,
            'title' => $chapter->title,
        ];

        if ($chapter->volume_id) {
            $data['volume_id'] = $chapter->volume_id;
            $data['volume_number'] = $chapter->relationLoaded('volume')
                ? $chapter->volume?->volume_number
                : $chapter->volume()->value('volume_number');
        }

        return $data;
    }

    private function calculateProgressPercentage(Novel $novel, ?Chapter $chapter): float
    {
        $totalChapters = $novel->total_chapters ?? 0;

        if (!$chapter || $totalChapters <= 0) {
            return 0;
        }

        $position = ChapterOrderHelper::globalPosition($novel, $chapter);

        return $position > 0 ? round(($position / $totalChapters) * 100, 2) : 0;
    }

    private function isChapterAhead(Novel $novel, Chapter $target, Chapter $current): bool
    {
        return ChapterOrderHelper::compareChapters($target, $current) > 0;
    }
}
