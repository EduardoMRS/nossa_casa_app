<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_presences', function (Blueprint $table): void {
            $table->string('checkout_pin')->nullable()->after('check_out');
            $table->timestamp('pin_generated_at')->nullable()->after('checkout_pin');
            $table->index(['classroom_id', 'user_id', 'check_out']);
        });
    }

    public function down(): void
    {
        Schema::table('classroom_presences', function (Blueprint $table): void {
            $table->dropIndex(['classroom_id', 'user_id', 'check_out']);
            $table->dropColumn(['checkout_pin', 'pin_generated_at']);
        });
    }
};
