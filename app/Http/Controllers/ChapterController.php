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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
