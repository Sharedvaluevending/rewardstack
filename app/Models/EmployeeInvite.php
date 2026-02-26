<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmployeeInvite extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'email',
        'token',
        'role',
        'invited_by',
        'expires_at',
        'accepted_at',
        'user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Generate a unique invite token
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    /**
     * Create a new invite
     */
    public static function createInvite(int $businessId, string $email, int $invitedBy, string $role = 'employee'): self
    {
        return self::create([
            'business_id' => $businessId,
            'email' => strtolower($email),
            'token' => self::generateToken(),
            'role' => $role,
            'invited_by' => $invitedBy,
            'expires_at' => now()->addDays(7), // 7 day expiry
        ]);
    }

    /**
     * Business relationship
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Inviter relationship
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Accepted user relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if invite is valid
     */
    public function isValid(): bool
    {
        return $this->expires_at->isFuture() && is_null($this->accepted_at);
    }

    /**
     * Get the invite URL
     */
    public function getUrlAttribute(): string
    {
        $baseUrl = rtrim((string) config('app.url', ''), '/');
        if ($baseUrl === '') {
            return url("/employee/accept/{$this->token}");
        }

        return "{$baseUrl}/employee/accept/{$this->token}";
    }
}
