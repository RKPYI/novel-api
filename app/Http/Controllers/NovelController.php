<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Novel;
use App\Models\Genre;
use App\Models\ReadingProgress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NovelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Unique cache key for this exact request (filters + pagination)
        $cacheKey = 'novels_index_' . md5($request->fullUrl());

        $novels = Cache::tags(['novels-index'])->remember($cacheKey, now()->addMinutes(10), function () use ($request) {
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

        // Increment view count and refresh the model to get updated views
        $novel->views = $novel->views + 1;
        $novel->save();

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

        $novel->delete();

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

        $novels = Cache::tags(['novels', 'novels-search'])->remember($cacheKey, now()->addMinutes(15), function () use ($query) {
            return Novel::with('genres')
                ->where('title', 'LIKE', '%' . $query . '%')
                ->orWhere('author', 'LIKE', '%' . $query . '%')
                ->orWhere('description', 'LIKE', '%' . $query . '%')
                ->limit(10)
                ->get();
        });

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
        $novels = Cache::tags(['novels', 'novels-popular'])->remember('novels_popular', now()->addMinutes(5), function () {
            return Novel::with('genres')
                ->orderBy('views', 'desc')
                ->limit(12)
                ->get();
        });

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
        $novels = Cache::tags(['novels', 'novels-latest'])->remember('novels_latest', now()->addMinutes(10), function () {
            return Novel::with('genres')
                ->orderBy('created_at', 'desc')
                ->limit(12)
                ->get();
        });

        return response()->json([
            'message' => 'Latest novels',
            'novels' => $novels
        ]);
    }

    /**
     * Get recently updated novels.
     */
    public function recentlyUpdated()
    {
        // Cache recently updated for 10 minutes
        $novels = Cache::tags(['novels', 'novels-updated'])->remember('novels_recently_updated', now()->addMinutes(10), function () {
            return Novel::with('genres')
                ->orderBy('updated_at', 'desc')
                ->limit(12)
                ->get();
        });

        return response()->json([
            'message' => 'Recently updated novels',
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
        $novels = Cache::tags(['novels', 'novels-recommendations'])->remember('novels_recommendations', now()->addMinutes(20), function () {
            return Novel::with('genres')
                ->orderBy('rating', 'desc')
                ->orderBy('views', 'desc')
                ->limit(12)
                ->get();
        });

        return response()->json([
            'message' => 'Recommended novels',
            'novels' => $novels
        ]);
    }
}
