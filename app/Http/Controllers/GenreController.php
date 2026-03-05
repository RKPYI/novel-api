<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Helpers\CacheHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GenreController extends Controller
{
    /**
     * List all genres with novel counts.
     */
    public function index(): JsonResponse
    {
        $genres = Genre::withCount('novels')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'All genres',
            'genres'  => $genres,
        ]);
    }

    /**
     * Create a new genre.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:genres,name',
            'description' => 'nullable|string|max:1000',
            'color'       => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $genre = Genre::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
            'color'       => $request->color ?? '#dc2626',
        ]);

        CacheHelper::forget('genres_all');

        return response()->json([
            'message' => 'Genre created successfully',
            'genre'   => $genre->loadCount('novels'),
        ], 201);
    }

    /**
     * Show a single genre.
     */
    public function show(Genre $genre): JsonResponse
    {
        return response()->json([
            'message' => 'Genre details',
            'genre'   => $genre->loadCount('novels'),
        ]);
    }

    /**
     * Update a genre.
     */
    public function update(Request $request, Genre $genre): JsonResponse
    {
        $request->validate([
            'name'        => 'sometimes|required|string|max:255|unique:genres,name,' . $genre->id,
            'description' => 'nullable|string|max:1000',
            'color'       => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $data = $request->only(['name', 'description', 'color']);

        // Regenerate slug if name changes
        if (isset($data['name']) && $data['name'] !== $genre->name) {
            $data['slug'] = Str::slug($data['name']);
        }

        $genre->update($data);

        CacheHelper::forget('genres_all');

        return response()->json([
            'message' => 'Genre updated successfully',
            'genre'   => $genre->fresh()->loadCount('novels'),
        ]);
    }

    /**
     * Delete a genre.
     */
    public function destroy(Genre $genre): JsonResponse
    {
        // Prevent deletion if novels are attached
        if ($genre->novels()->exists()) {
            return response()->json([
                'message' => "Cannot delete genre '{$genre->name}' because it has novels attached. Remove the novels from this genre first.",
                'novels_count' => $genre->novels()->count(),
            ], 409);
        }

        $name = $genre->name;
        $genre->delete();

        CacheHelper::forget('genres_all');

        return response()->json([
            'message' => "Genre '{$name}' deleted successfully",
        ]);
    }
}
