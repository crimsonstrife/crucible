<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('forge_org_id')->nullable()->unique()->after('slug');
            $table->string('forge_org_slug')->nullable()->after('forge_org_id');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['forge_org_id']);
            $table->dropColumn(['forge_org_id', 'forge_org_slug']);
        });
    }
};
