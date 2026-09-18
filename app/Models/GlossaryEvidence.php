<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlossaryEvidence extends Model
{
    protected $table = 'glossary_evidence';

    protected $fillable = ['fact_id', 'chapter_id', 'quote', 'context', 'confidence'];

    protected $hidden = ['quote_hash'];

    protected $casts = ['confidence' => 'float'];

    protected static function booted(): void
    {
        static::saving(function (self $evidence): void {
            $evidence->quote_hash = hash('sha256', $evidence->quote);
        });
    }

    public function fact(): BelongsTo
    {
        return $this->belongsTo(GlossaryFact::class, 'fact_id');
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
