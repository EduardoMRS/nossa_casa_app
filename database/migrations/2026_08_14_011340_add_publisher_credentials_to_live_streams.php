<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->string('input_mode')->default('pull')->after('source_url');
            $table->text('publish_token')->nullable()->after('input_mode');
            $table->timestamp('token_rotated_at')->nullable()->after('publish_token');
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn(['input_mode', 'publish_token', 'token_rotated_at']);
        });
    }
};
