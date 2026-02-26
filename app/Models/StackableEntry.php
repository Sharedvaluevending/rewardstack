<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StackableEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'stackable_pool_id',
        'business_id',
        'promotion_id',
        'sort_order',
        'is_featured',
        'is_active',
        'is_approved',
        'approved_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function pool()
    {
        return $this->belongsTo(StackablePool::class, 'stackable_pool_id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_approved', true);
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    // Helpers
    public function approve(): void
    {
        $this->update([
            'is_approved' => true,
            'approved_at' => now(),
        ]);
    }
}
