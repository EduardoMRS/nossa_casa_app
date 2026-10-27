<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conversations power the chat. A conversation is either a DIRECT thread
     * (member <-> church leadership/finance) or a CONTEXT thread attached to an
     * Event, Group or Classroom (polymorphic conversable). ULIDs avoid integer
     * id exhaustion in a high-volume table.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained('churches')->cascadeOnDelete();
            $table->string('type');
            $table->nullableUlidMorphs('conversable');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
