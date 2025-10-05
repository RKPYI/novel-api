<?php

namespace App\Http\Controllers;

use App\Models\UserLibrary;
use App\Models\Novel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class UserLibraryController extends Controller
{
    /**
     * Get user's library
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->query('status', 'all');
        $favorites = $request->query('favorites', false);

        $query = UserLibrary::where('user_id', $user->id)
            ->with(['novel.genres'])
            ->orderBy('added_at', 'desc');

        // Handle status filtering (including 'favorites' as a special status)
        if ($status === 'favorites') {
            $query->where('is_favorite', true);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        // Also support the favorites boolean parameter for backward compatibility
        if ($favorites === 'true' || $favorites === true) {
            $query->where('is_favorite', true);
        }

        $library = $query->paginate(20);

        return response()->json([
            'message' => 'User library retrieved successfully',
            'library' => $library,
            'stats' => [
                'total' => UserLibrary::where('user_id', $user->id)->count(),
                'want_to_read' => UserLibrary::where('user_id', $user->id)->where('status', UserLibrary::STATUS_WANT_TO_READ)->count(),
                'reading' => UserLibrary::where('user_id', $user->id)->where('status', UserLibrary::STATUS_READING)->count(),
                'completed' => UserLibrary::where('user_id', $user->id)->where('status', UserLibrary::STATUS_COMPLETED)->count(),
                'dropped' => UserLibrary::where('user_id', $user->id)->where('status', UserLibrary::STATUS_DROPPED)->count(),
                'on_hold' => UserLibrary::where('user_id', $user->id)->where('status', UserLibrary::STATUS_ON_HOLD)->count(),
                'favorites' => UserLibrary::where('user_id', $user->id)->where('is_favorite', true)->count(),
            ]
        ]);
    }

    /**
     * Add novel to library or update existing entry
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), UserLibrary::validationRules());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $novelId = $request->novel_id;

        // Check if novel exists
        $novel = Novel::find($novelId);
        if (!$novel) {
            return response()->json([
                'message' => 'Novel not found'
            ], 404);
        }

        // Create or update library entry
        $libraryEntry = UserLibrary::updateOrCreate(
            [
                'user_id' => $user->id,
                'novel_id' => $novelId
            ],
            [
                'status' => $request->status,
                'is_favorite' => $request->boolean('is_favorite', false),
                'status_updated_at' => now(),
                'added_at' => now(), // Will only set on create
            ]
        );

        $libraryEntry->load(['novel.genres']);

        return response()->json([
            'message' => 'Novel added to library successfully',
            'library_entry' => $libraryEntry
        ], $libraryEntry->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Update library entry status
     */
    public function update(Request $request, UserLibrary $library): JsonResponse
    {
        // Check if user owns this library entry
        if ($library->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'status' => 'sometimes|in:' . implode(',', UserLibrary::getStatuses()),
            'is_favorite' => 'sometimes|boolean',
        ]);

        $updateData = $request->only(['status', 'is_favorite']);

        if ($request->has('status')) {
            $updateData['status_updated_at'] = now();
        }

        $library->update($updateData);
        $library->load(['novel.genres']);

        return response()->json([
            'message' => 'Library entry updated successfully',
            'library_entry' => $library
        ]);
    }

    /**
     * Remove novel from library
     */
    public function destroy(Request $request, UserLibrary $library): JsonResponse
    {
        // Check if user owns this library entry
        if ($library->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $novelTitle = $library->novel->title;
        $library->delete();

        return response()->json([
            'message' => "'{$novelTitle}' removed from library successfully"
        ]);
    }

    /**
     * Check if novel is in user's library
     */
    public function checkStatus(Request $request, Novel $novel): JsonResponse
    {
        $user = $request->user();

        $libraryEntry = UserLibrary::where('user_id', $user->id)
            ->where('novel_id', $novel->id)
            ->first();

        if (!$libraryEntry) {
            return response()->json([
                'in_library' => false,
                'novel_id' => $novel->id,
                'novel_title' => $novel->title
            ]);
        }

        return response()->json([
            'in_library' => true,
            'library_entry' => [
                'id' => $libraryEntry->id,
                'status' => $libraryEntry->status,
                'is_favorite' => $libraryEntry->is_favorite,
                'added_at' => $libraryEntry->added_at,
                'status_updated_at' => $libraryEntry->status_updated_at
            ],
            'novel_id' => $novel->id,
            'novel_title' => $novel->title
        ]);
    }

    /**
     * Toggle favorite status
     */
    public function toggleFavorite(Request $request, Novel $novel): JsonResponse
    {
        $user = $request->user();

        $libraryEntry = UserLibrary::where('user_id', $user->id)
            ->where('novel_id', $novel->id)
            ->first();

        if (!$libraryEntry) {
            return response()->json([
                'message' => 'Novel not in library. Add to library first.'
            ], 404);
        }

        $libraryEntry->update([
            'is_favorite' => !$libraryEntry->is_favorite
        ]);

        return response()->json([
            'message' => $libraryEntry->is_favorite ? 'Added to favorites' : 'Removed from favorites',
            'is_favorite' => $libraryEntry->is_favorite,
            'library_entry' => $libraryEntry->load(['novel.genres'])
        ]);
    }

    /**
     * Get available statuses
     */
    public function getStatuses(): JsonResponse
    {
        return response()->json([
            'statuses' => [
                ['value' => UserLibrary::STATUS_WANT_TO_READ, 'label' => 'Want to Read'],
                ['value' => UserLibrary::STATUS_READING, 'label' => 'Reading'],
                ['value' => UserLibrary::STATUS_COMPLETED, 'label' => 'Completed'],
                ['value' => UserLibrary::STATUS_DROPPED, 'label' => 'Dropped'],
                ['value' => UserLibrary::STATUS_ON_HOLD, 'label' => 'On Hold'],
            ]
        ]);
    }
}
