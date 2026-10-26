<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links groups to their responsible users (leaders).
     */
    public function up(): void
    {
        Schema::create('group_user', function (Blueprint $table) {
            $table->foreignUlid('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['group_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_user');
    }
};
