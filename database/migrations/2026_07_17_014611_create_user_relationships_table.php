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
        Schema::create('user_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('related_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship_type'); // Ex: 'friend', 'follower
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_relationships');
    }
};
