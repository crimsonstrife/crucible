<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lfs_objects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_id')->constrained()->cascadeOnDelete();
            $table->string('oid', 128);
            $table->unsignedBigInteger('size');
            $table->string('mime_type')->nullable();
            $table->string('storage_path', 2048)->nullable();
            $table->timestamps();

            $table->unique(['repository_id', 'oid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lfs_objects');
    }
};
