<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlossaryEntity extends Model
{
    protected $fillable = ['novel_id', 'type', 'canonical_name', 'normalized_name', 'aliases', 'status', 'authority'];

    protected $casts = ['aliases' => 'array'];

    public function novel(): BelongsTo
    {
        return $this->belongsTo(Novel::class);
    }

    public function facts(): HasMany
    {
        return $this->hasMany(GlossaryFact::class, 'entity_id');
    }
}
