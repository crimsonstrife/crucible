<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('shareable_type', 191);
            $table->string('shareable_id', 64);
            $table->string('principal_type', 191);
            $table->string('principal_id', 64);
            $table->unsignedTinyInteger('access_level');
            $table->boolean('propagate_to_children')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('grantor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique([
                'shareable_type',
                'shareable_id',
                'principal_type',
                'principal_id',
            ], 'record_shares_unique');

            $table->index(['shareable_type', 'shareable_id', 'access_level']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_shares');
    }
};
