<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'tool_id',
        'output_icon',
        'description',
        'eco_points',
        'coin_reward',
    ];

    protected function casts(): array
    {
        return [
            'eco_points'  => 'integer',
            'coin_reward' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($recipe) {
            if (empty($recipe->slug)) {
                $recipe->slug = Str::slug($recipe->name) . '-' . Str::random(4);
            }
        });
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'recipe_ingredients')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}

