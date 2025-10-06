<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChapterController extends Controller
{
    /**
     * Display a listing of chapters for a novel.
     */
    public function index(\App\Models\Novel $novel)
    {
        $chapters = \App\Models\Chapter::where('novel_id', $novel->id)
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
    public function store(Request $request, \App\Models\Novel $novel)
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
            $lastChapter = \App\Models\Chapter::where('novel_id', $novel->id)
                ->orderBy('chapter_number', 'desc')
                ->first();
            $chapterNumber = $lastChapter ? $lastChapter->chapter_number + 1 : 1;
        } else {
            $chapterNumber = $request->chapter_number;

            // Check if chapter number already exists
            $existingChapter = \App\Models\Chapter::where('novel_id', $novel->id)
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

        $chapter = \App\Models\Chapter::create([
            'novel_id' => $novel->id,
            'title' => $request->title,
            'content' => $request->content,
            'chapter_number' => $chapterNumber,
            'word_count' => $wordCount,
            'is_free' => $request->boolean('is_free', true),
            'published_at' => $request->published_at ? now() : null,
        ]);

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
    public function show(\App\Models\Novel $novel, $chapterNumber)
    {
        $chapter = \App\Models\Chapter::where('novel_id', $novel->id)
            ->where('chapter_number', $chapterNumber)
            ->first();

        if (!$chapter) {
            return response()->json([
                'message' => 'Chapter not found'
            ], 404);
        }

        // Increment view count
        $chapter->increment('views');

        // Get previous chapter (chapter with smaller chapter_number)
        $previousChapter = \App\Models\Chapter::where('novel_id', $novel->id)
            ->where('chapter_number', '<', $chapter->chapter_number)
            ->orderBy('chapter_number', 'desc')
            ->first();

        // Get next chapter (chapter with larger chapter_number)
        $nextChapter = \App\Models\Chapter::where('novel_id', $novel->id)
            ->where('chapter_number', '>', $chapter->chapter_number)
            ->orderBy('chapter_number', 'asc')
            ->first();

        // Add navigation info to chapter data
        $chapterData = $chapter->toArray();
        $chapterData['previous_chapter'] = $previousChapter ? $previousChapter->chapter_number : null;
        $chapterData['next_chapter'] = $nextChapter ? $nextChapter->chapter_number : null;

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
    public function update(Request $request, \App\Models\Novel $novel, \App\Models\Chapter $chapter)
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
            $existingChapter = \App\Models\Chapter::where('novel_id', $novel->id)
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

        $chapter->update($updateData);

        return response()->json([
            'message' => 'Chapter updated successfully',
            'chapter' => $chapter->fresh()
        ]);
    }

    /**
     * Remove the specified chapter.
     */
    public function destroy(Request $request, \App\Models\Novel $novel, \App\Models\Chapter $chapter)
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

        return response()->json([
            'message' => "Chapter '{$chapterTitle}' (#{$chapterNumber}) deleted successfully"
        ]);
    }

    /**
     * Send notifications to users about new chapter
     */
    private function notifyUsersAboutNewChapter(\App\Models\Novel $novel, \App\Models\Chapter $chapter)
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
