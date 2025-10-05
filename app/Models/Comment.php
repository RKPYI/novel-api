<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $fillable = [
        'user_id',
        'novel_id',
        'chapter_id',
        'parent_id',
        'content',
        'likes',
        'dislikes',
        'is_spoiler',
        'is_approved',
        'edited_at',
    ];

    protected $casts = [
        'is_spoiler' => 'boolean',
        'is_approved' => 'boolean',
        'edited_at' => 'datetime',
    ];

    /**
     * Get the user that owns the comment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the novel that the comment belongs to
     */
    public function novel(): BelongsTo
    {
        return $this->belongsTo(Novel::class);
    }

    /**
     * Get the chapter that the comment belongs to (optional)
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Get the parent comment (for replies)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get the replies to this comment
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->where('is_approved', true);
    }

    /**
     * Get all replies including unapproved (for admins)
     */
    public function allReplies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * Get the votes for this comment
     */
    public function votes(): HasMany
    {
        return $this->hasMany(CommentVote::class);
    }

    /**
     * Check if the comment is a top-level comment
     */
    public function isTopLevel(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Get the vote from a specific user
     */
    public function userVote($userId)
    {
        return $this->votes()->where('user_id', $userId)->first();
    }

    /**
     * Update like/dislike counts
     */
    public function updateVoteCounts()
    {
        $this->likes = $this->votes()->where('is_upvote', true)->count();
        $this->dislikes = $this->votes()->where('is_upvote', false)->count();
        $this->save();
    }

    /**
     * Check if the comment has been edited
     */
    public function isEdited(): bool
    {
        return !is_null($this->edited_at);
    }

    /**
     * Get formatted edited time for display
     */
    public function getEditedTimeAttribute(): ?string
    {
        return $this->edited_at ? $this->edited_at->diffForHumans() : null;
    }
}
