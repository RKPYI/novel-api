<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlossaryFact extends Model
{
    protected $fillable = [
        'novel_id', 'entity_id', 'fact_key', 'value', 'previous_value', 'value_data', 'confidence',
        'status', 'authority', 'first_seen_chapter_id', 'last_seen_chapter_id', 'last_updated_at',
    ];

    protected $casts = [
        'value_data' => 'array',
        'confidence' => 'float',
        'last_updated_at' => 'datetime',
    ];

    public function novel(): BelongsTo
    {
        return $this->belongsTo(Novel::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(GlossaryEntity::class, 'entity_id');
    }

    public function firstSeenChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'first_seen_chapter_id');
    }

    public function lastSeenChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'last_seen_chapter_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(GlossaryEvidence::class, 'fact_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(GlossaryReview::class, 'fact_id');
    }
}
