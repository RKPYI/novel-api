<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Chapter extends Model
{
    protected $fillable = [
        'novel_id', 'title', 'content', 'chapter_number',
        'word_count', 'views', 'is_free', 'published_at'
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function novel()
    {
        return $this->belongsTo(Novel::class);
    }

    public function readingProgress()
    {
        return $this->hasMany(ReadingProgress::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->where('is_approved', true);
    }

    public function allComments()
    {
        return $this->hasMany(Comment::class);
    }

    // Get next chapter
    public function getNextChapterAttribute()
    {
        return static::where('novel_id', $this->novel_id)
            ->where('chapter_number', '>', $this->chapter_number)
            ->orderBy('chapter_number')
            ->first();
    }

    // Get previous chapter
    public function getPreviousChapterAttribute()
    {
        return static::where('novel_id', $this->novel_id)
            ->where('chapter_number', '<', $this->chapter_number)
            ->orderBy('chapter_number', 'desc')
            ->first();
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // When a chapter is created, increment novel's total_chapters
        static::created(function ($chapter) {
            if ($chapter->novel) {
                $chapter->novel->increment('total_chapters');
                $chapter->novel->touch(); // Update updated_at timestamp
            }
        });

        // When a chapter is deleted, decrement novel's total_chapters
        static::deleted(function ($chapter) {
            if ($chapter->novel) {
                $chapter->novel->decrement('total_chapters');
                $chapter->novel->touch(); // Update updated_at timestamp
            }
        });

        // Clear chapter caches on save/delete
        static::saved(function ($chapter) {
            if ($chapter->novel) {
                Cache::tags([
                    "novel_{$chapter->novel->slug}",
                    "chapters_novel_{$chapter->novel->id}"
                ])->flush();
            }
        });

        static::deleted(function ($chapter) {
            if ($chapter->novel) {
                Cache::tags([
                    "novel_{$chapter->novel->slug}",
                    "chapters_novel_{$chapter->novel->id}"
                ])->flush();
            }
        });
    }
}
