<?php

namespace App\Http\Controllers;

use App\Jobs\ExtractChapterGlossary;
use App\Helpers\ChapterOrderHelper;
use App\Models\Chapter;
use App\Models\GlossaryFact;
use App\Models\GlossaryExtractionRun;
use App\Models\Novel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class GlossaryController extends Controller
{
    public function novels(Request $request): JsonResponse
    {
        $novels = Novel::query()
            ->withCount(['publishedChapters', 'glossaryEntities', 'glossaryFacts'])
            ->withMax('glossaryFacts', 'updated_at')
            ->withCount([
                'glossaryFacts as pending_glossary_facts_count' => fn ($query) => $query->where('status', 'pending_review'),
            ])
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->string('search').'%'))
            ->latest('updated_at')
            ->paginate(min($request->integer('per_page', 20), 50));

        $novels->getCollection()->transform(function (Novel $novel) {
            $latestRun = GlossaryExtractionRun::where('novel_id', $novel->id)->latest()->first();
            $staleAfter = now()->subSeconds((int) config('services.openrouter.run_stale_after', 600));
            $isStale = $latestRun
                && $latestRun->status === 'running'
                && $latestRun->started_at
                && $latestRun->started_at->lt($staleAfter);
            $novel->setAttribute('glossary_scan_status', $isStale ? 'stale' : ($latestRun?->status ?? 'not_started'));
            $novel->setAttribute('glossary_last_scan_at', $latestRun?->finished_at);
            $novel->setAttribute(
                'glossary_last_scan_error',
                $isStale ? 'This extraction run stopped responding and needs a retry.' : $latestRun?->error
            );

            return $novel;
        });

        return response()->json($novels);
    }

    public function index(Request $request, Novel $novel): JsonResponse
    {
        $boundary = $this->readerBoundary($request, $novel);
        $facts = GlossaryFact::with(['entity', 'evidence.chapter.volume'])
            ->where('novel_id', $novel->id)
            ->where('status', 'active')
            ->get()
            ->filter(fn (GlossaryFact $fact) => $this->factAllowed($fact, $boundary))
            ->values();

        return response()->json([
            'novel' => ['id' => $novel->id, 'title' => $novel->title, 'slug' => $novel->slug],
            'max_chapter' => $boundary?->id,
            'boundary' => $boundary ? [
                'id' => $boundary->id,
                'chapter_number' => $boundary->chapter_number,
                'title' => $boundary->title,
                'volume_number' => $boundary->volume?->volume_number,
            ] : null,
            'facts' => $facts,
        ]);
    }

    public function show(Request $request, Novel $novel, GlossaryFact $fact): JsonResponse
    {
        if ($fact->novel_id !== $novel->id || $fact->status !== 'active') {
            return response()->json(['message' => 'Glossary fact not found'], 404);
        }

        $boundary = $this->readerBoundary($request, $novel);
        $fact->load(['entity', 'evidence.chapter.volume']);

        if (!$this->factAllowed($fact, $boundary)) {
            return response()->json(['message' => 'Glossary fact is not available at your reading progress'], 404);
        }

        return response()->json(['fact' => $fact]);
    }

    public function startScan(Request $request, Novel $novel): JsonResponse
    {
        $chapters = $this->publishedChaptersInOrder($novel);

        foreach ($chapters as $chapter) {
            ExtractChapterGlossary::dispatch($chapter->id);
        }

        return response()->json([
            'message' => 'Glossary scan queued',
            'queued_chapters' => $chapters->count(),
            'skipped_chapters' => 0,
            'mode' => 'full',
        ], 202);
    }

    public function resumeScan(Request $request, Novel $novel): JsonResponse
    {
        $chapters = $this->publishedChaptersInOrder($novel);
        $chaptersToQueue = $chapters->filter(
            fn (Chapter $chapter): bool => $chapter->latestGlossaryExtractionRun?->status !== 'completed'
        )->values();

        foreach ($chaptersToQueue as $chapter) {
            ExtractChapterGlossary::dispatch($chapter->id);
        }

        return response()->json([
            'message' => 'Incomplete glossary chapters queued',
            'queued_chapters' => $chaptersToQueue->count(),
            'skipped_chapters' => $chapters->count() - $chaptersToQueue->count(),
            'mode' => 'resume',
        ], 202);
    }

    private function publishedChaptersInOrder(Novel $novel): Collection
    {
        return ChapterOrderHelper::chaptersInReadingOrder(
            $novel,
            $novel->publishedChapters()
                ->with('latestGlossaryExtractionRun')
                ->getQuery()
        );
    }

    public function pending(Request $request, Novel $novel): JsonResponse
    {
        $facts = GlossaryFact::with(['entity', 'evidence.chapter'])
            ->where('novel_id', $novel->id)
            ->where('status', 'pending_review')
            ->when($request->filled('type'), fn ($query) => $query->whereHas('entity', fn ($entity) => $entity->where('type', $request->string('type'))))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($nested) use ($search) {
                    $nested->where('value', 'like', $search)
                        ->orWhere('fact_key', 'like', $search)
                        ->orWhereHas('entity', fn ($entity) => $entity->where('canonical_name', 'like', $search));
                });
            })
            ->latest('updated_at')
            ->paginate(50);

        return response()->json($facts);
    }

    public function bulkReview(Request $request, Novel $novel): JsonResponse
    {
        $data = $request->validate([
            'fact_ids' => 'required|array|min:1|max:100',
            'fact_ids.*' => 'integer|distinct',
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:2000',
        ]);

        $facts = GlossaryFact::where('novel_id', $novel->id)
            ->whereIn('id', $data['fact_ids'])
            ->where('status', 'pending_review')
            ->get();
        $processed = [];

        \DB::transaction(function () use ($facts, $data, $request, &$processed) {
            foreach ($facts as $fact) {
                $fact->update(['status' => $data['action'] === 'approve' ? 'active' : 'rejected']);
                $fact->reviews()->create([
                    'reviewer_id' => $request->user()->id,
                    'action' => $data['action'],
                    'notes' => $data['notes'] ?? null,
                ]);
                $processed[] = $fact->id;
            }
        });

        return response()->json([
            'message' => 'Glossary review decisions applied',
            'processed_ids' => $processed,
            'skipped_ids' => array_values(array_diff($data['fact_ids'], $processed)),
        ]);
    }

    public function review(Request $request, Novel $novel, GlossaryFact $fact): JsonResponse
    {
        if ($fact->novel_id !== $novel->id) {
            return response()->json(['message' => 'Glossary fact not found'], 404);
        }

        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:2000',
        ]);

        $fact->update(['status' => $data['action'] === 'approve' ? 'active' : 'rejected']);
        $fact->reviews()->create([
            'reviewer_id' => $request->user()->id,
            'action' => $data['action'],
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['fact' => $fact->fresh(['entity', 'evidence'])]);
    }

    private function readerBoundary(Request $request, Novel $novel)
    {
        if ($request->user()) {
            return $request->user()->readingProgress()
                ->where('novel_id', $novel->id)
                ->with('chapter.volume')
                ->first()?->chapter;
        }

        $chapterId = $request->integer('max_chapter_id');
        return $chapterId ? $novel->publishedChapters()->whereKey($chapterId)->with('volume')->first() : null;
    }

    private function factAllowed(GlossaryFact $fact, $boundary): bool
    {
        if (!$boundary) {
            return false;
        }

        $boundary->loadMissing('volume');
        $evidence = $fact->evidence->filter(function ($item) use ($boundary) {
            $chapter = $item->chapter;
            return $chapter && ChapterOrderHelper::compareChapters($chapter, $boundary) <= 0;
        });

        return $evidence->isNotEmpty();
    }
}
