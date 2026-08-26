<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Helpers\ChapterOrderHelper;
use App\Http\Requests\Volume\StoreVolumeRequest;
use App\Http\Requests\Volume\UpdateVolumeRequest;
use App\Models\Chapter;
use App\Models\Novel;
use App\Models\Volume;
use Illuminate\Http\Request;

class VolumeController extends Controller
{
    /**
     * Public volume list with published chapters only.
     */
    public function index(Novel $novel)
    {
        if (!$novel->uses_volumes) {
            return response()->json([
                'message' => 'This novel does not use volumes',
                'uses_volumes' => false,
                'volumes' => [],
            ]);
        }

        $cacheKey = "volumes_novel_{$novel->id}_published";

        $volumes = CacheHelper::remember($cacheKey, now()->addMinutes(30), function () use ($novel) {
            return $novel->volumes()
                ->with(['publishedChapters.volume'])
                ->orderBy('volume_number')
                ->get()
                ->map(fn (Volume $volume) => $this->formatVolume($volume, publishedOnly: true));
        }, ["volumes_novel_{$novel->id}"]);

        return response()->json([
            'message' => 'Volumes for novel: ' . $novel->title,
            'uses_volumes' => true,
            'novel' => [
                'title' => $novel->title,
                'slug' => $novel->slug,
                'author' => $novel->author,
            ],
            'volumes' => $volumes,
        ]);
    }

    /**
     * Author view of all volumes including unpublished chapters.
     */
    public function authorIndex(Request $request, Novel $novel)
    {
        $user = $request->user();

        if ($novel->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'You can only view your own novel volumes',
            ], 403);
        }

        if (!$novel->uses_volumes) {
            return response()->json([
                'message' => 'This novel does not use volumes',
                'uses_volumes' => false,
                'volumes' => [],
            ]);
        }

        $volumes = $novel->volumes()
            ->with(['chapters.volume', 'chapters.latestReview'])
            ->orderBy('volume_number')
            ->get()
            ->map(fn (Volume $volume) => $this->formatVolume($volume, publishedOnly: false, includeStatus: true));

        return response()->json([
            'message' => 'All volumes for novel: ' . $novel->title,
            'uses_volumes' => true,
            'novel' => [
                'id' => $novel->id,
                'title' => $novel->title,
                'slug' => $novel->slug,
                'author' => $novel->author,
            ],
            'volumes' => $volumes,
        ]);
    }

    public function store(StoreVolumeRequest $request, Novel $novel)
    {
        $user = $request->user();
        if ($novel->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['message' => 'You can only add volumes to your own novels'], 403);
        }

        if (!$novel->uses_volumes) {
            return response()->json(['message' => 'Enable volume mode on this novel first'], 400);
        }

        $data = $request->validated();

        if (!empty($data['volume_number'])) {
            $volumeNumber = $data['volume_number'];
        } else {
            $lastVolume = Volume::where('novel_id', $novel->id)->orderByDesc('volume_number')->first();
            $volumeNumber = $lastVolume ? $lastVolume->volume_number + 1 : 1;
        }

        $existing = Volume::where('novel_id', $novel->id)->where('volume_number', $volumeNumber)->first();
        if ($existing) {
            return response()->json([
                'message' => 'Volume number already exists',
                'existing_volume' => $existing,
            ], 409);
        }

        $volume = Volume::create([
            'novel_id' => $novel->id,
            'volume_number' => $volumeNumber,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
        ]);

        $this->clearVolumeCaches($novel);

        return response()->json([
            'message' => 'Volume created successfully',
            'volume' => $this->formatVolume($volume->fresh(), publishedOnly: false),
        ], 201);
    }

    public function update(UpdateVolumeRequest $request, Novel $novel, Volume $volume)
    {
        $user = $request->user();
        if ($novel->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['message' => 'You can only edit volumes on your own novels'], 403);
        }

        if ($volume->novel_id !== $novel->id) {
            return response()->json(['message' => 'Volume does not belong to this novel'], 404);
        }

        $data = $request->validated();

        if (isset($data['volume_number']) && $data['volume_number'] !== $volume->volume_number) {
            $existing = Volume::where('novel_id', $novel->id)
                ->where('volume_number', $data['volume_number'])
                ->where('id', '!=', $volume->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Volume number already exists',
                    'existing_volume' => $existing,
                ], 409);
            }
        }

        $volume->update($data);
        $this->clearVolumeCaches($novel);

        return response()->json([
            'message' => 'Volume updated successfully',
            'volume' => $this->formatVolume($volume->fresh()->load('chapters.volume'), publishedOnly: false),
        ]);
    }

    public function destroy(Request $request, Novel $novel, Volume $volume)
    {
        $user = $request->user();
        if ($novel->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['message' => 'You can only delete volumes from your own novels'], 403);
        }

        if ($volume->novel_id !== $novel->id) {
            return response()->json(['message' => 'Volume does not belong to this novel'], 404);
        }

        if ($volume->chapters()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a volume that still has chapters. Move or delete chapters first.',
            ], 400);
        }

        $volumeTitle = $volume->title;
        $volumeNumber = $volume->volume_number;
        $volume->delete();

        $this->clearVolumeCaches($novel);

        return response()->json([
            'message' => "Volume '{$volumeTitle}' (#{$volumeNumber}) deleted successfully",
        ]);
    }

    private function formatVolume(Volume $volume, bool $publishedOnly, bool $includeStatus = false): array
    {
        $chapters = $publishedOnly ? $volume->publishedChapters : $volume->chapters;

        if ($chapters->isEmpty() && $volume->relationLoaded('chapters')) {
            $chapters = $publishedOnly
                ? $volume->chapters->filter(fn (Chapter $c) => $c->isPublished())
                : $volume->chapters;
        }

        $formattedChapters = $chapters->sortBy('chapter_number')->map(function (Chapter $chapter) use ($includeStatus) {
            $data = ChapterOrderHelper::formatChapterSummary($chapter);

            if ($includeStatus) {
                $data['status'] = $chapter->status;
                $data['published_at'] = $chapter->published_at;
                $data['created_at'] = $chapter->created_at;
                if ($chapter->relationLoaded('latestReview') && $chapter->latestReview) {
                    $data['latest_review'] = $chapter->latestReview;
                }
            }

            return $data;
        })->values();

        return [
            'id' => $volume->id,
            'novel_id' => $volume->novel_id,
            'volume_number' => $volume->volume_number,
            'title' => $volume->title,
            'description' => $volume->description,
            'chapters' => $formattedChapters,
        ];
    }

    private function clearVolumeCaches(Novel $novel): void
    {
        CacheHelper::clearNovelCaches($novel->id, $novel->slug);
        CacheHelper::flush(["volumes_novel_{$novel->id}"], [
            "volumes_novel_{$novel->id}",
            "volumes_novel_{$novel->id}_published",
            "chapters_novel_{$novel->id}",
            "chapters_novel_{$novel->id}_published",
        ]);
    }
}
