<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each context that can host a chat keeps its own on/off switch. Chat is
     * disabled by default; when off the thread is shown as unavailable.
     */
    public function up(): void
    {
        foreach (['events', 'groups', 'classrooms'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'chat_enabled')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->boolean('chat_enabled')->default(false);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['events', 'groups', 'classrooms'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'chat_enabled')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('chat_enabled');
                });
            }
        }
    }
};
