<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('church_registration_requests', function (Blueprint $table): void {
            $table->string('country')->nullable()->after('address');
            $table->string('state', 2)->nullable()->after('country');
            $table->string('city')->nullable()->after('state');
            $table->string('neighborhood')->nullable()->after('city');
            $table->string('street')->nullable()->after('neighborhood');
            $table->string('number')->nullable()->after('street');
            $table->string('complement')->nullable()->after('number');
            $table->string('zipcode', 20)->nullable()->after('complement');
            $table->decimal('latitude', 10, 7)->nullable()->after('zipcode');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('church_registration_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'country',
                'state',
                'city',
                'neighborhood',
                'street',
                'number',
                'complement',
                'zipcode',
                'latitude',
                'longitude',
            ]);
        });
    }
};