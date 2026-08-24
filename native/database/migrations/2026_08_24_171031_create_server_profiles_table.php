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
        Schema::create('server_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('instance_id')->unique();
            $table->string('name');
            $table->string('origin');
            $table->string('api_base_url');
            $table->string('web_base_url');
            $table->unsignedInteger('api_version')->default(1);
            $table->json('capabilities');
            $table->json('realtime')->nullable();
            $table->ulid('selected_church_id')->nullable();
            $table->boolean('selected')->default(false)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_profiles');
    }
};
