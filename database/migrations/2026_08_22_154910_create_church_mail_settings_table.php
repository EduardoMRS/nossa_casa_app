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
        Schema::create('church_mail_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->unique()->constrained('churches')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->boolean('allow_branches')->default(false);
            $table->text('host')->nullable();
            $table->text('port')->nullable();
            $table->text('scheme')->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->text('from_address')->nullable();
            $table->text('from_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('church_mail_settings');
    }
};
