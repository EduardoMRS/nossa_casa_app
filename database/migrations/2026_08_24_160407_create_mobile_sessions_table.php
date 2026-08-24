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
        Schema::create('mobile_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 26);
            $table->string('device_name', 120);
            $table->string('platform', 20);
            $table->string('app_version', 40);
            $table->ulid('token_family')->index();
            $table->unsignedBigInteger('current_access_token_id')->nullable()->unique();
            $table->unsignedTinyInteger('active_slot')->nullable()->default(1);
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'device_id', 'active_slot'], 'mobile_sessions_one_active_device');
            $table->foreign('current_access_token_id')
                ->references('id')
                ->on('personal_access_tokens')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_sessions');
    }
};
