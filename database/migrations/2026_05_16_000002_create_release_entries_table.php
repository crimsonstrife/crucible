<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('release_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32);
            $table->text('description');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['release_id', 'category', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_entries');
    }
};
