<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NovelAsset extends Model
{
    protected $fillable = [
        'novel_id',
        'filename',
        'original_filename',
        'path',
        'mime_type',
        'size',
    ];

    /**
     * Get the novel that owns this asset.
     */
    public function novel()
    {
        return $this->belongsTo(Novel::class);
    }

    /**
     * Get the path attribute.
     * Automatically converts local storage paths to full URLs.
     */
    public function getPathAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if (str_starts_with($value, '/storage/')) {
            return url($value);
        }

        return url('/storage/' . $value);
    }
}
