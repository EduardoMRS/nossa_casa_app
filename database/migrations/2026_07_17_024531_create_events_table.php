<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // events
        Schema::create('events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained();
            $table->foreignUlid('author_id')->constrained('users');
            $table->string('title');
            $table->string('slug')->unique();
            $table->json('tags')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->timestamps();
        });

        // event_users (Inscrições)
        Schema::create('event_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, approved, rejected, canceled
            $table->timestamps();
        });

        // event_confirmations (Check-in/Check-out)
        Schema::create('event_confirmations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_user_id')->constrained('event_users')->cascadeOnDelete();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_confirmations');
        Schema::dropIfExists('event_users');
        Schema::dropIfExists('events');
    }
};
