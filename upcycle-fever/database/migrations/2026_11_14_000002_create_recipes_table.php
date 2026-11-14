<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->string('output_icon')->default('🪴');
            $table->text('description')->nullable();
            $table->integer('eco_points')->default(30);
            $table->integer('coin_reward')->default(50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
