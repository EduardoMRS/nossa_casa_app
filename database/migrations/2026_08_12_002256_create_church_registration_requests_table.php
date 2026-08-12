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
        Schema::create('church_registration_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('approved_church_id')->nullable()->constrained('churches')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('domain')->index();
            $table->text('description')->nullable();
            $table->date('found_date')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('church_registration_requests');
    }
};
