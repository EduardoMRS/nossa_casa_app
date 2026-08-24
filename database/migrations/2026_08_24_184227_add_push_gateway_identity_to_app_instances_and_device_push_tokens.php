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
        Schema::table('app_instances', function (Blueprint $table) {
            $table->string('push_gateway_public_key', 88)->nullable()->after('key');
            $table->boolean('push_gateway_enabled')->default(false)->after('push_gateway_public_key');
        });

        Schema::table('device_push_tokens', function (Blueprint $table) {
            $table->foreignUlid('app_instance_id')
                ->nullable()
                ->after('id')
                ->constrained('app_instances')
                ->cascadeOnDelete();
            $table->index(['app_instance_id', 'invalidated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_push_tokens', function (Blueprint $table) {
            $table->dropForeign(['app_instance_id']);
            $table->dropIndex(['app_instance_id', 'invalidated_at']);
            $table->dropColumn('app_instance_id');
        });

        Schema::table('app_instances', function (Blueprint $table) {
            $table->dropColumn(['push_gateway_public_key', 'push_gateway_enabled']);
        });
    }
};
