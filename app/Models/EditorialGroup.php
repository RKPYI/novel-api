<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class EditorialGroup extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'tag',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** All memberships in this group. */
    public function members(): HasMany
    {
        return $this->hasMany(EditorialGroupMember::class);
    }

    /** The single editor membership. */
    public function editorMember(): HasOne
    {
        return $this->hasOne(EditorialGroupMember::class)->where('role', 'editor');
    }

    /** Author memberships. */
    public function authorMembers(): HasMany
    {
        return $this->hasMany(EditorialGroupMember::class)->where('role', 'author');
    }

    /** Users belonging to this group (via pivot). */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'editorial_group_members')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /** Get the editor User model directly. */
    public function getEditorAttribute()
    {
        return $this->editorMember?->user;
    }

    /** Check if the group already has an editor. */
    public function hasEditor(): bool
    {
        return $this->members()->where('role', 'editor')->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($group) {
            if (empty($group->slug)) {
                $group->slug = $group->generateSlug();
            }
        });
    }

    /** Generate a unique slug from the group name. */
    public function generateSlug(string $name = null): string
    {
        $name = $name ?: $this->name;
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $original . '-' . $counter++;
        }

        return $slug;
    }
}
