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
        Schema::table('forge_integrations', function (Blueprint $table) {
            // Drop the existing org-level FK and column
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');

            // Add repo-level FK (1:1 — one Forge project per repository)
            $table->foreignUuid('repository_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            $table->unique('repository_id');
        });
    }

    public function down(): void
    {
        Schema::table('forge_integrations', function (Blueprint $table) {
            $table->dropUnique(['repository_id']);
            $table->dropForeign(['repository_id']);
            $table->dropColumn('repository_id');

            $table->foreignUuid('organization_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            $table->index('organization_id');
        });
    }
};
