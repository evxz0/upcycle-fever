<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom preferences (JSON) ke tabel users
 * untuk menyimpan avatar, pengaturan suara, dan kualitas grafis.
 * (F-01: Profile Management Technical Task)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('preferences')->nullable()->after('last_seen_at')
                  ->comment('JSON: avatar, sound_enabled, graphic_quality');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });
    }
};
