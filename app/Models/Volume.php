<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Volume extends Model
{
    protected $fillable = [
        'novel_id',
        'volume_number',
        'title',
        'description',
    ];

    public function novel()
    {
        return $this->belongsTo(Novel::class);
    }

    public function chapters()
    {
        return $this->hasMany(Chapter::class)->orderBy('chapter_number');
    }

    public function publishedChapters()
    {
        return $this->hasMany(Chapter::class)
            ->whereIn('status', [Chapter::STATUS_APPROVED, Chapter::STATUS_PENDING_UPDATE])
            ->whereNotNull('published_at')
            ->orderBy('chapter_number');
    }
}
