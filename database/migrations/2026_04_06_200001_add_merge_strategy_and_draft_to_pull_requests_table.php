<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->string('merge_strategy', 20)->default('merge_commit')->after('status');
            $table->boolean('is_draft')->default(false)->after('merge_strategy');
        });
    }

    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropColumn(['merge_strategy', 'is_draft']);
        });
    }
};
