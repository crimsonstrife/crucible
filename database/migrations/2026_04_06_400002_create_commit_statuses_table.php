<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commit_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_id')->constrained()->cascadeOnDelete();
            $table->string('sha', 40);
            $table->string('context', 255);       // e.g. "ci/build", "ci/tests"
            $table->string('state', 20);           // pending, success, failure, error
            $table->string('description', 255)->nullable();
            $table->string('target_url', 2048)->nullable();
            $table->foreignUuid('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['repository_id', 'sha', 'context']);
            $table->index(['repository_id', 'sha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commit_statuses');
    }
};
