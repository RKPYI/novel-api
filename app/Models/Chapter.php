<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Helpers\CacheHelper;

class Chapter extends Model
{
    protected $fillable = [
        'novel_id', 'title', 'content', 'chapter_number',
        'word_count', 'views', 'is_free', 'published_at',
        'status', 'reviewed_by', 'reviewed_at',
        'pending_title', 'pending_content',
        'claimed_by', 'claimed_at'
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'published_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    // Chapter status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REVISION_REQUESTED = 'revision_requested';
    const STATUS_PENDING_UPDATE = 'pending_update'; // Published chapter with pending content update

    public function novel()
    {
        return $this->belongsTo(Novel::class);
    }

    public function readingProgress()
    {
        return $this->hasMany(ReadingProgress::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->where('is_approved', true);
    }

    public function allComments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get the editor who reviewed this chapter
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the editor who claimed this chapter for review
     */
    public function claimedByEditor()
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    /**
     * Check if the chapter is currently claimed by an editor
     * A claim expires after 24 hours
     */
    public function isClaimed(): bool
    {
        if (!$this->claimed_by || !$this->claimed_at) {
            return false;
        }

        // Check if claim has expired (24 hours)
        if ($this->claimed_at->diffInHours(now()) >= 24) {
            return false;
        }

        return true;
    }

    /**
     * Check if the chapter is claimed by a specific editor
     */
    public function isClaimedBy(int $editorId): bool
    {
        return $this->isClaimed() && $this->claimed_by === $editorId;
    }

    /**
     * Claim this chapter for review by an editor
     */
    public function claim(int $editorId): bool
    {
        // If already claimed by someone else and not expired, cannot claim
        if ($this->isClaimed() && $this->claimed_by !== $editorId) {
            return false;
        }

        $this->update([
            'claimed_by' => $editorId,
            'claimed_at' => now(),
        ]);

        return true;
    }

    /**
     * Release the claim on this chapter
     */
    public function releaseClaim(): void
    {
        $this->update([
            'claimed_by' => null,
            'claimed_at' => null,
        ]);
    }

    /**
     * Scope for chapters with expired claims (older than 24 hours)
     */
    public function scopeExpiredClaims($query)
    {
        return $query->whereNotNull('claimed_by')
                     ->whereNotNull('claimed_at')
                     ->where('claimed_at', '<', now()->subHours(24));
    }

    /**
     * Get all reviews for this chapter
     */
    public function reviews()
    {
        return $this->hasMany(ChapterReview::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the latest review for this chapter
     */
    public function latestReview()
    {
        return $this->hasOne(ChapterReview::class)->latestOfMany();
    }

    /**
     * Check if chapter is published (approved and has published_at)
     */
    public function isPublished(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PENDING_UPDATE])
            && $this->published_at !== null;
    }

    /**
     * Check if chapter needs review
     */
    public function needsReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    /**
     * Check if chapter has pending content update
     */
    public function hasPendingUpdate(): bool
    {
        return $this->status === self::STATUS_PENDING_UPDATE;
    }

    /**
     * Check if chapter has revision requested
     */
    public function hasRevisionRequested(): bool
    {
        return $this->status === self::STATUS_REVISION_REQUESTED;
    }

    /**
     * Check if chapter can be edited by author
     */
    public function canBeEditedByAuthor(): bool
    {
        // Can edit if draft, revision requested, or approved (will create pending update)
        // Cannot edit if pending_review or pending_update (waiting for editor)
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_REVISION_REQUESTED,
            self::STATUS_APPROVED
        ]);
    }

    /**
     * Scope for published chapters only
     * Includes approved and pending_update (still visible to readers)
     */
    public function scopePublished($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PENDING_UPDATE])
                     ->whereNotNull('published_at');
    }

    /**
     * Scope for chapters pending review
     */
    public function scopePendingReview($query)
    {
        return $query->where('status', self::STATUS_PENDING_REVIEW);
    }

    // Get next chapter
    public function getNextChapterAttribute()
    {
        return static::where('novel_id', $this->novel_id)
            ->where('chapter_number', '>', $this->chapter_number)
            ->orderBy('chapter_number')
            ->first();
    }

    // Get previous chapter
    public function getPreviousChapterAttribute()
    {
        return static::where('novel_id', $this->novel_id)
            ->where('chapter_number', '<', $this->chapter_number)
            ->orderBy('chapter_number', 'desc')
            ->first();
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // When a chapter is created, only increment if it's published (admin created)
        static::created(function ($chapter) {
            if ($chapter->novel && $chapter->isPublished()) {
                $chapter->novel->increment('total_chapters');
                $chapter->novel->touch(); // Update updated_at timestamp
            }
        });

        // When a chapter is deleted, only decrement if it was published
        static::deleted(function ($chapter) {
            $publishedStatuses = [self::STATUS_APPROVED, self::STATUS_PENDING_UPDATE];
            if ($chapter->novel && in_array($chapter->status, $publishedStatuses) && $chapter->published_at !== null) {
                $chapter->novel->decrement('total_chapters');
                $chapter->novel->touch(); // Update updated_at timestamp
            }
        });

        // Clear chapter caches on save/delete (using key-based caching for compatibility)
        static::saved(function ($chapter) {
            if ($chapter->novel) {
                CacheHelper::clearChapterCaches(
                    $chapter->novel->id,
                    $chapter->chapter_number,
                    $chapter->novel->slug
                );
            }
        });

        static::deleted(function ($chapter) {
            if ($chapter->novel) {
                CacheHelper::clearChapterCaches(
                    $chapter->novel->id,
                    $chapter->chapter_number,
                    $chapter->novel->slug
                );
            }
        });
    }
}
