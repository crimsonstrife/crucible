<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sparse_checkout_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);           // e.g. "Art Only", "Code Only"
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->json('include_paths');           // ["/Content/Textures", "/Content/Materials"]
            $table->json('exclude_paths')->nullable(); // ["!/Content/Textures/Debug"]
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['repository_id', 'slug']);
            $table->index('repository_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sparse_checkout_profiles');
    }
};
