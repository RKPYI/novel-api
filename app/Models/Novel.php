<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Novel extends Model
{
    protected $fillable = [
        'user_id', 'title', 'author', 'slug', 'description', 'status', 'cover_image',
        'total_chapters', 'views', 'likes', 'rating', 'rating_count',
        'is_featured', 'is_trending', 'published_at'
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_trending' => 'boolean',
        'published_at' => 'datetime',
        'rating' => 'decimal:2',
    ];

    public function chapters()
    {
        return $this->hasMany(Chapter::class)->orderBy('chapter_number');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function genres()
    {
        return $this->belongsToMany(Genre::class);
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

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Get users who have this novel in their library
     */
    public function libraryEntries()
    {
        return $this->hasMany(UserLibrary::class);
    }

    /**
     * Get top-level comments for this novel
     */
    public function topLevelComments()
    {
        return $this->comments()->whereNull('parent_id')->whereNull('chapter_id');
    }

    /**
     * Update novel rating based on user ratings
     */
    public function updateRating()
    {
        $ratings = $this->ratings();
        $this->rating_count = $ratings->count();
        $this->rating = $this->rating_count > 0 ? round($ratings->avg('rating'), 2) : 0;
        $this->save();
    }

    /**
     * Recalculate and update total chapters count
     */
    public function updateChapterCount()
    {
        $this->total_chapters = $this->chapters()->count();
        $this->save();
    }

    // Scopes
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeTrending($query)
    {
        return $query->where('is_trending', true);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    // Generate slug from title
    public function generateSlug($title = null)
    {
        $title = $title ?: $this->title;
        $slug = \Illuminate\Support\Str::slug($title);

        // Ensure slug is unique
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    // Boot method to automatically generate slug
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($novel) {
            if (empty($novel->slug)) {
                $novel->slug = $novel->generateSlug();
            }
        });

        static::updating(function ($novel) {
            if ($novel->isDirty('title') && empty($novel->slug)) {
                $novel->slug = $novel->generateSlug();
            }
        });

        // Smart cache invalidation on save
        static::saved(function ($novel) {
            // Clear index, search, and aggregated lists
            Cache::tags(['novels-index', 'novels-search', 'novels-latest', 'novels-updated', 'novels-popular', 'novels-recommendations'])->flush();

            // Clear specific novel cache
            Cache::tags(["novel_{$novel->slug}"])->flush();
        });

        // Clear caches on delete
        static::deleted(function ($novel) {
            Cache::tags(['novels-index', 'novels-search', 'novels-latest', 'novels-updated', 'novels-popular', 'novels-recommendations'])->flush();
            Cache::tags(["novel_{$novel->slug}"])->flush();
        });
    }

    // Route key name for route model binding
    public function getRouteKeyName()
    {
        return 'slug';
    }
}
