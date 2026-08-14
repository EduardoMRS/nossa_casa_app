<?php

use App\Enums\RecordingStatus;
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
        Schema::create('recordings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_stream_id')->constrained()->cascadeOnDelete();
            $table->string('worker_id')->nullable()->index();
            $table->text('worker_path');
            $table->char('worker_path_hash', 64)->unique();
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('duration')->nullable();
            $table->string('status')->default(RecordingStatus::WAITING_UPLOAD->value)->index();
            $table->timestamp('uploaded_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recordings');
    }
};
