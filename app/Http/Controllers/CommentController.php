<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\CommentVote;
use App\Models\Novel;
use App\Models\Chapter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * Get comments for a novel or chapter
     */
    public function index(Request $request, Novel $novel, $chapterNumber = null): JsonResponse
    {
        $query = Comment::with(['user:id,name,avatar', 'replies.user:id,name,avatar'])
            ->where('novel_id', $novel->id)
            ->where('is_approved', true)
            ->whereNull('parent_id'); // Only top-level comments

        if ($chapterNumber) {
            $chapter = Chapter::where('novel_id', $novel->id)
                ->where('chapter_number', $chapterNumber)
                ->firstOrFail();
            $query->where('chapter_id', $chapter->id);
        } else {
            $query->whereNull('chapter_id'); // Novel comments only
        }

        $comments = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($comments);
    }

    /**
     * Store a new comment
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'novel_id' => 'required|exists:novels,id',
            'chapter_id' => 'nullable|exists:chapters,id',
            'parent_id' => 'nullable|exists:comments,id',
            'content' => 'required|string|max:1000',
            'is_spoiler' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'novel_id' => $request->novel_id,
            'chapter_id' => $request->chapter_id,
            'parent_id' => $request->parent_id,
            'content' => $request->content,
            'is_spoiler' => $request->boolean('is_spoiler', false),
        ]);

        $comment->load(['user:id,name,avatar', 'replies.user:id,name,avatar']);

        return response()->json([
            'message' => 'Comment created successfully',
            'comment' => $comment
        ], 201);
    }

    /**
     * Update a comment
     */
    public function update(Request $request, Comment $comment): JsonResponse
    {
        // Check if user owns the comment
        if ($comment->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'is_spoiler' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $comment->update([
            'content' => $request->content,
            'is_spoiler' => $request->boolean('is_spoiler', $comment->is_spoiler),
        ]);

        $comment->load(['user:id,name,avatar', 'replies.user:id,name,avatar']);

        return response()->json([
            'message' => 'Comment updated successfully',
            'comment' => $comment
        ]);
    }

    /**
     * Delete a comment
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();

        // Check if user owns the comment or is admin
        if ($comment->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully'
        ]);
    }

    /**
     * Vote on a comment (like/dislike)
     */
    public function vote(Request $request, Comment $comment): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_upvote' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $request->user()->id;
        $isUpvote = $request->boolean('is_upvote');

        // Check if user has already voted
        $existingVote = CommentVote::where('user_id', $userId)
            ->where('comment_id', $comment->id)
            ->first();

        if ($existingVote) {
            if ($existingVote->is_upvote === $isUpvote) {
                // Remove vote if clicking the same button
                $existingVote->delete();
                $message = 'Vote removed';
            } else {
                // Update vote if clicking opposite button
                $existingVote->update(['is_upvote' => $isUpvote]);
                $message = 'Vote updated';
            }
        } else {
            // Create new vote
            CommentVote::create([
                'user_id' => $userId,
                'comment_id' => $comment->id,
                'is_upvote' => $isUpvote,
            ]);
            $message = 'Vote added';
        }

        // Update comment vote counts
        $comment->updateVoteCounts();

        return response()->json([
            'message' => $message,
            'likes' => $comment->likes,
            'dislikes' => $comment->dislikes,
        ]);
    }

    /**
     * Get user's vote on a comment
     */
    public function getUserVote(Request $request, Comment $comment): JsonResponse
    {
        $vote = $comment->userVote($request->user()->id);

        return response()->json([
            'vote' => $vote ? [
                'is_upvote' => $vote->is_upvote,
                'created_at' => $vote->created_at,
            ] : null
        ]);
    }

    /**
     * Admin: Get all comments (including unapproved)
     */
    public function adminIndex(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comments = Comment::with(['user:id,name,email,avatar', 'novel:id,title', 'chapter:id,title'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($comments);
    }

    /**
     * Admin: Approve/disapprove comment
     */
    public function adminToggleApproval(Request $request, Comment $comment): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->update([
            'is_approved' => !$comment->is_approved
        ]);

        return response()->json([
            'message' => 'Comment approval status updated',
            'comment' => $comment
        ]);
    }
}
