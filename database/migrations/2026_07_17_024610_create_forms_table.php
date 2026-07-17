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
        // forms
        Schema::create('forms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->string('description')->nullable();
            $table->fopenoreignUlid('church_id')->constrained()->cascadeOnDelete();
            $table->fopenoreignUlid('category_id')->constrained()->cascadeOnDelete();
            $table->json('schema'); // Onde guardamos a estrutura do form (labels, inputs, validations)
            $table->timestamps();
        });

        // form_relations (Polimórfica: vincular a Event ou Post)
        Schema::create('form_relations', function (Blueprint $table) {
            $table->foreignUlid('form_id')->constrained()->cascadeOnDelete();
            $table->ulidMorphs('formable'); // formable_id e formable_type
            $table->timestamps();
        });

        // form_responses
        Schema::create('form_responses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('form_id')->constrained();
            $table->foreignUlid('user_id')->constrained();
            $table->json('answers'); // Onde guardamos as respostas do usuário
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_responses');
        Schema::dropIfExists('form_relations');
        Schema::dropIfExists('forms');
    }
};
