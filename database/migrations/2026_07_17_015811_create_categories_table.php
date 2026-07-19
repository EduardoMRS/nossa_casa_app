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
        Schema::create('categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->nullable()->constrained('churches')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('slug')->nullable()->index();
            $table->string('type')->nullable()->index(); // Usará o enum CategoryType
            $table->timestamps();
        });

        // Tabela pivô polimórfica (category_relations)
        Schema::create('categorizables', function (Blueprint $table) {
            $table->foreignUlid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->ulidMorphs('categorizable');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorizables');
        Schema::dropIfExists('categories');
    }
};
