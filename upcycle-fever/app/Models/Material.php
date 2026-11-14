<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'type',
        'description',
        'icon',
        'eco_impact',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'eco_impact' => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($material) {
            if (empty($material->slug)) {
                $material->slug = Str::slug($material->name) . '-' . Str::random(4);
            }
        });
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}

