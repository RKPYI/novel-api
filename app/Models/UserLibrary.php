<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLibrary extends Model
{
    protected $fillable = [
        'user_id',
        'novel_id',
        'status',
        'is_favorite',
        'added_at',
        'status_updated_at',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'added_at' => 'datetime',
        'status_updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_WANT_TO_READ = 'want_to_read';
    const STATUS_READING = 'reading';
    const STATUS_COMPLETED = 'completed';
    const STATUS_DROPPED = 'dropped';
    const STATUS_ON_HOLD = 'on_hold';

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_WANT_TO_READ,
            self::STATUS_READING,
            self::STATUS_COMPLETED,
            self::STATUS_DROPPED,
            self::STATUS_ON_HOLD,
        ];
    }

    /**
     * Get the user that owns this library entry
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the novel in this library entry
     */
    public function novel(): BelongsTo
    {
        return $this->belongsTo(Novel::class);
    }

    /**
     * Check if the entry is marked as favorite
     */
    public function isFavorite(): bool
    {
        return $this->is_favorite;
    }

    /**
     * Mark as favorite
     */
    public function markAsFavorite(): void
    {
        $this->update(['is_favorite' => true]);
    }

    /**
     * Unmark as favorite
     */
    public function unmarkAsFavorite(): void
    {
        $this->update(['is_favorite' => false]);
    }

    /**
     * Update reading status
     */
    public function updateStatus(string $status): void
    {
        $this->update([
            'status' => $status,
            'status_updated_at' => now()
        ]);
    }

    /**
     * Validation rules for user library
     */
    public static function validationRules(): array
    {
        return [
            'novel_id' => 'required|exists:novels,id',
            'status' => 'required|in:' . implode(',', self::getStatuses()),
            'is_favorite' => 'boolean',
        ];
    }
}
