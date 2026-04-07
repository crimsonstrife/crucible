<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssh_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('fingerprint')->unique();
            $table->text('public_key');
            $table->string('key_type')->default('ssh-ed25519');
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_deploy_key')->default(false);
            $table->foreignUuid('deploy_repo_id')->nullable()->constrained('repositories')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssh_keys');
    }
};
