<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'definition',
        'is_active',
    ];

    protected $casts = [
        'definition' => 'array',
        'is_active' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}

