<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Groups represent cells, Bible studies and similar gatherings. Each group
     * belongs to a church, is organized by a category (e.g. cell, Bible study),
     * has an optional meeting place (polymorphic address) and one or more
     * responsible users (group_user pivot).
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained('churches')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->unique(['church_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
