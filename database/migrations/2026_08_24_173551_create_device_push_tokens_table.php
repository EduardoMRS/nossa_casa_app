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
        Schema::create('device_push_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('mobile_session_id')->nullable()->constrained()->nullOnDelete();
            $table->ulid('device_id');
            $table->string('transport', 20);
            $table->char('token_hash', 64)->unique();
            $table->text('token');
            $table->string('app_version', 40)->nullable();
            $table->string('locale', 20)->nullable();
            $table->json('enabled_categories');
            $table->timestamp('last_seen_at');
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id', 'transport']);
            $table->index(['user_id', 'invalidated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_push_tokens');
    }
};
