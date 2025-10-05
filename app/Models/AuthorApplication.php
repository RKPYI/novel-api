<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorApplication extends Model
{
    protected $fillable = [
        'user_id',
        'pen_name',
        'bio',
        'writing_experience',
        'sample_work',
        'portfolio_url',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get the user who submitted this application
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who reviewed this application
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Check if application is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if application is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if application is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Validation rules for author applications
     */
    public static function validationRules(): array
    {
        return [
            'pen_name' => 'nullable|string|max:255',
            'bio' => 'required|string|min:50|max:1000',
            'writing_experience' => 'required|string|min:100|max:2000',
            'sample_work' => 'nullable|string|max:5000',
            'portfolio_url' => 'nullable|url|max:255',
        ];
    }
}
