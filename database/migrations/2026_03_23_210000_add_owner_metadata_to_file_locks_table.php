<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_locks', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
        });

        Schema::table('file_locks', function (Blueprint $table) {
            $table->string('owner_name')->nullable()->after('locked_by');
            $table->string('owner_identifier')->nullable()->after('owner_name');
            $table->boolean('owner_is_external')->default(false)->after('owner_identifier');
            $table->foreignUuid('locked_by')->nullable()->change();
        });

        Schema::table('file_locks', function (Blueprint $table) {
            $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();
        });

        DB::table('file_locks')
            ->leftJoin('users', 'users.id', '=', 'file_locks.locked_by')
            ->select('file_locks.id', 'file_locks.locked_by', 'users.name', 'users.email')
            ->orderBy('file_locks.id')
            ->get()
            ->each(function (object $lock): void {
                DB::table('file_locks')
                    ->where('id', $lock->id)
                    ->update([
                        'owner_name' => $lock->name,
                        'owner_identifier' => $lock->email ?? $lock->locked_by,
                        'owner_is_external' => $lock->name === null && $lock->email === null,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('file_locks', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropColumn(['owner_name', 'owner_identifier', 'owner_is_external']);
        });

        Schema::table('file_locks', function (Blueprint $table) {
            $table->foreign('locked_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
