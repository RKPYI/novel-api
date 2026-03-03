<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChapterReview extends Model
{
    protected $fillable = [
        'chapter_id',
        'editor_id',
        'action',
        'notes',
    ];

    const ACTION_APPROVED = 'approved';
    const ACTION_REVISION_REQUESTED = 'revision_requested';

    /**
     * Get the chapter this review belongs to
     */
    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Get the editor who made this review
     */
    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
