<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlossaryObservation extends Model
{
    protected $fillable = [
        'novel_id', 'chapter_id', 'extraction_run_id', 'entity_type', 'entity_name',
        'normalized_name', 'fact_key', 'value', 'quote', 'context', 'confidence', 'payload',
    ];

    protected $casts = ['confidence' => 'float', 'payload' => 'array'];

    public function run(): BelongsTo { return $this->belongsTo(GlossaryExtractionRun::class, 'extraction_run_id'); }
    public function chapter(): BelongsTo { return $this->belongsTo(Chapter::class); }
    public function novel(): BelongsTo { return $this->belongsTo(Novel::class); }
}
