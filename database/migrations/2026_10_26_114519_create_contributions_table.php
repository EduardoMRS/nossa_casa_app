<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tithe and offering records. Both authenticated and anonymous visitors may
     * register a contribution, so user_id is nullable. There is no payment
     * processing yet; these are declared contributions for treasury tracking.
     */
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->decimal('amount', 12, 2);
            $table->string('contributor_name')->nullable();
            $table->string('message', 500)->nullable();
            $table->timestamp('contributed_at');
            $table->timestamps();

            $table->index(['church_id', 'type']);
            $table->index(['church_id', 'contributed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
