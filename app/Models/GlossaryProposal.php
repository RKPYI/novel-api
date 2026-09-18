<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlossaryProposal extends Model
{
    protected $fillable = [
        'novel_id', 'observation_id', 'entity_id', 'fact_id', 'operation', 'status',
        'ai_recommendation', 'ai_explanation', 'confidence', 'before_data', 'after_data', 'reason',
    ];

    protected $casts = [
        'confidence' => 'float',
        'before_data' => 'array',
        'after_data' => 'array',
    ];

    public function observation(): BelongsTo { return $this->belongsTo(GlossaryObservation::class); }
    public function entity(): BelongsTo { return $this->belongsTo(GlossaryEntity::class); }
    public function fact(): BelongsTo { return $this->belongsTo(GlossaryFact::class); }
    public function novel(): BelongsTo { return $this->belongsTo(Novel::class); }
}
