<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->json('language_stats')->nullable()->after('lfs_sync_status');
            $table->string('language_stats_head_sha', 40)->nullable()->after('language_stats');
            $table->timestamp('language_stats_updated_at')->nullable()->after('language_stats_head_sha');
        });
    }

    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropColumn([
                'language_stats',
                'language_stats_head_sha',
                'language_stats_updated_at',
            ]);
        });
    }
};
