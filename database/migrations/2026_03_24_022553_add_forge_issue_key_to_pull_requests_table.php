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
        Schema::table('pull_requests', function (Blueprint $table) {
            // Forge issue key, e.g. "PROJ-123", linked when PR is created from a Forge issue
            $table->string('forge_issue_key', 50)->nullable()->after('merge_commit_sha');
            $table->index('forge_issue_key');
        });
    }

    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropIndex(['forge_issue_key']);
            $table->dropColumn('forge_issue_key');
        });
    }
};
