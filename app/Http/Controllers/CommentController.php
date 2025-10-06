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
        $baseQuery = Comment::where('novel_id', $novel->id)
            ->where('is_approved', true);

        if ($chapterNumber) {
            $chapter = Chapter::where('novel_id', $novel->id)
                ->where('id', $chapterNumber) // Changed 'chapter_number' to 'id'
                ->firstOrFail();
            $baseQuery->where('chapter_id', $chapter->id);
        } else {
            $baseQuery->whereNull('chapter_id'); // Novel comments only
        }

        // Get total count of all comments (including replies)
        $totalCommentsCount = (clone $baseQuery)->count();

        // Get paginated top-level comments
        $topLevelCommentsQuery = (clone $baseQuery)
            ->with(['user:id,name,avatar,role,email_verified_at', 'replies.user:id,name,avatar,role,email_verified_at', 'replies.replies'])
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc');

        $comments = $topLevelCommentsQuery->paginate(20);

        // To ensure replies also have their user data and potentially nested replies loaded
        // This can be resource-intensive if replies are very deep.
        // Consider if 'replies.user:id,name,avatar' is sufficient or if deeper nesting is needed.
        // The current 'replies.user:id,name,avatar' loads user for direct replies.
        // If you need user for replies of replies, you'd do 'replies.replies.user:id,name,avatar', etc.
        // For now, assuming 'replies.user:id,name,avatar' is what's primarily used by frontend for replies.

        return response()->json([
            'comments' => $comments,
            'total_comments_count' => $totalCommentsCount
        ]);
    }

    /**
     * Get votes for multiple comments by IDs.
     * Example: GET /comments/votes?comment_ids=12,13,14
     */
    public function bulkVotes(Request $request): JsonResponse
    {
        $idsParam = $request->query('comment_ids');

        if (is_null($idsParam) || $idsParam === '') {
            return response()->json([ 'message' => 'comment_ids query parameter is required' ], 400);
        }

        // Accept CSV or JSON array
        if (is_string($idsParam)) {
            $ids = array_filter(array_map('trim', explode(',', $idsParam)), function ($v) {
                return $v !== '';
            });
        } elseif (is_array($idsParam)) {
            $ids = $idsParam;
        } else {
            return response()->json([ 'message' => 'Invalid comment_ids format' ], 400);
        }

        // Cast to integers and unique
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (empty($ids)) {
            return response()->json([ 'message' => 'No valid comment ids provided' ], 400);
        }

        $result = [];
        $userId = $request->user()?->id;

        // If not authenticated, return all nulls
        if (!$userId) {
            foreach ($ids as $id) {
                $result[$id] = ['vote' => null];
            }
            return response()->json($result);
        }

        // Get user's votes for all comments in one query
        $userVotes = CommentVote::whereIn('comment_id', $ids)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('comment_id');

        // Build result matching getUserVote format
        foreach ($ids as $id) {
            $vote = $userVotes->get($id);

            $result[$id] = [
                'vote' => $vote ? [
                    'is_upvote' => $vote->is_upvote,
                    'created_at' => $vote->created_at,
                ] : null
            ];
        }

        return response()->json($result);
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

        // Send notification if this is a reply to another user's comment
        if ($request->parent_id) {
            $parentComment = Comment::with(['novel', 'chapter'])->find($request->parent_id);

            // Only notify if replying to someone else's comment (not own comment)
            if ($parentComment && $parentComment->user_id !== $request->user()->id) {
                \App\Models\Notification::createCommentReplyNotification(
                    $parentComment->user_id,
                    $comment,
                    $parentComment
                );
            }
        }

        $comment->load(['user:id,name,avatar,role,email_verified_at', 'replies.user:id,name,avatar,role,email_verified_at']);

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
            'edited_at' => now(),
        ]);

        $comment->load(['user:id,name,avatar,role,email_verified_at', 'replies.user:id,name,avatar,role,email_verified_at']);

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

        // For adminIndex, ensure role and email_verified_at are included if not already by default user selection
        $comments = Comment::with(['user:id,name,email,avatar,role,email_verified_at', 'novel:id,title', 'chapter:id,title'])
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
