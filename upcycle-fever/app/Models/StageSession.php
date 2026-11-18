<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StageSession extends Model
{
    protected $fillable = [
        'user_id',
        'stage_number',
        'duration_seconds',
        'spawn_interval_seconds',
        'started_at',
        'ends_at',
        'ended_at',
        'status',
        'orders_completed',
        'orders_expired',
        'coins_earned',
        'eco_points_earned',
        'xp_earned',
    ];

    protected function casts(): array
    {
        return [
            'stage_number'           => 'integer',
            'duration_seconds'       => 'integer',
            'spawn_interval_seconds' => 'integer',
            'started_at'             => 'datetime',
            'ends_at'                => 'datetime',
            'ended_at'               => 'datetime',
            'orders_completed'       => 'integer',
            'orders_expired'         => 'integer',
            'coins_earned'           => 'integer',
            'eco_points_earned'      => 'integer',
            'xp_earned'              => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class);
    }

    public function remainingSeconds(): int
    {
        if ($this->status !== 'active' || !$this->ends_at) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->ends_at, false));
    }
}
