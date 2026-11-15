<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tool extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'action_type',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(function ($tool) {
            if (empty($tool->slug)) {
                $tool->slug = Str::slug($tool->name) . '-' . Str::random(4);
            }
        });
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
}

