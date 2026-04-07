<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_lock_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_id')->constrained()->cascadeOnDelete();
            $table->string('pattern');           // glob pattern, e.g. "*.uasset", "Content/**/*.umap"
            $table->string('lock_mode');         // mandatory | advisory
            $table->boolean('auto_lock')->default(false);
            $table->unsignedInteger('lock_timeout_hours')->nullable();
            $table->timestamps();

            $table->index(['repository_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_lock_policies');
    }
};
