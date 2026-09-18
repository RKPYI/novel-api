<?php

namespace App\Http\Controllers;

use App\Helpers\ChapterOrderHelper;
use App\Models\GlossaryFact;
use App\Models\Novel;
use App\Services\OpenRouterGlossaryClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StoryCompanionController extends Controller
{
    public function ask(Request $request, Novel $novel, OpenRouterGlossaryClient $client): JsonResponse
    {
        $data = $request->validate([
            'question' => 'required|string|max:1000',
            'history' => 'nullable|array|max:6',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string|max:2000',
        ]);

        $boundary = $request->user()->readingProgress()
            ->where('novel_id', $novel->id)
            ->with('chapter.volume')
            ->first()?->chapter;

        if (!$boundary) {
            return response()->json([
                'answer' => 'Read a chapter first so I can keep the answer spoiler-safe.',
                'availability' => 'not_available',
                'citations' => [],
                'boundary' => null,
            ]);
        }

        $chapters = ChapterOrderHelper::chaptersInReadingOrder(
            $novel,
            $novel->publishedChapters()->getQuery()
        )->filter(fn ($chapter) => ChapterOrderHelper::compareChapters($chapter, $boundary) <= 0);
        $facts = GlossaryFact::with(['entity', 'evidence.chapter'])
            ->where('novel_id', $novel->id)
            ->where('status', 'active')
            ->get()
            ->filter(fn ($fact) => $fact->evidence->contains(fn ($evidence) => $evidence->chapter && ChapterOrderHelper::compareChapters($evidence->chapter, $boundary) <= 0));

        $context = $this->buildContext($facts, $chapters);
        $boundaryLabel = 'Chapter '.$boundary->chapter_number.($boundary->title ? ' - '.$boundary->title : '');
        $result = $client->answer($data['question'], $context, $boundaryLabel, $data['history'] ?? []);
        $result['citations'] = $this->validCitations($result['citations'], $context, $chapters);
        $result['boundary'] = [
            'id' => $boundary->id,
            'chapter_number' => $boundary->chapter_number,
            'title' => $boundary->title,
            'volume_number' => $boundary->volume?->volume_number,
        ];

        return response()->json($result);
    }

    private function buildContext(Collection $facts, Collection $chapters): string
    {
        $parts = [];
        foreach ($facts as $fact) {
            foreach ($fact->evidence as $evidence) {
                if ($evidence->chapter) {
                    $parts[] = sprintf(
                        '[FACT fact_id=%d chapter_id=%d chapter_number=%d] %s: %s — "%s"',
                        $fact->id,
                        $evidence->chapter->id,
                        $evidence->chapter->chapter_number,
                        $fact->entity->canonical_name,
                        $fact->value,
                        $evidence->quote,
                    );
                }
            }
        }

        foreach ($chapters as $chapter) {
            $parts[] = sprintf(
                '[CHAPTER chapter_id=%d chapter_number=%d] %s',
                $chapter->id,
                $chapter->chapter_number,
                mb_substr(strip_tags($chapter->content), 0, 4000),
            );
        }

        return mb_substr(implode("\n\n", $parts), 0, 60000);
    }

    private function validCitations(array $citations, string $context, Collection $chapters): array
    {
        $allowedChapterIds = $chapters->pluck('id')->map(fn ($id) => (int) $id)->all();

        return collect($citations)->filter(function ($citation) use ($context, $allowedChapterIds) {
            return is_array($citation)
                && in_array((int) ($citation['chapter_id'] ?? 0), $allowedChapterIds, true)
                && is_string($citation['quote'] ?? null)
                && $citation['quote'] !== ''
                && str_contains($context, $citation['quote']);
        })->map(function ($citation) use ($chapters) {
            $chapter = $chapters->firstWhere('id', (int) $citation['chapter_id']);
            return [
                'chapter_id' => $chapter->id,
                'chapter_number' => $chapter->chapter_number,
                'quote' => $citation['quote'],
            ];
        })->values()->all();
    }
}
