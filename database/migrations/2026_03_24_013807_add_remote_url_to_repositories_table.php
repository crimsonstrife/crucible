<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            // Encrypted so embedded credentials (tokens in HTTPS URLs) are not stored in plaintext.
            $table->text('remote_url')->nullable()->after('forge_project_id');
            $table->timestamp('last_synced_at')->nullable()->after('remote_url');
            $table->boolean('auto_sync')->default(false)->after('last_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropColumn(['remote_url', 'last_synced_at', 'auto_sync']);
        });
    }
};
