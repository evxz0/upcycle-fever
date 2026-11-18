<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('item_type', ['material', 'product'])->default('material');
            $table->foreignId('material_id')->nullable()->constrained('materials')->cascadeOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained('recipes')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'item_type', 'material_id', 'recipe_id'], 'user_inventory_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
