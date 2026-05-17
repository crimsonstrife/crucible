<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tag_name', 255);
            $table->string('commit_sha', 40);
            $table->string('name', 255)->nullable();
            $table->string('slug', 255);
            $table->text('body')->nullable();
            $table->boolean('is_draft')->default(false);
            $table->boolean('is_prerelease')->default(false);
            $table->boolean('is_latest')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['repository_id', 'tag_name']);
            $table->unique(['repository_id', 'slug']);
            $table->index(['repository_id', 'is_draft', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
