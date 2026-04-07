<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('owner_id')->constrained('users');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('vcs_type')->default('git');
            $table->string('visibility')->default('private');
            $table->string('default_branch')->default('main');
            $table->boolean('lfs_enabled')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_fork')->default(false);
            $table->foreignUuid('forked_from_id')->nullable()->constrained('repositories')->nullOnDelete();
            $table->string('forge_project_id')->nullable()->index();
            $table->unsignedBigInteger('size_kb')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'visibility']);
            $table->index('vcs_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
