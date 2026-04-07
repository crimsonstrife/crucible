<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_locks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('locked_by')->constrained('users');
            $table->string('path');
            $table->string('ref')->nullable();
            $table->timestamp('locked_at');
            $table->timestamps();

            $table->unique(['repository_id', 'path']);
            $table->index(['repository_id', 'locked_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_locks');
    }
};
