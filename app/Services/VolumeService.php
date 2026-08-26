<?php

namespace App\Services;

use App\Helpers\CacheHelper;
use App\Helpers\ChapterOrderHelper;
use App\Models\Chapter;
use App\Models\Novel;
use App\Models\Volume;
use Illuminate\Support\Facades\DB;

class VolumeService
{
    public function enableVolumes(Novel $novel): void
    {
        if ($novel->uses_volumes) {
            return;
        }

        DB::transaction(function () use ($novel) {
            $hasChapters = Chapter::where('novel_id', $novel->id)->exists();

            if ($hasChapters) {
                $volume = Volume::firstOrCreate(
                    ['novel_id' => $novel->id, 'volume_number' => 1],
                    ['title' => 'Volume 1']
                );

                Chapter::where('novel_id', $novel->id)
                    ->whereNull('volume_id')
                    ->update(['volume_id' => $volume->id]);
            }

            $novel->update(['uses_volumes' => true]);
        });

        $this->clearCaches($novel);
    }

    public function disableVolumes(Novel $novel): void
    {
        if (!$novel->uses_volumes) {
            return;
        }

        DB::transaction(function () use ($novel) {
            $chapters = ChapterOrderHelper::chaptersInReadingOrder(
                $novel,
                Chapter::query()->with('volume')
            );

            $number = 1;
            foreach ($chapters as $chapter) {
                $chapter->update([
                    'chapter_number' => $number++,
                    'volume_id' => null,
                ]);
            }

            Volume::where('novel_id', $novel->id)->delete();
            $novel->update(['uses_volumes' => false]);
        });

        $this->clearCaches($novel);
    }

    public function syncVolumeMode(Novel $novel, bool $usesVolumes): void
    {
        if ($usesVolumes && !$novel->uses_volumes) {
            $this->enableVolumes($novel->fresh());
        } elseif (!$usesVolumes && $novel->uses_volumes) {
            $this->disableVolumes($novel->fresh());
        }
    }

    /**
     * Move a chapter to another volume and renumber chapters in both volumes.
     */
    public function moveChapterToVolume(
        Chapter $chapter,
        Volume $targetVolume,
        ?int $targetChapterNumber = null
    ): Chapter {
        $novel = $chapter->novel;

        if (!$novel || !$novel->uses_volumes) {
            throw new \InvalidArgumentException('This novel does not use volumes.');
        }

        if ($targetVolume->novel_id !== $novel->id) {
            throw new \InvalidArgumentException('Target volume does not belong to this novel.');
        }

        if (!$chapter->volume_id) {
            throw new \InvalidArgumentException('Chapter is not assigned to a volume.');
        }

        $sourceVolumeId = $chapter->volume_id;

        DB::transaction(function () use (
            $chapter,
            $targetVolume,
            $targetChapterNumber,
            $sourceVolumeId
        ) {
            $chapter->update([
                'volume_id' => $targetVolume->id,
                'chapter_number' => -($chapter->id + 10000),
            ]);

            if ($sourceVolumeId !== $targetVolume->id) {
                $this->renumberVolumeChapters($sourceVolumeId);
            }

            $targetChapters = Chapter::where('volume_id', $targetVolume->id)
                ->where('id', '!=', $chapter->id)
                ->orderBy('chapter_number')
                ->orderBy('id')
                ->get();

            $insertAt = $targetChapterNumber !== null
                ? max(1, min($targetChapterNumber, $targetChapters->count() + 1))
                : $targetChapters->count() + 1;

            $ordered = $targetChapters->all();
            array_splice($ordered, $insertAt - 1, 0, [$chapter->fresh()]);

            $this->applySequentialNumbers($ordered);
        });

        $this->clearCaches($novel);

        return $chapter->fresh(['volume']);
    }

    /**
     * Move multiple chapters to a target volume, preserving their relative order.
     *
     * @param  array<int>  $chapterIds
     */
    public function bulkMoveChaptersToVolume(
        Novel $novel,
        array $chapterIds,
        Volume $targetVolume,
        ?int $targetChapterNumber = null
    ): int {
        if (!$novel->uses_volumes) {
            throw new \InvalidArgumentException('This novel does not use volumes.');
        }

        if ($targetVolume->novel_id !== $novel->id) {
            throw new \InvalidArgumentException('Target volume does not belong to this novel.');
        }

        $chapters = Chapter::whereIn('id', $chapterIds)
            ->where('novel_id', $novel->id)
            ->with('volume')
            ->get();

        if ($chapters->count() !== count($chapterIds)) {
            throw new \InvalidArgumentException('One or more chapters do not belong to this novel.');
        }

        if ($chapters->contains(fn (Chapter $chapter) => !$chapter->volume_id)) {
            throw new \InvalidArgumentException('All chapters must be assigned to a volume.');
        }

        $sorted = $chapters->sort(fn (Chapter $a, Chapter $b) => ChapterOrderHelper::compareChapters($a, $b))->values();
        $movingIds = $sorted->pluck('id');
        $sourceVolumeIds = $sorted->pluck('volume_id')->unique()->filter(
            fn (int $volumeId) => $volumeId !== $targetVolume->id
        );

        DB::transaction(function () use (
            $sorted,
            $targetVolume,
            $targetChapterNumber,
            $sourceVolumeIds,
            $movingIds
        ) {
            foreach ($sorted as $chapter) {
                $chapter->update([
                    'volume_id' => $targetVolume->id,
                    'chapter_number' => -($chapter->id + 10000),
                ]);
            }

            foreach ($sourceVolumeIds as $volumeId) {
                $this->renumberVolumeChapters($volumeId);
            }

            $targetChapters = Chapter::where('volume_id', $targetVolume->id)
                ->whereNotIn('id', $movingIds)
                ->orderBy('chapter_number')
                ->orderBy('id')
                ->get();

            $insertAt = $targetChapterNumber !== null
                ? max(1, min($targetChapterNumber, $targetChapters->count() + 1))
                : $targetChapters->count() + 1;

            $ordered = $targetChapters->all();
            array_splice($ordered, $insertAt - 1, 0, $sorted->all());

            $this->applySequentialNumbers($ordered);
        });

        $this->clearCaches($novel);

        return $sorted->count();
    }

    /**
     * Renumber all chapters in a volume to 1..N preserving current order.
     */
    public function renumberVolumeChapters(int $volumeId): void
    {
        $chapters = Chapter::where('volume_id', $volumeId)
            ->orderBy('chapter_number')
            ->orderBy('id')
            ->get()
            ->all();

        $this->applySequentialNumbers($chapters);
    }

    /**
     * @param  array<int, Chapter>  $chapters
     */
    private function applySequentialNumbers(array $chapters): void
    {
        foreach ($chapters as $index => $chapter) {
            $chapter->update(['chapter_number' => -($index + 1)]);
        }

        foreach ($chapters as $index => $chapter) {
            $chapter->update(['chapter_number' => $index + 1]);
        }
    }

    public function clearNovelVolumeCaches(Novel $novel): void
    {
        $this->clearCaches($novel);
    }

    private function clearCaches(Novel $novel): void
    {
        CacheHelper::clearNovelCaches($novel->id, $novel->slug);
        CacheHelper::flush(["volumes_novel_{$novel->id}"], ["volumes_novel_{$novel->id}"]);
    }
}
