<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedQRCode extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     * Explicitly set to avoid Laravel deriving 'saved_q_r_codes' from class name.
     */
    protected $table = 'saved_qr_codes';

    protected $fillable = [
        'user_id',
        'qr_code_id',
        'saved_at',
    ];

    protected $casts = [
        'saved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function qrCode()
    {
        return $this->belongsTo(QRCode::class, 'qr_code_id');
    }
}
