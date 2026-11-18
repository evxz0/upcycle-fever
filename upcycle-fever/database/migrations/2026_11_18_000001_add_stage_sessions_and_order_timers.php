<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('stage_number')->default(1);
            $table->unsignedInteger('duration_seconds')->default(120);
            $table->unsignedInteger('spawn_interval_seconds')->default(15);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->unsignedInteger('orders_completed')->default(0);
            $table->unsignedInteger('orders_expired')->default(0);
            $table->unsignedInteger('coins_earned')->default(0);
            $table->unsignedInteger('eco_points_earned')->default(0);
            $table->unsignedInteger('xp_earned')->default(0);
            $table->timestamps();
        });

        Schema::table('customer_orders', function (Blueprint $table) {
            $table->foreignId('stage_session_id')
                ->nullable()
                ->after('user_id')
                ->constrained('stage_sessions')
                ->nullOnDelete();
            $table->timestamp('spawned_at')->nullable()->after('patience_remaining');
            $table->timestamp('expires_at')->nullable()->after('spawned_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stage_session_id');
            $table->dropColumn(['spawned_at', 'expires_at']);
        });

        Schema::dropIfExists('stage_sessions');
    }
};
