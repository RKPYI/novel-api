<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlossaryEntityRedirect extends Model
{
    protected $fillable = ['novel_id', 'source_entity_id', 'target_entity_id', 'created_by', 'reason'];

    public function source(): BelongsTo { return $this->belongsTo(GlossaryEntity::class, 'source_entity_id'); }
    public function target(): BelongsTo { return $this->belongsTo(GlossaryEntity::class, 'target_entity_id'); }
}
