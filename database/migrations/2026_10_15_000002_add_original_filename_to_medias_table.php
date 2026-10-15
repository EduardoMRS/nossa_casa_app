<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medias', function (Blueprint $table): void {
            $table->text('original_filename')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('medias', function (Blueprint $table): void {
            $table->dropColumn('original_filename');
        });
    }
};