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
        Schema::create('networks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('parent_church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignUlid('child_church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignUlid('community_id')->nullable()->constrained('communities')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['parent_church_id', 'child_church_id']); // Evita duplicidade de vínculo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('networks');
    }
};
