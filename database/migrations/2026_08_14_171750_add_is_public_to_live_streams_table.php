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
        Schema::table('live_streams', function (Blueprint $table) {
            $table->boolean('is_public')->default(true)->after('record');
            $table->index(['church_id', 'is_public', 'status'], 'live_streams_public_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropIndex('live_streams_public_status_index');
            $table->dropColumn('is_public');
        });
    }
};
