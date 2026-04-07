<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_set_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permission_sets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        // Pivot: permissions granted by a permission set
        // permission_id references permissions.id (bigInteger)
        Schema::create('permission_set_permissions', function (Blueprint $table) {
            $table->foreignUuid('permission_set_id')->constrained('permission_sets')->cascadeOnDelete();
            $table->unsignedBigInteger('permission_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_set_id', 'permission_id']);
        });

        // Pivot: permissions muted (denied) by a permission set
        Schema::create('permission_set_mutes', function (Blueprint $table) {
            $table->foreignUuid('permission_set_id')->constrained('permission_sets')->cascadeOnDelete();
            $table->unsignedBigInteger('permission_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_set_id', 'permission_id']);
        });

        // Pivot: permission sets belong to groups
        Schema::create('group_permission_sets', function (Blueprint $table) {
            $table->foreignUuid('group_id')->constrained('permission_set_groups')->cascadeOnDelete();
            $table->foreignUuid('permission_set_id')->constrained('permission_sets')->cascadeOnDelete();
            $table->primary(['group_id', 'permission_set_id']);
        });

        // Pivot: roles have permission sets
        // role_id references roles.id (bigInteger)
        Schema::create('role_permission_sets', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreignUuid('permission_set_id')->constrained('permission_sets')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_set_id']);
        });

        // Pivot: users have permission sets directly
        Schema::create('user_permission_sets', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('permission_set_id')->constrained('permission_sets')->cascadeOnDelete();
            $table->primary(['user_id', 'permission_set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_sets');
        Schema::dropIfExists('role_permission_sets');
        Schema::dropIfExists('group_permission_sets');
        Schema::dropIfExists('permission_set_mutes');
        Schema::dropIfExists('permission_set_permissions');
        Schema::dropIfExists('permission_sets');
        Schema::dropIfExists('permission_set_groups');
    }
};
