<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_protection_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_id')->constrained()->cascadeOnDelete();
            $table->string('pattern');                       // glob pattern, e.g. "main", "release/*"
            $table->boolean('require_pull_request')->default(true);
            $table->unsignedSmallInteger('required_approvals')->default(0);
            $table->boolean('require_status_checks')->default(false);
            $table->json('required_status_checks')->nullable(); // ["ci/build", "ci/test"]
            $table->json('restrict_push_to_roles')->nullable(); // ["maintain", "admin"]
            $table->boolean('allow_force_push')->default(false);
            $table->boolean('allow_deletion')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['repository_id', 'pattern']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_protection_rules');
    }
};
