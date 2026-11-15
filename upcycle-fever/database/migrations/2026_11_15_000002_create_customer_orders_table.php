<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('slot_index')->default(0);
            $table->string('customer_name', 50);
            $table->string('customer_avatar', 10)->default('👤');
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('patience_max')->default(60);
            $table->unsignedInteger('patience_remaining')->default(60);
            $table->enum('status', ['waiting', 'completed', 'expired'])->default('waiting');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_orders');
    }
};

