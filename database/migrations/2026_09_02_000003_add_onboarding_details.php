<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            $table->string('default_locale', 10)->default('pt')->after('found_date');
        });

        Schema::table('church_registration_requests', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('locale', 10)->default('pt')->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('church_registration_requests', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude', 'locale']);
        });

        Schema::table('communities', function (Blueprint $table): void {
            $table->dropColumn('default_locale');
        });
    }
};
