<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('coins')->default(265)->after('last_seen_at');
            $table->unsignedInteger('eco_points')->default(10)->after('coins');
            $table->unsignedInteger('xp')->default(0)->after('eco_points');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['coins', 'eco_points', 'xp']);
        });
    }
};

