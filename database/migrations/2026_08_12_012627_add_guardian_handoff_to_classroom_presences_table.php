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
        Schema::table('classroom_presences', function (Blueprint $table) {
            $table->foreignUlid('dropoff_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('dropoff_name')->nullable()->after('dropoff_user_id');
            $table->string('dropoff_phone', 40)->nullable()->after('dropoff_name');
            $table->foreignUlid('pickup_user_id')->nullable()->after('check_out')->constrained('users')->nullOnDelete();
            $table->string('pickup_name')->nullable()->after('pickup_user_id');
            $table->string('pickup_phone', 40)->nullable()->after('pickup_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classroom_presences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dropoff_user_id');
            $table->dropConstrainedForeignId('pickup_user_id');
            $table->dropColumn(['dropoff_name', 'dropoff_phone', 'pickup_name', 'pickup_phone']);
        });
    }
};
