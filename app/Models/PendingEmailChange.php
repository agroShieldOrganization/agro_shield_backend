<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingEmailChange extends Model
{
    protected $fillable = [
        'user_id',
        'new_email',
        'otp_hash',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}