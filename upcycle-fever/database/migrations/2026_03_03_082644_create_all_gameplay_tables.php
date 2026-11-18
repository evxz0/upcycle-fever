<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel game: materials, tools, recipes, recipe_ingredients,
 * player_inventory, stage_sessions, customer_orders, guest_sessions
 */
return new class extends Migration
{
    public function up(): void
    {
        // Materials — bahan limbah
        if (!Schema::hasTable('materials')) {
            Schema::create('materials', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('category');
                $table->string('icon', 20)->default('📦');
                $table->text('description')->nullable();
                $table->integer('eco_impact')->default(0);
                $table->timestamps();
            });
        }

        // Tools — alat kerajinan
        if (!Schema::hasTable('tools')) {
            Schema::create('tools', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('icon', 20)->default('🔧');
                $table->string('action_type')->default('Memproses');
                $table->integer('level_required')->default(1);
                $table->integer('coin_cost')->default(0);
                $table->timestamps();
            });
        }

        // Recipes — resep crafting
        if (!Schema::hasTable('recipes')) {
            Schema::create('recipes', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->foreignId('tool_id')->nullable()->constrained('tools')->nullOnDelete();
                $table->string('output_icon', 20)->default('🎁');
                $table->text('description')->nullable();
                $table->integer('eco_points')->default(10);
                $table->integer('coin_reward')->default(20);
                $table->timestamps();
            });
        }

        // Recipe Ingredients — pivot materials <-> recipes
        if (!Schema::hasTable('recipe_ingredients')) {
            Schema::create('recipe_ingredients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
                $table->foreignId('material_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('quantity')->default(1);
            });
        }

        // Player Inventory — stok bahan per user
        if (!Schema::hasTable('player_inventory')) {
            Schema::create('player_inventory', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('material_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(0);
                $table->timestamps();
                $table->unique(['user_id', 'material_id']);
            });
        }

        // Stage Sessions — sesi permainan per user
        if (!Schema::hasTable('stage_sessions')) {
            Schema::create('stage_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedTinyInteger('stage_number')->default(1);
                $table->unsignedSmallInteger('duration_seconds')->default(120);
                $table->unsignedSmallInteger('spawn_interval_seconds')->default(15);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->string('status', 20)->default('active'); // active|completed|abandoned
                $table->unsignedInteger('orders_completed')->default(0);
                $table->unsignedInteger('orders_expired')->default(0);
                $table->unsignedInteger('coins_earned')->default(0);
                $table->unsignedInteger('eco_points_earned')->default(0);
                $table->unsignedInteger('xp_earned')->default(0);
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }

        // Customer Orders — antrean pesanan per sesi
        if (!Schema::hasTable('customer_orders')) {
            Schema::create('customer_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('stage_session_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedTinyInteger('slot_index')->default(0);
                $table->string('customer_name', 60);
                $table->string('customer_avatar', 20)->default('👤');
                $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedSmallInteger('patience_max')->default(60);
                $table->unsignedSmallInteger('patience_remaining')->default(60);
                $table->timestamp('spawned_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('status', 20)->default('waiting'); // waiting|served|expired
                $table->timestamps();
                $table->index(['stage_session_id', 'status']);
            });
        }

        // Guest Sessions — statistik trial guest
        if (!Schema::hasTable('guest_sessions')) {
            Schema::create('guest_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('guest_token', 64)->unique();
                $table->timestamp('trial_started_at')->nullable();
                $table->timestamp('trial_completed_at')->nullable();
                $table->foreignId('converted_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ip_hash', 64)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_sessions');
        Schema::dropIfExists('customer_orders');
        Schema::dropIfExists('stage_sessions');
        Schema::dropIfExists('player_inventory');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('tools');
        Schema::dropIfExists('materials');
    }
};
