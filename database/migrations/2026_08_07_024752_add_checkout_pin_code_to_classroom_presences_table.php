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
            $table->text('checkout_pin_code')->nullable()->after('checkout_pin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classroom_presences', function (Blueprint $table) {
            $table->dropColumn('checkout_pin_code');
        });
    }
};
