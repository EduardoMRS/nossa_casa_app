<?php

use App\Enums\LiveStreamStatus;
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
        Schema::create('live_streams', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->nullable()->constrained('churches')->cascadeOnDelete();
            $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('path')->unique();
            $table->text('source_url');
            $table->boolean('source_on_demand')->default(false);
            $table->boolean('record')->default(true);
            $table->string('status')->default(LiveStreamStatus::READY->value)->index();
            $table->string('worker_id')->nullable()->index();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_streams');
    }
};
