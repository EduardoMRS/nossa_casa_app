<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_session_refresh_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('mobile_session_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamps();

            $table->index(['mobile_session_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_session_refresh_tokens');
    }
};
