<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lfs_upload_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_id')->constrained()->cascadeOnDelete();
            $table->string('oid', 64);
            $table->unsignedBigInteger('total_size');
            $table->unsignedBigInteger('uploaded_bytes')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['repository_id', 'oid']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lfs_upload_sessions');
    }
};
