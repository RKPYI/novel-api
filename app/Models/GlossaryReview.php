<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlossaryReview extends Model
{
    protected $fillable = ['fact_id', 'reviewer_id', 'action', 'notes'];

    public function fact(): BelongsTo
    {
        return $this->belongsTo(GlossaryFact::class, 'fact_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
