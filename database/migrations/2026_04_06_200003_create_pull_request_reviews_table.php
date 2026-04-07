<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pull_request_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pull_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('state', 20)->default('pending'); // pending, approved, changes_requested, commented
            $table->text('body')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pull_request_id', 'reviewer_id']);
            $table->index(['pull_request_id', 'state']);
        });

        // Requested reviewers pivot
        Schema::create('pull_request_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('pull_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pull_request_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pull_request_reviewers');
        Schema::dropIfExists('pull_request_reviews');
    }
};
