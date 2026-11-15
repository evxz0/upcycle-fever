<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOrder extends Model
{
    protected $fillable = [
        'user_id',
        'slot_index',
        'customer_name',
        'customer_avatar',
        'recipe_id',
        'patience_max',
        'patience_remaining',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'slot_index'         => 'integer',
            'patience_max'       => 'integer',
            'patience_remaining' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}

