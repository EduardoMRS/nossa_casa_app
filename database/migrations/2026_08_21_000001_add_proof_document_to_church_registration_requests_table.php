<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('church_registration_requests', function (Blueprint $table) {
            $table->string('proof_document_path')->nullable()->after('address');
            $table->string('proof_document_name')->nullable()->after('proof_document_path');
            $table->string('proof_document_mime')->nullable()->after('proof_document_name');
        });
    }

    public function down(): void
    {
        Schema::table('church_registration_requests', function (Blueprint $table) {
            $table->dropColumn([
                'proof_document_path',
                'proof_document_name',
                'proof_document_mime',
            ]);
        });
    }
};
