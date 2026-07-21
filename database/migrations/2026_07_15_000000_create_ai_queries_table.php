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
        Schema::create('ai_queries', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('model');
            $table->text('input'); // A pergunta/prompt enviado
            $table->longText('response'); // A resposta recebida
            $table->json('usage')->nullable(); // Informações de uso (tokens, etc)
            $table->string('status')->default('completed'); // completed, error, pending
            $table->text('error_message')->nullable(); // Mensagem de erro se houver
            $table->integer('church_id')->unsigned()->nullable();
            $table->string('type')->nullable();
            //periodo inicio
            $table->dateTime('started_at_filter')->nullable();
            $table->dateTime('finished_at_filter')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('provider');
            $table->index('model');
            $table->index('status');
            $table->index('church_id');
            $table->index('type');
            $table->index('started_at_filter');
            $table->index('finished_at_filter');

            // Foreign keys
            $table->foreign('church_id')->references('id')->on('churches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_queries');
    }
};
