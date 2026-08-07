<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // 'openai', 'openrouter', 'gemini'
            $table->string('model_id')->unique(); // Ex: 'gpt-4o-mini'
            $table->string('name')->nullable();          
            $table->string('status')->default('active'); 
            $table->integer('position')->default(0);
            $table->unsignedInteger('context_length')->nullable()->default(0);
            $table->string('input_modalities')->nullable(); // Ex: "text,image"
            $table->string('output_modalities')->nullable(); // Ex: "text"
            $table->decimal('price_prompt', 14, 10)->default(0.0000000000);$table->decimal('price_completion', 14, 10)->default(0.0000000000);

            $table->timestamps();

            $table->index(['provider', 'status']);$table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};

