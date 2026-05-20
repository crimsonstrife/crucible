<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('release_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('url', 2048);
            $table->string('platform', 32)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['release_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_links');
    }
};
