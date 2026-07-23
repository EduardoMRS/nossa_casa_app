<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_queries', function (Blueprint $table): void {
            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_queries', function (Blueprint $table): void {
            $table->dropForeign(['church_id']);
        });
    }
};
