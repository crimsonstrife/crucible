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
        Schema::create('pull_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('repository_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('number'); // Per-repo sequential number (#1, #2 …)

            $table->string('title');
            $table->text('description')->nullable();

            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_branch');
            $table->string('target_branch');

            $table->string('status')->default('open'); // open | merged | closed

            // Snapshot SHAs captured at PR creation / last update
            $table->string('head_sha', 40)->nullable();  // tip of source_branch
            $table->string('base_sha', 40)->nullable();  // tip of target_branch (merge base)

            // Populated on merge
            $table->foreignUuid('merged_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('merge_commit_sha', 40)->nullable();
            $table->timestamp('merged_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['repository_id', 'number']);
            $table->index(['repository_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_requests');
    }
};
