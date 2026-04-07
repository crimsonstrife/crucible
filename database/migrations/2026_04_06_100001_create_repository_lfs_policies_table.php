<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_lfs_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_id')->constrained()->cascadeOnDelete();
            $table->string('pattern');                  // glob pattern, e.g. "*.uasset"
            $table->unsignedBigInteger('min_size_bytes')->nullable(); // auto-LFS above this size
            $table->string('description')->nullable();  // human-readable label
            $table->timestamps();

            $table->index(['repository_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_lfs_policies');
    }
};
