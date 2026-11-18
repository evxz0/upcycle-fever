<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOrder extends Model
{
    protected $fillable = [
        'user_id',
        'stage_session_id',
        'slot_index',
        'customer_name',
        'customer_avatar',
        'recipe_id',
        'patience_max',
        'patience_remaining',
        'spawned_at',
        'expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'stage_session_id'   => 'integer',
            'slot_index'         => 'integer',
            'patience_max'       => 'integer',
            'patience_remaining' => 'integer',
            'spawned_at'         => 'datetime',
            'expires_at'         => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stageSession(): BelongsTo
    {
        return $this->belongsTo(StageSession::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function computedPatienceRemaining(): int
    {
        return (int) floor($this->computedPatienceSecondsFloat());
    }

    /**
     * Hitung sisa kesabaran dalam detik pecahan (presisi milidetik) untuk mencegah pembulatan merugikan di batas 70%.
     */
    public function computedPatienceSecondsFloat(): float
    {
        if ($this->status !== 'waiting') {
            return 0.0;
        }

        if ($this->expires_at) {
            return max(0.0, now()->diffInMilliseconds($this->expires_at, false) / 1000.0);
        }

        return (float) max(0, $this->patience_remaining);
    }
}

