<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chat messages. A message carries text and/or a single media attachment
     * (image/video/audio) or a link with an OpenGraph preview. Soft-deletable so
     * moderators can remove a message while keeping the thread consistent.
     */
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->string('attachment_path', 2048)->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->json('link_preview')->nullable();
            $table->foreignUlid('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
