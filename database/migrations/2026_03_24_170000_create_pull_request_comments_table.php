<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pull_request_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('pull_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->text('body');

            // Optional threading: a reply references the parent comment
            $table->foreignUuid('in_reply_to_id')
                  ->nullable()
                  ->constrained('pull_request_comments')
                  ->nullOnDelete();

            // Optional inline placement (file + diff line position)
            $table->string('file_path')->nullable();
            $table->integer('diff_position')->nullable(); // line index within the diff hunk

            $table->timestamps();
            $table->softDeletes();

            $table->index(['pull_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pull_request_comments');
    }
};
