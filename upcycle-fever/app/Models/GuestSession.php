<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestSession extends Model
{
    protected $fillable = ['guest_token', 'trial_started_at', 'trial_completed_at', 'converted_user_id', 'ip_hash'];
    protected $casts = ['trial_started_at' => 'datetime', 'trial_completed_at' => 'datetime'];

    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }
}
