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
        // classrooms
        Schema::create('classrooms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUlid('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // classroom_presences (Rastreamento por aula/data)
        Schema::create('classroom_presences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('classroom_id')->constrained();
            $table->foreignUlid('user_id')->constrained();
            $table->datetime('check_in')->nullable()->default(now());
            $table->datetime('check_out')->nullable();
            $table->timestamps();
        });

        Schema::table('classroom_users', function (Blueprint $table) {
            $table->foreignUlid('classroom_id')->constrained();
            $table->foreignUlid('user_id')->constrained();
            $table->primary(['classroom_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classroom_presences');
        Schema::dropIfExists('classroom_users');
        Schema::dropIfExists('classrooms');
    }
};
