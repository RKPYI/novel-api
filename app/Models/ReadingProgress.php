<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingProgress extends Model
{
    protected $fillable = [
        'user_id',
        'novel_id',
        'chapter_id',
        'chapter_number',
        'progress_percentage',
        'last_read_at',
    ];

    protected $casts = [
        'progress_percentage' => 'decimal:2',
        'last_read_at' => 'datetime',
    ];

    /**
     * Get the user that owns this reading progress.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the novel associated with this reading progress.
     */
    public function novel(): BelongsTo
    {
        return $this->belongsTo(Novel::class);
    }

    /**
     * Get the current chapter associated with this reading progress.
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
