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
        Schema::create('posts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('church_id')->nullable()->constrained('churches')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('postables', function (Blueprint $table) {
            $table->foreignUlid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->ulidMorphs('postable'); // Cria postable_type e postable_id (ULID)            
            $table->primary(['post_id', 'postable_id', 'postable_type'], 'postables_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postables');
        Schema::dropIfExists('posts');
    }
};
