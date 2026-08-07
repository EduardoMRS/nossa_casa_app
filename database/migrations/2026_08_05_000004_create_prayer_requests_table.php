<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prayer_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('church_id')->nullable()->constrained('churches')->nullOnDelete();
            $table->text('content');
            $table->timestamps();

            $table->index('church_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_requests');
    }
};
