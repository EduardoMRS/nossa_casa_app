<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-church treasury settings used to receive tithes and offerings. There
     * is no in-app payment in this phase: the church publishes its Pix key or
     * bank account and contributors transfer directly. Sensitive fields are
     * encrypted at the model level.
     */
    public function up(): void
    {
        Schema::create('treasury_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->unique()->constrained('churches')->cascadeOnDelete();
            $table->string('pix_key_type')->nullable();
            $table->text('pix_key')->nullable();
            $table->string('bank_name')->nullable();
            $table->text('bank_account')->nullable();
            $table->string('account_holder')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('accepts_contributions')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_settings');
    }
};
