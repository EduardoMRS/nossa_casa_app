<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('church_registration_requests', function (Blueprint $table) {
            $table->foreignUlid('requested_parent_church_id')
                ->nullable()
                ->after('community_id')
                ->constrained('churches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('church_registration_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_parent_church_id');
        });
    }
};
