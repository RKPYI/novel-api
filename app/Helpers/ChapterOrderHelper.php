<?php

namespace App\Helpers;

use App\Models\Chapter;
use App\Models\Novel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ChapterOrderHelper
{
    /**
     * Base query for chapters in global reading order.
     */
    public static function readingOrderQuery(Novel $novel, ?Builder $query = null): Builder
    {
        $query = $query ?? Chapter::query()->where('chapters.novel_id', $novel->id);

        return $query
            ->leftJoin('volumes', 'chapters.volume_id', '=', 'volumes.id')
            ->orderByRaw('COALESCE(volumes.volume_number, 0) ASC')
            ->orderBy('chapters.chapter_number', 'asc')
            ->select('chapters.*');
    }

    /**
     * Get all chapters for a novel in reading order.
     */
    public static function chaptersInReadingOrder(Novel $novel, ?Builder $query = null): Collection
    {
        return self::readingOrderQuery($novel, $query)->get();
    }

    /**
     * Get the global position (1-based) of a chapter in reading order.
     */
    public static function globalPosition(Novel $novel, Chapter $chapter): int
    {
        $orderedIds = self::readingOrderQuery($novel)->pluck('chapters.id');

        $position = $orderedIds->search($chapter->id);

        return $position === false ? 0 : $position + 1;
    }

    /**
     * Compare two chapters in reading order. Returns negative if $a before $b.
     */
    public static function compareChapters(Chapter $a, Chapter $b): int
    {
        $volumeA = $a->relationLoaded('volume') ? ($a->volume?->volume_number ?? 0) : ($a->volume()->value('volume_number') ?? 0);
        $volumeB = $b->relationLoaded('volume') ? ($b->volume?->volume_number ?? 0) : ($b->volume()->value('volume_number') ?? 0);

        if ($volumeA !== $volumeB) {
            return $volumeA <=> $volumeB;
        }

        return $a->chapter_number <=> $b->chapter_number;
    }

    /**
     * Find adjacent published chapter in reading order.
     */
    public static function adjacentPublishedChapter(Novel $novel, Chapter $chapter, string $direction): ?Chapter
    {
        $chapters = self::readingOrderQuery(
            $novel,
            Chapter::query()
                ->where('chapters.novel_id', $novel->id)
                ->whereIn('chapters.status', [Chapter::STATUS_APPROVED, Chapter::STATUS_PENDING_UPDATE])
                ->whereNotNull('chapters.published_at')
        )->with('volume:id,volume_number')->get();

        $index = $chapters->search(fn (Chapter $c) => $c->id === $chapter->id);

        if ($index === false) {
            return null;
        }

        $targetIndex = $direction === 'next' ? $index + 1 : $index - 1;

        return $chapters->get($targetIndex);
    }

    /**
     * Format chapter summary for API responses.
     */
    public static function formatChapterSummary(Chapter $chapter): array
    {
        $data = [
            'id' => $chapter->id,
            'novel_id' => $chapter->novel_id,
            'title' => $chapter->title,
            'chapter_number' => $chapter->chapter_number,
            'word_count' => $chapter->word_count,
        ];

        if ($chapter->volume_id) {
            $data['volume_id'] = $chapter->volume_id;
            $data['volume_number'] = $chapter->relationLoaded('volume')
                ? $chapter->volume?->volume_number
                : $chapter->volume()->value('volume_number');
        }

        return $data;
    }

    /**
     * Resolve a chapter for public or author access.
     */
    public static function resolveChapter(
        Novel $novel,
        int $chapterNumber,
        ?int $volumeNumber = null,
        bool $publishedOnly = true
    ): ?Chapter {
        $query = Chapter::where('novel_id', $novel->id)
            ->where('chapter_number', $chapterNumber);

        if ($novel->uses_volumes) {
            if ($volumeNumber === null) {
                return null;
            }

            $query->whereHas('volume', fn (Builder $q) => $q->where('volume_number', $volumeNumber));
        } else {
            $query->whereNull('volume_id');
        }

        if ($publishedOnly) {
            $query->whereIn('status', [Chapter::STATUS_APPROVED, Chapter::STATUS_PENDING_UPDATE])
                ->whereNotNull('published_at');
        }

        return $query->with('volume:id,volume_number,title')->first();
    }
}
