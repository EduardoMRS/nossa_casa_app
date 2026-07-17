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
            $table->foreignUlid('church_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->index(); // Usará o enum CategoryType
            $table->timestamps();
        });

        // Tabela pivô polimórfica (category_relations)
        Schema::create('categorizables', function (Blueprint $table) {
            $table->foreignUlid('category_id')->constrained()->cascadeOnDelete();
            $table->ulidMorphs('categorizable'); // Cria categorizable_type e categorizable_id (ULID)
            
            $table->primary(['category_id', 'categorizable_id', 'categorizable_type'], 'categorizables_primary');
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
