<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Novel;
use App\Models\Genre;
use App\Models\ReadingProgress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Helpers\CacheHelper;
use App\Helpers\ImageUploadHelper;

class NovelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Unique cache key for this exact request (filters + pagination)
        $cacheKey = 'novels_index_' . md5($request->fullUrl());

        $novels = CacheHelper::remember($cacheKey, now()->addMinutes(10), function () use ($request) {
            Log::info('CACHE MISS - Running query for: ' . $request->fullUrl());
            $query = Novel::with(['genres']);

            // Filter by genre
            if ($request->has('genre')) {
                $query->whereHas('genres', function ($q) use ($request) {
                    $q->where('slug', $request->genre);
                });
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Sort by various criteria
            $sortBy = $request->get('sort_by', 'updated_at');
            $sortOrder = $request->get('sort_order', 'desc');

            switch ($sortBy) {
                case 'popular':
                    $query->orderBy('views', $sortOrder);
                    break;
                case 'rating':
                    $query->orderBy('rating', $sortOrder);
                    break;
                case 'latest':
                    $query->orderBy('created_at', $sortOrder);
                    break;
                case 'updated':
                    $query->orderBy('updated_at', $sortOrder);
                    break;
                default:
                    $query->orderBy($sortBy, $sortOrder);
            }

            return $query->paginate(12);
        });

        return response()->json([
            'message' => 'List of novels',
            'novels' => $novels
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'nullable|string|max:255', // Optional - defaults to user's name
            'description' => 'nullable|string',
            'cover_image' => 'nullable|url',
            'status' => 'in:ongoing,completed,hiatus',
            'genres' => 'array',
            'genres.*' => 'exists:genres,id'
        ]);

        $novel = Novel::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'author' => $request->author ?? $request->user()->name, // Use provided author name or user's name
            'description' => $request->description,
            'cover_image' => $request->cover_image,
            'status' => $request->status ?? 'ongoing'
        ]);

        // Attach genres if provided
        if ($request->has('genres')) {
            $novel->genres()->attach($request->genres);
        }

        return response()->json([
            'message' => 'Novel created successfully',
            'novel' => $novel->load('genres')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $slug)
    {
        // Don't cache the show endpoint since views are incremented on every request
        // and need to be real-time for low-traffic novels
        $novel = Novel::with(['genres', 'chapters' => function($query) {
            $query->select('id', 'novel_id', 'chapter_number', 'title', 'word_count')->orderBy('chapter_number');
        }])->where('slug', $slug)->first();

        if (!$novel) {
            return response()->json([
                'message' => 'Novel not found'
            ], 404);
        }

        // Increment views count atomically without updating timestamps
        $novel->timestamps = false;
        $novel->increment('views');
        $novel->timestamps = true;

        $novel->refresh();

        return response()->json([
            'message' => 'Novel details',
            'novel' => $novel
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $slug)
    {
        $novel = Novel::where('slug', $slug)->first();
        if (!$novel) {
            return response()->json([
                'message' => 'Novel not found'
            ], 404);
        }

        // Check ownership (only owner or admin can update)
        if ($novel->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'You can only edit your own novels'
            ], 403);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'author' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|url',
            'status' => 'sometimes|in:ongoing,completed,hiatus',
            'genres' => 'sometimes|array',
            'genres.*' => 'exists:genres,id'
        ]);

        $novel->update(array_filter([
            'title' => $request->title,
            'author' => $request->author,
            'description' => $request->description,
            'cover_image' => $request->cover_image,
            'status' => $request->status
        ], function($value) {
            return $value !== null;
        }));

        // Update genres if provided
        if ($request->has('genres')) {
            $novel->genres()->sync($request->genres);
        }

        return response()->json([
            'message' => 'Novel updated successfully',
            'novel' => $novel->load('genres')
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $slug)
    {
        $novel = Novel::where('slug', $slug)->first();
        if (!$novel) {
            return response()->json([
                'message' => 'Novel not found'
            ], 404);
        }

        // Check ownership (only owner or admin can delete)
        if ($novel->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'You can only delete your own novels'
            ], 403);
        }

        // Delete novel's cover image and directory
        ImageUploadHelper::deleteNovelDirectory($novel->slug);

        $novel->delete();

        // Clear related caches using CacheHelper
        CacheHelper::clearNovelCaches($novel->id, $novel->slug);

        return response()->json([
            'message' => 'Novel deleted successfully'
        ], 200);
    }

    /**
     * Search novels by title or author.
     */
    public function search(Request $request)
    {
        $query = $request->query('q');

        if (!$query) {
            return response()->json([
                'message' => 'Search query is required',
                'novels' => []
            ], 400);
        }

        // Cache search results for 15 minutes
        $cacheKey = 'novel_search_' . md5(strtolower($query));

        $novels = CacheHelper::remember($cacheKey, now()->addMinutes(15), function () use ($query) {
            return Novel::with('genres')
                ->where('title', 'LIKE', '%' . $query . '%')
                ->orWhere('author', 'LIKE', '%' . $query . '%')
                ->orWhere('description', 'LIKE', '%' . $query . '%')
                ->limit(10)
                ->get();
        }, ['novels', 'novels-search']);

        return response()->json([
            'message' => 'Search results for: ' . $query,
            'novels' => $novels,
        ]);
    }

    /**
     * Get popular novels.
     */
    public function popular()
    {
        // Cache popular novels for 5 minutes (changes frequently due to views)
        $novels = CacheHelper::remember('novels_popular', now()->addMinutes(5), function () {
            return Novel::with('genres')
                ->orderBy('views', 'desc')
                ->limit(12)
                ->get();
        }, ['novels', 'novels-popular']);

        return response()->json([
            'message' => 'Popular novels',
            'novels' => $novels
        ]);
    }

    /**
     * Get latest novels.
     */
    public function latest()
    {
        // Cache latest novels for 10 minutes
        $novels = CacheHelper::remember('novels_latest', now()->addMinutes(10), function () {
            return Novel::with('genres')
                ->orderBy('created_at', 'desc')
                ->limit(12)
                ->get();
        }, ['novels', 'novels-latest']);

        return response()->json([
            'message' => 'Latest novels',
            'novels' => $novels
        ]);
    }

    /**
     * Get recently updated novels (novels with newest chapters).
     */
    public function recentlyUpdated(Request $request)
    {
        // Get limit from request, default to 20, max 50
        $limit = min($request->get('limit', 20), 50);

        // Cache recently updated for 10 minutes
        $cacheKey = 'novels_recently_updated_' . $limit;

        $novels = CacheHelper::remember($cacheKey, now()->addMinutes(10), function () use ($limit) {
            // Get novels ordered by their most recent chapter's created_at
            // Include latest chapter info using subqueries
            return Novel::with(['genres'])
                ->addSelect([
                    'novels.*',
                    'latest_chapter_created_at' => \App\Models\Chapter::select('created_at')
                        ->whereColumn('novel_id', 'novels.id')
                        ->orderBy('created_at', 'desc')
                        ->limit(1),
                    'latest_chapter_number' => \App\Models\Chapter::select('chapter_number')
                        ->whereColumn('novel_id', 'novels.id')
                        ->orderBy('created_at', 'desc')
                        ->limit(1),
                    'latest_chapter_title' => \App\Models\Chapter::select('title')
                        ->whereColumn('novel_id', 'novels.id')
                        ->orderBy('created_at', 'desc')
                        ->limit(1),
                    'latest_chapter_id' => \App\Models\Chapter::select('id')
                        ->whereColumn('novel_id', 'novels.id')
                        ->orderBy('created_at', 'desc')
                        ->limit(1)
                ])
                ->whereHas('chapters') // Only novels with at least one chapter
                ->orderBy('latest_chapter_created_at', 'desc')
                ->limit($limit)
                ->get();
        }, ['novels', 'novels-updated']);

        return response()->json([
            'message' => 'Recently updated novels (with new chapters)',
            'novels' => $novels
        ]);
    }

    /**
     * Get all genres.
     */
    public function genres()
    {
        // Cache genres for 1 hour (rarely changes)
        $genres = Cache::remember('genres_all', now()->addHour(), function () {
            return Genre::orderBy('name')->get();
        });

        return response()->json([
            'message' => 'Available genres',
            'genres' => $genres
        ]);
    }

    /**
     * Get recommendations for a user.
     */
    public function recommendations(Request $request)
    {
        // Cache recommendations for 20 minutes
        $novels = CacheHelper::remember('novels_recommendations', now()->addMinutes(20), function () {
            return Novel::with('genres')
                ->orderBy('rating', 'desc')
                ->orderBy('views', 'desc')
                ->limit(12)
                ->get();
        }, ['novels', 'novels-recommendations']);

        return response()->json([
            'message' => 'Recommended novels',
            'novels' => $novels
        ]);
    }

    /**
     * Bulk delete novels
     */
    public function bulkDestroy(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'novel_ids' => 'required|array|min:1',
            'novel_ids.*' => 'required|integer|exists:novels,id'
        ]);

        $novelIds = $request->novel_ids;

        // Get the novels to delete
        $novelsToDelete = Novel::whereIn('id', $novelIds)->get();

        // Check authorization for each novel
        $unauthorizedNovels = [];
        $authorizedNovelIds = [];

        foreach ($novelsToDelete as $novel) {
            // Only owner or admin can delete
            if ($novel->user_id === $user->id || $user->isAdmin()) {
                $authorizedNovelIds[] = $novel->id;
            } else {
                $unauthorizedNovels[] = [
                    'id' => $novel->id,
                    'title' => $novel->title
                ];
            }
        }

        // If user is not authorized for any of the novels, return error
        if (!empty($unauthorizedNovels) && empty($authorizedNovelIds)) {
            return response()->json([
                'message' => 'You can only delete your own novels',
                'unauthorized_novels' => $unauthorizedNovels
            ], 403);
        }

        // Delete authorized novels
        $deletedCount = 0;
        if (!empty($authorizedNovelIds)) {
            $deletedCount = Novel::whereIn('id', $authorizedNovelIds)->delete();

            // Clear related caches using CacheHelper
            CacheHelper::clearNovelCaches();
        }

        // Prepare response
        $response = [
            'message' => "Successfully deleted {$deletedCount} novel(s)",
            'deleted_count' => $deletedCount
        ];

        if (!empty($unauthorizedNovels)) {
            $response['message'] .= ', but ' . count($unauthorizedNovels) . ' novel(s) were skipped due to lack of permissions';
            $response['unauthorized_novels'] = $unauthorizedNovels;
            $response['unauthorized_count'] = count($unauthorizedNovels);
        }

        return response()->json($response, !empty($unauthorizedNovels) ? 207 : 200);
    }

    /**
     * Get related novels based on multiple factors
     * Algorithm: Hybrid approach combining genre similarity, rating range, and author
     */
    public function related($slug)
    {
        $cacheKey = "novel_related_{$slug}";

        $data = CacheHelper::remember($cacheKey, now()->addMinutes(30), function () use ($slug) {
            Log::info("CACHE MISS - Finding related novels for: {$slug}");

            // Get the current novel with its genres
            $novel = Novel::where('slug', $slug)
                ->with('genres')
                ->firstOrFail();

            $genreIds = $novel->genres->pluck('id')->toArray();

            if (empty($genreIds)) {
                // If novel has no genres, return popular novels instead
                $relatedNovels = Novel::with(['genres', 'user'])
                    ->where('id', '!=', $novel->id)
                    ->orderBy('views', 'desc')
                    ->limit(6)
                    ->get();

                return [
                    'novel' => $novel,
                    'related_novels' => $relatedNovels,
                    'algorithm' => 'popular_fallback'
                ];
            }

            // Find novels with matching genres
            $relatedNovels = Novel::with(['genres', 'user'])
                ->where('id', '!=', $novel->id)
                ->whereHas('genres', function ($query) use ($genreIds) {
                    $query->whereIn('genres.id', $genreIds);
                })
                ->get();

            // Calculate similarity scores for each novel
            $scoredNovels = $relatedNovels->map(function ($relatedNovel) use ($novel, $genreIds) {
                $score = 0;

                // 1. Genre matching (most important - 50 points max)
                $matchingGenres = $relatedNovel->genres->pluck('id')->intersect($genreIds)->count();
                $totalGenres = max(count($genreIds), 1);
                $genreScore = ($matchingGenres / $totalGenres) * 50;
                $score += $genreScore;

                // 2. Same author bonus (20 points)
                if ($relatedNovel->author === $novel->author) {
                    $score += 20;
                }

                // 3. Similar rating (15 points max)
                $ratingDifference = abs($relatedNovel->rating - $novel->rating);
                $ratingScore = max(0, 15 - ($ratingDifference * 3));
                $score += $ratingScore;

                // 4. Similar popularity tier (10 points max)
                $viewsDifference = abs(log($relatedNovel->views + 1) - log($novel->views + 1));
                $popularityScore = max(0, 10 - $viewsDifference);
                $score += $popularityScore;

                // 5. Same status bonus (5 points)
                if ($relatedNovel->status === $novel->status) {
                    $score += 5;
                }

                $relatedNovel->similarity_score = round($score, 2);
                return $relatedNovel;
            });

            // Sort by similarity score and take top 6
            $topRelated = $scoredNovels
                ->sortByDesc('similarity_score')
                ->take(6)
                ->values();

            return [
                'novel' => $novel,
                'related_novels' => $topRelated,
                'algorithm' => 'hybrid_similarity'
            ];
        });

        return response()->json([
            'message' => 'Related novels retrieved successfully',
            'data' => $data['related_novels'],
            'current_novel' => [
                'id' => $data['novel']->id,
                'title' => $data['novel']->title,
                'slug' => $data['novel']->slug
            ],
            'algorithm_used' => $data['algorithm']
        ], 200);
    }

    /**
     * Upload a cover image for a novel.
     */
    public function uploadCover(Request $request, string $slug)
    {
        $novel = Novel::where('slug', $slug)->first();

        if (!$novel) {
            return response()->json([
                'message' => 'Novel not found'
            ], 404);
        }

        // Check ownership (only owner or admin can upload)
        if ($novel->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'You can only upload covers for your own novels'
            ], 403);
        }

        $request->validate([
            'cover' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        try {
            // Get old cover path for deletion
            $oldCoverPath = null;
            if ($novel->cover_image && !filter_var($novel->cover_image, FILTER_VALIDATE_URL)) {
                $oldCoverPath = str_replace('/storage/', '', $novel->cover_image);
            }

            // Upload and process the image
            $coverUrl = ImageUploadHelper::uploadNovelCover(
                $request->file('cover'),
                $novel->slug,
                $oldCoverPath
            );

            // Update novel's cover_image field
            $novel->cover_image = $coverUrl;
            $novel->save();

            // Clear novel-related caches
            CacheHelper::clearNovelCaches($novel->id, $novel->slug);

            // Refresh model to get accessor values
            $novel->refresh();

            return response()->json([
                'message' => 'Cover image uploaded successfully',
                'cover_url' => $novel->cover_image,
                'novel' => $novel
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload cover image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a novel's cover image.
     */
    public function deleteCover(Request $request, string $slug)
    {
        $novel = Novel::where('slug', $slug)->first();

        if (!$novel) {
            return response()->json([
                'message' => 'Novel not found'
            ], 404);
        }

        // Check ownership (only owner or admin can delete)
        if ($novel->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'You can only delete covers for your own novels'
            ], 403);
        }

        try {
            // Delete the cover image file
            if ($novel->cover_image && !filter_var($novel->cover_image, FILTER_VALIDATE_URL)) {
                ImageUploadHelper::deleteNovelCover($novel->slug);
            }

            // Clear the cover_image field
            $novel->cover_image = null;
            $novel->save();

            // Clear novel-related caches
            CacheHelper::clearNovelCaches($novel->id, $novel->slug);

            return response()->json([
                'message' => 'Cover image deleted successfully',
                'novel' => $novel
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete cover image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

