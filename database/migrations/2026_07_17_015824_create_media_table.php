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
        Schema::create('medias', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('uploader_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('church_id')->nullable()->constrained('churches')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('mimetype');
            $table->unsignedBigInteger('size'); // Tamanho do arquivo em bytes
            $table->boolean('gallery')->default(true); // Se aparece na Galeria
            $table->string('status')->default(\App\Enums\MediaStatus::PENDING->value);
            $table->timestamps();
        });

        // Tabela pivô polimórfica (media_relations)
        Schema::create('mediables', function (Blueprint $table) {
            $table->foreignUlid('media_id')->constrained('medias')->cascadeOnDelete();
            $table->ulidMorphs('mediable'); // Cria mediable_type e mediable_id (ULID)
            
            $table->primary(['media_id', 'mediable_id', 'mediable_type'], 'mediables_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mediables');
        Schema::dropIfExists('medias');
    }
};
