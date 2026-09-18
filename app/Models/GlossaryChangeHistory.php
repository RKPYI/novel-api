<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlossaryChangeHistory extends Model
{
    protected $table = 'glossary_change_history';

    protected $fillable = [
        'novel_id', 'proposal_id', 'actor_id', 'actor_type', 'change_type',
        'subject_type', 'subject_id', 'before_data', 'after_data', 'reason',
    ];

    protected $casts = ['before_data' => 'array', 'after_data' => 'array'];
}
