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
        Schema::create('vercicles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('library_id')->constrained('libraries')->cascadeOnDelete();
            $table->string('book');
            $table->string('chapter');
            $table->string('verse');
            $table->string('content');
            $table->string('version');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vercicles');
    }
};
