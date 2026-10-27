<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the optional social media / group chat link to groups for
     * installations that created the groups table before the column existed.
     * Guarded so fresh installs (where the create migration already adds it)
     * don't fail.
     */
    public function up(): void
    {
        if (Schema::hasTable('groups') && ! Schema::hasColumn('groups', 'social_link')) {
            Schema::table('groups', function (Blueprint $table): void {
                $table->string('social_link', 2048)->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('groups') && Schema::hasColumn('groups', 'social_link')) {
            Schema::table('groups', function (Blueprint $table): void {
                $table->dropColumn('social_link');
            });
        }
    }
};
