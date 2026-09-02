<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_network_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('requesting_church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignUlid('parent_church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignUlid('child_church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignUlid('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('responded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['parent_church_id', 'status']);
            $table->index(['child_church_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_network_requests');
    }
};
