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
        Schema::create('church_storage_backups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained('churches')->cascadeOnDelete();
            $table->string('source_driver', 40)->default('default');
            $table->string('target_driver', 40)->default('default');
            $table->string('backup_disk', 40)->default('local');
            $table->string('backup_directory', 255);
            $table->unsignedInteger('files_count')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->string('status', 30)->default('pending');
            $table->timestamp('cleanup_at');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('church_storage_backups');
    }
};
