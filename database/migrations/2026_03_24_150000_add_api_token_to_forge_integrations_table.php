<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forge_integrations', function (Blueprint $table) {
            $table->string('api_token_hash', 64)->nullable()->after('last_synced_at');
            $table->timestamp('api_token_last_used_at')->nullable()->after('api_token_hash');

            $table->unique('api_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('forge_integrations', function (Blueprint $table) {
            $table->dropUnique(['api_token_hash']);
            $table->dropColumn(['api_token_hash', 'api_token_last_used_at']);
        });
    }
};
