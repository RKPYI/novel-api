<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlossaryEvidence extends Model
{
    protected $table = 'glossary_evidence';

    protected $fillable = ['fact_id', 'chapter_id', 'quote', 'context', 'confidence'];

    protected $casts = ['confidence' => 'float'];

    public function fact(): BelongsTo
    {
        return $this->belongsTo(GlossaryFact::class, 'fact_id');
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
