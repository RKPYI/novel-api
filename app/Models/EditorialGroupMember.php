<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialGroupMember extends Model
{
    protected $fillable = [
        'editorial_group_id',
        'user_id',
        'role',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function group(): BelongsTo
    {
        return $this->belongsTo(EditorialGroup::class, 'editorial_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isEditor(): bool
    {
        return $this->role === 'editor';
    }

    public function isAuthor(): bool
    {
        return $this->role === 'author';
    }
}
