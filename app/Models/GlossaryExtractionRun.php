<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlossaryExtractionRun extends Model
{
    protected $fillable = [
        'novel_id', 'chapter_id', 'model', 'status', 'attempts', 'metrics',
        'error', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'metrics' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function novel(): BelongsTo
    {
        return $this->belongsTo(Novel::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
