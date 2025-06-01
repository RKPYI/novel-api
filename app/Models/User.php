<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'provider',
        'provider_id',
        'avatar',
        'bio',
        'last_login_at',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['is_admin', 'is_verified'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // User roles
    const ROLE_USER = 0;
    const ROLE_ADMIN = 1;

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Accessor for is_admin attribute.
     */
    public function getIsAdminAttribute(): bool
    {
        $roleValue = $this->role;
        $expectedAdminRole = self::ROLE_ADMIN;
        $logMessage = sprintf(
            "User ID: %s, Role Value: %s (Type: %s), Is Role Set: %s, Is Role Empty: %s, Expected Admin Role: %s (Type: %s)",
            $this->id,
            var_export($roleValue, true),
            gettype($roleValue),
            isset($this->role) ? 'true' : 'false',
            empty($this->role) && $this->role !== 0 ? 'true' : 'false', // Check for empty, allowing 0
            var_export($expectedAdminRole, true),
            gettype($expectedAdminRole)
        );
        \Illuminate\Support\Facades\Log::debug($logMessage);
        return $roleValue === $expectedAdminRole;
    }

    /**
     * Accessor for is_verified attribute.
     */
    public function getIsVerifiedAttribute(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Check if user is regular user
     */
    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    /**
     * Get the user's reading progress
     */
    public function readingProgress()
    {
        return $this->hasMany(ReadingProgress::class);
    }

    /**
     * Get the user's comments
     */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get the user's ratings
     */
    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Get the user's comment votes
     */
    public function commentVotes()
    {
        return $this->hasMany(CommentVote::class);
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification);
    }
}
