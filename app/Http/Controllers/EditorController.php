<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\ChapterReview;
use App\Models\EditorialGroup;
use App\Models\EditorialGroupMember;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EditorController extends Controller
{
    /**
     * Get chapters pending review (including pending updates).
     * Shows claim status so editors know which chapters are available.
     */
    public function getPendingChapters(Request $request): JsonResponse
    {
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 15);
        $editorId = $request->user()->id;

        // First, release any expired claims (older than 24 hours)
        Chapter::expiredClaims()->update([
            'claimed_by' => null,
            'claimed_at' => null,
        ]);

        $chapters = Chapter::with([
                'novel:id,title,slug,author',
                'novel.user:id,name',
                'claimedByEditor:id,name'
            ])
            ->whereIn('status', [Chapter::STATUS_PENDING_REVIEW, Chapter::STATUS_PENDING_UPDATE])
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        // Add claim info to each chapter
        $chapters->getCollection()->transform(function ($chapter) use ($editorId) {
            $chapter->is_claimed = $chapter->isClaimed();
            $chapter->is_claimed_by_me = $chapter->isClaimedBy($editorId);
            $chapter->can_review = $chapter->isClaimedBy($editorId);
            return $chapter;
        });

        return response()->json([
            'message' => 'Pending chapters retrieved successfully',
            'chapters' => $chapters
        ]);
    }

    /**
     * Get chapters claimed by the current editor
     */
    public function getMyClaimedChapters(Request $request): JsonResponse
    {
        $editorId = $request->user()->id;

        // Release expired claims first
        Chapter::expiredClaims()->update([
            'claimed_by' => null,
            'claimed_at' => null,
        ]);

        $chapters = Chapter::with(['novel:id,title,slug,author', 'novel.user:id,name'])
            ->where('claimed_by', $editorId)
            ->whereIn('status', [Chapter::STATUS_PENDING_REVIEW, Chapter::STATUS_PENDING_UPDATE])
            ->orderBy('claimed_at', 'asc')
            ->get();

        // Add time remaining info
        $chapters->transform(function ($chapter) {
            $chapter->claim_expires_at = $chapter->claimed_at->addHours(24)->toISOString();
            $chapter->claim_hours_remaining = max(0, 24 - $chapter->claimed_at->diffInHours(now()));
            return $chapter;
        });

        return response()->json([
            'message' => 'Your claimed chapters retrieved successfully',
            'chapters' => $chapters
        ]);
    }

    /**
     * Claim a chapter for review.
     * Prevents race conditions by using database-level locking.
     */
    public function claimChapter(Request $request, Chapter $chapter): JsonResponse
    {
        $editor = $request->user();

        // Only pending_review or pending_update chapters can be claimed
        if (!in_array($chapter->status, [Chapter::STATUS_PENDING_REVIEW, Chapter::STATUS_PENDING_UPDATE])) {
            return response()->json([
                'message' => 'This chapter is not available for review',
                'current_status' => $chapter->status
            ], 400);
        }

        // Use database transaction with pessimistic locking to prevent race conditions
        $claimed = DB::transaction(function () use ($chapter, $editor) {
            // Lock the row for update to prevent concurrent claims
            $lockedChapter = Chapter::where('id', $chapter->id)->lockForUpdate()->first();

            if (!$lockedChapter) {
                return false;
            }

            // If already claimed by this editor, refresh the claim
            if ($lockedChapter->isClaimedBy($editor->id)) {
                $lockedChapter->update([
                    'claimed_at' => now(),
                ]);
                return true;
            }

            // If claimed by someone else and not expired, reject
            if ($lockedChapter->isClaimed()) {
                return false;
            }

            // Claim the chapter
            $lockedChapter->update([
                'claimed_by' => $editor->id,
                'claimed_at' => now(),
            ]);

            return true;
        });

        if (!$claimed) {
            $chapter->refresh();
            $chapter->load('claimedByEditor:id,name');

            return response()->json([
                'message' => 'This chapter is already claimed by another editor. Please try a different chapter.',
                'claimed_by' => $chapter->claimedByEditor ? $chapter->claimedByEditor->name : 'Unknown',
            ], 409);
        }

        $chapter->refresh();
        $chapter->load(['novel:id,title,slug,author', 'claimedByEditor:id,name']);

        return response()->json([
            'message' => 'Chapter claimed successfully. You have 24 hours to review it.',
            'chapter' => $chapter,
            'claim_expires_at' => $chapter->claimed_at->addHours(24)->toISOString(),
        ]);
    }

    /**
     * Release a claimed chapter (unclaim).
     * Editor can voluntarily release a chapter they claimed.
     */
    public function unclaimChapter(Request $request, Chapter $chapter): JsonResponse
    {
        $editor = $request->user();

        // Check if the chapter is claimed by this editor (or admin can unclaim any)
        if (!$chapter->isClaimedBy($editor->id) && !$editor->isAdmin()) {
            return response()->json([
                'message' => 'You can only release chapters that you have claimed'
            ], 403);
        }

        $chapter->releaseClaim();

        return response()->json([
            'message' => 'Chapter claim released successfully. Other editors can now claim it.',
        ]);
    }

    /**
     * Get a specific chapter for review.
     * Only the editor who claimed the chapter can view its full details.
     */
    public function showChapter(Request $request, Chapter $chapter): JsonResponse
    {
        $editor = $request->user();

        // Release expired claims first
        if ($chapter->claimed_at && $chapter->claimed_at->diffInHours(now()) >= 24) {
            $chapter->releaseClaim();
        }

        // Only the editor who claimed the chapter (or admin) can view review details
        if (!$chapter->isClaimedBy($editor->id) && !$editor->isAdmin()) {
            return response()->json([
                'message' => 'You must claim this chapter before you can review it. This prevents multiple editors from reviewing the same chapter simultaneously.',
            ], 403);
        }

        $chapter->load([
            'novel:id,title,slug,author,user_id',
            'novel.user:id,name,email',
            'reviews.editor:id,name',
            'reviewer:id,name',
            'claimedByEditor:id,name'
        ]);

        $chapter->claim_expires_at = $chapter->claimed_at?->addHours(24)->toISOString();

        return response()->json([
            'message' => 'Chapter details retrieved successfully',
            'chapter' => $chapter
        ]);
    }

    /**
     * Approve a chapter.
     * Only the editor who claimed the chapter can approve it.
     */
    public function approveChapter(Request $request, Chapter $chapter): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:2000'
        ]);

        $editor = $request->user();

        // Release expired claims first
        if ($chapter->claimed_at && $chapter->claimed_at->diffInHours(now()) >= 24) {
            $chapter->releaseClaim();
        }

        // Only the claiming editor (or admin) can approve
        if (!$chapter->isClaimedBy($editor->id) && !$editor->isAdmin()) {
            return response()->json([
                'message' => 'You must claim this chapter before you can approve it.',
            ], 403);
        }

        // Check if this is a pending update for an already published chapter
        if ($chapter->status === Chapter::STATUS_PENDING_UPDATE) {
            return $this->approveChapterUpdate($request, $chapter);
        }

        // Only pending_review or revision_requested chapters can be approved
        if (!in_array($chapter->status, [Chapter::STATUS_PENDING_REVIEW, Chapter::STATUS_REVISION_REQUESTED])) {
            return response()->json([
                'message' => 'This chapter cannot be approved in its current status',
                'current_status' => $chapter->status
            ], 400);
        }

        // Update chapter status and release claim
        $chapter->update([
            'status' => Chapter::STATUS_APPROVED,
            'reviewed_by' => $editor->id,
            'reviewed_at' => now(),
            'published_at' => now(),
            'claimed_by' => null,
            'claimed_at' => null,
        ]);

        // Create review record
        ChapterReview::create([
            'chapter_id' => $chapter->id,
            'editor_id' => $editor->id,
            'action' => ChapterReview::ACTION_APPROVED,
            'notes' => $request->notes,
        ]);

        // Update novel's published chapter count
        $chapter->novel->updateChapterCount();

        // Notify the author
        $this->notifyAuthorOfApproval($chapter);

        return response()->json([
            'message' => 'Chapter approved and published successfully',
            'chapter' => $chapter->fresh(['novel:id,title,slug', 'reviewer:id,name'])
        ]);
    }

    /**
     * Approve pending updates for an already published chapter
     */
    private function approveChapterUpdate(Request $request, Chapter $chapter): JsonResponse
    {
        $editor = $request->user();

        $updateData = [
            'status' => Chapter::STATUS_APPROVED,
            'reviewed_by' => $editor->id,
            'reviewed_at' => now(),
            'claimed_by' => null,
            'claimed_at' => null,
        ];

        // Apply pending changes to the main content
        if ($chapter->pending_title) {
            $updateData['title'] = $chapter->pending_title;
        }
        if ($chapter->pending_content) {
            $updateData['content'] = $chapter->pending_content;
            $updateData['word_count'] = str_word_count(strip_tags($chapter->pending_content));
        }

        // Clear pending fields
        $updateData['pending_title'] = null;
        $updateData['pending_content'] = null;

        $chapter->update($updateData);

        // Create review record
        ChapterReview::create([
            'chapter_id' => $chapter->id,
            'editor_id' => $editor->id,
            'action' => ChapterReview::ACTION_APPROVED,
            'notes' => $request->notes ?? 'Content update approved',
        ]);

        // Notify the author
        $this->notifyAuthorOfApproval($chapter);

        return response()->json([
            'message' => 'Chapter update approved successfully',
            'chapter' => $chapter->fresh(['novel:id,title,slug', 'reviewer:id,name'])
        ]);
    }

    /**
     * Request revision for a chapter.
     * Only the editor who claimed the chapter can request revisions.
     */
    public function requestRevision(Request $request, Chapter $chapter): JsonResponse
    {
        $request->validate([
            'notes' => 'required|string|max:2000'
        ]);

        $editor = $request->user();

        // Release expired claims first
        if ($chapter->claimed_at && $chapter->claimed_at->diffInHours(now()) >= 24) {
            $chapter->releaseClaim();
        }

        // Only the claiming editor (or admin) can request revision
        if (!$chapter->isClaimedBy($editor->id) && !$editor->isAdmin()) {
            return response()->json([
                'message' => 'You must claim this chapter before you can request revisions.',
            ], 403);
        }

        // Handle pending update rejection - clear pending content and reset to approved
        if ($chapter->status === Chapter::STATUS_PENDING_UPDATE) {
            // Clear pending changes and keep the original published content
            $chapter->update([
                'status' => Chapter::STATUS_APPROVED,
                'pending_title' => null,
                'pending_content' => null,
                'reviewed_by' => $editor->id,
                'reviewed_at' => now(),
                'claimed_by' => null,
                'claimed_at' => null,
            ]);

            // Create review record
            ChapterReview::create([
                'chapter_id' => $chapter->id,
                'editor_id' => $editor->id,
                'action' => ChapterReview::ACTION_REVISION_REQUESTED,
                'notes' => $request->notes,
            ]);

            // Notify the author
            $this->notifyAuthorOfRevisionRequest($chapter, $request->notes);

            return response()->json([
                'message' => 'Update rejected. The author has been notified to make revisions. Original published content remains unchanged.',
                'chapter' => $chapter->fresh(['novel:id,title,slug', 'reviewer:id,name'])
            ]);
        }

        // Only pending_review chapters can have revision requested
        if ($chapter->status !== Chapter::STATUS_PENDING_REVIEW) {
            return response()->json([
                'message' => 'Revision can only be requested for chapters pending review',
                'current_status' => $chapter->status
            ], 400);
        }

        // Update chapter status and release claim
        $chapter->update([
            'status' => Chapter::STATUS_REVISION_REQUESTED,
            'reviewed_by' => $editor->id,
            'reviewed_at' => now(),
            'claimed_by' => null,
            'claimed_at' => null,
        ]);

        // Create review record
        ChapterReview::create([
            'chapter_id' => $chapter->id,
            'editor_id' => $editor->id,
            'action' => ChapterReview::ACTION_REVISION_REQUESTED,
            'notes' => $request->notes,
        ]);

        // Notify the author
        $this->notifyAuthorOfRevisionRequest($chapter, $request->notes);

        return response()->json([
            'message' => 'Revision requested successfully',
            'chapter' => $chapter->fresh(['novel:id,title,slug', 'reviewer:id,name', 'latestReview'])
        ]);
    }

    /**
     * Get the editorial group this editor belongs to,
     * including its members and pending chapter count.
     */
    public function getGroupInfo(Request $request): JsonResponse
    {
        $editor = $request->user();

        // Find the editor's membership
        $membership = EditorialGroupMember::where('user_id', $editor->id)
            ->where('role', 'editor')
            ->first();

        if (!$membership) {
            return response()->json([
                'message' => 'You are not assigned to any editorial group.',
                'group'   => null,
            ]);
        }

        $group = EditorialGroup::with(['members.user:id,name,username,email,role'])
            ->find($membership->editorial_group_id);

        if (!$group) {
            return response()->json([
                'message' => 'Editorial group not found.',
                'group'   => null,
            ], 404);
        }

        // Get author user IDs in this group
        $authorUserIds = $group->members
            ->where('role', 'author')
            ->pluck('user_id')
            ->toArray();

        // Count pending chapters from those authors
        $pendingFromGroup = 0;
        if (!empty($authorUserIds)) {
            $pendingFromGroup = Chapter::whereHas('novel', function ($q) use ($authorUserIds) {
                    $q->whereIn('user_id', $authorUserIds);
                })
                ->whereIn('status', [Chapter::STATUS_PENDING_REVIEW, Chapter::STATUS_PENDING_UPDATE])
                ->count();
        }

        // Format members
        $members = $group->members->map(function ($m) {
            return [
                'id'        => $m->user->id,
                'name'      => $m->user->name,
                'username'  => $m->user->username,
                'email'     => $m->user->email,
                'user_role' => $m->user->role,
                'group_role' => $m->role,
                'joined_at' => $m->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'message' => 'Group info retrieved successfully',
            'group'   => [
                'id'          => $group->id,
                'name'        => $group->name,
                'tag'         => $group->tag,
                'description' => $group->description,
                'created_at'  => $group->created_at?->toISOString(),
                'member_count' => $group->members->count(),
                'pending_chapters_from_group' => $pendingFromGroup,
                'members'     => $members,
            ],
        ]);
    }

    /**
     * Get editor dashboard stats
     */
    public function getStats(Request $request): JsonResponse
    {
        $editorId = $request->user()->id;

        // Release expired claims before counting
        Chapter::expiredClaims()->update([
            'claimed_by' => null,
            'claimed_at' => null,
        ]);

        $stats = [
            'pending_review' => Chapter::whereIn('status', [Chapter::STATUS_PENDING_REVIEW, Chapter::STATUS_PENDING_UPDATE])->count(),
            'available_to_claim' => Chapter::whereIn('status', [Chapter::STATUS_PENDING_REVIEW, Chapter::STATUS_PENDING_UPDATE])
                ->where(function ($query) {
                    $query->whereNull('claimed_by')
                          ->orWhere('claimed_at', '<', now()->subHours(24));
                })
                ->count(),
            'my_claimed_chapters' => Chapter::where('claimed_by', $editorId)
                ->whereIn('status', [Chapter::STATUS_PENDING_REVIEW, Chapter::STATUS_PENDING_UPDATE])
                ->where('claimed_at', '>=', now()->subHours(24))
                ->count(),
            'my_reviews_today' => ChapterReview::where('editor_id', $editorId)
                ->whereDate('created_at', today())
                ->count(),
            'my_reviews_this_week' => ChapterReview::where('editor_id', $editorId)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'my_total_reviews' => ChapterReview::where('editor_id', $editorId)->count(),
            'approvals_today' => ChapterReview::where('editor_id', $editorId)
                ->where('action', ChapterReview::ACTION_APPROVED)
                ->whereDate('created_at', today())
                ->count(),
            'revisions_requested_today' => ChapterReview::where('editor_id', $editorId)
                ->where('action', ChapterReview::ACTION_REVISION_REQUESTED)
                ->whereDate('created_at', today())
                ->count(),
        ];

        return response()->json([
            'message' => 'Editor stats retrieved successfully',
            'stats' => $stats
        ]);
    }

    /**
     * Get review history for editor
     */
    public function getReviewHistory(Request $request): JsonResponse
    {
        $editorId = $request->user()->id;
        $perPage = $request->query('per_page', 15);

        $reviews = ChapterReview::with(['chapter:id,title,chapter_number,novel_id', 'chapter.novel:id,title,slug'])
            ->where('editor_id', $editorId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'message' => 'Review history retrieved successfully',
            'reviews' => $reviews
        ]);
    }

    /**
     * Notify author when chapter is approved
     */
    private function notifyAuthorOfApproval(Chapter $chapter): void
    {
        $novel = $chapter->novel;
        if (!$novel || !$novel->user_id) {
            return;
        }

        Notification::create([
            'user_id' => $novel->user_id,
            'type' => 'chapter_approved',
            'title' => 'Chapter Approved',
            'message' => "Your chapter \"{$chapter->title}\" for \"{$novel->title}\" has been approved and published.",
            'data' => [
                'chapter_id' => $chapter->id,
                'chapter_title' => $chapter->title,
                'chapter_number' => $chapter->chapter_number,
                'novel_id' => $novel->id,
                'novel_title' => $novel->title,
                'novel_slug' => $novel->slug,
            ],
        ]);
    }

    /**
     * Notify author when revision is requested
     */
    private function notifyAuthorOfRevisionRequest(Chapter $chapter, string $notes): void
    {
        $novel = $chapter->novel;
        if (!$novel || !$novel->user_id) {
            return;
        }

        Notification::create([
            'user_id' => $novel->user_id,
            'type' => 'chapter_revision_requested',
            'title' => 'Revision Requested',
            'message' => "Your chapter \"{$chapter->title}\" for \"{$novel->title}\" needs revision.",
            'data' => [
                'chapter_id' => $chapter->id,
                'chapter_title' => $chapter->title,
                'chapter_number' => $chapter->chapter_number,
                'novel_id' => $novel->id,
                'novel_title' => $novel->title,
                'novel_slug' => $novel->slug,
                'revision_notes' => $notes,
            ],
        ]);
    }
}
