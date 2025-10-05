<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // Notification types
    const TYPE_NEW_CHAPTER = 'new_chapter';
    const TYPE_COMMENT_REPLY = 'comment_reply';
    const TYPE_AUTHOR_STATUS = 'author_status';
    const TYPE_NOVEL_UPDATE = 'novel_update';
    const TYPE_SYSTEM = 'system';

    /**
     * Get the user that owns this notification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(): void
    {
        $this->update([
            'is_read' => false,
            'read_at' => null
        ]);
    }

    /**
     * Create a new chapter notification
     */
    public static function createNewChapterNotification(int $userId, Novel $novel, Chapter $chapter): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => self::TYPE_NEW_CHAPTER,
            'title' => 'New Chapter Available',
            'message' => "Chapter {$chapter->chapter_number}: {$chapter->title} of '{$novel->title}' is now available!",
            'data' => [
                'novel_id' => $novel->id,
                'novel_slug' => $novel->slug,
                'novel_title' => $novel->title,
                'chapter_id' => $chapter->id,
                'chapter_number' => $chapter->chapter_number,
                'chapter_title' => $chapter->title,
            ]
        ]);
    }

    /**
     * Create a comment reply notification
     */
    public static function createCommentReplyNotification(int $userId, Comment $reply, Comment $originalComment): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => self::TYPE_COMMENT_REPLY,
            'title' => 'New Reply to Your Comment',
            'message' => "{$reply->user->name} replied to your comment",
            'data' => [
                'comment_id' => $reply->id,
                'original_comment_id' => $originalComment->id,
                'novel_id' => $reply->novel_id,
                'novel_slug' => $reply->novel->slug ?? null,
                'replier_name' => $reply->user->name,
            ]
        ]);
    }

    /**
     * Create author status notification
     */
    public static function createAuthorStatusNotification(int $userId, string $status, string $message): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => self::TYPE_AUTHOR_STATUS,
            'title' => 'Author Application Update',
            'message' => $message,
            'data' => [
                'status' => $status,
            ]
        ]);
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope for specific type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
