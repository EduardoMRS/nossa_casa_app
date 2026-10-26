<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores anonymous access logs for public pages only. No user identifier is
     * ever persisted so the records cannot be tied back to an authenticated
     * person; the IP address is stored as a one-way hash for privacy (LGPD).
     */
    public function up(): void
    {
        Schema::create('public_access_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->nullable()->constrained('churches')->nullOnDelete();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('path', 2048);
            $table->string('route_name')->nullable();
            $table->string('referer_host')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('locale', 12)->nullable();
            $table->timestamp('visited_at')->index();
            $table->timestamps();

            $table->index(['country_code', 'region']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_access_logs');
    }
};
