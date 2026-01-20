<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Get the novels that belong to this genre.
     */
    public function novels(): BelongsToMany
    {
        return $this->belongsToMany(Novel::class, 'genre_novel');
    }
}
