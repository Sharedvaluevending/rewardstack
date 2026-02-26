<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessPartnership extends Model
{
    use HasFactory;

    protected $fillable = [
        'requester_business_id',
        'partner_business_id',
        'status',
        'message',
        'response_message',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_DECLINED = 'declined';
    const STATUS_CANCELLED = 'cancelled';

    // Relationships
    public function requesterBusiness()
    {
        return $this->belongsTo(Business::class, 'requester_business_id');
    }

    public function partnerBusiness()
    {
        return $this->belongsTo(Business::class, 'partner_business_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where(function ($q) use ($businessId) {
            $q->where('requester_business_id', $businessId)
              ->orWhere('partner_business_id', $businessId);
        });
    }

    // Helpers
    public function accept(?string $message = null): void
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'response_message' => $message,
            'responded_at' => now(),
        ]);
    }

    public function decline(?string $message = null): void
    {
        $this->update([
            'status' => self::STATUS_DECLINED,
            'response_message' => $message,
            'responded_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'responded_at' => now(),
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Get the other business in the partnership (not the given one)
     */
    public function getOtherBusiness(int $businessId): ?Business
    {
        if ($this->requester_business_id === $businessId) {
            return $this->partnerBusiness;
        }
        return $this->requesterBusiness;
    }
}
