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
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->after('end_time');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_event_private')->default(false)->index()->after('expires_at');
        });

        Schema::create('event_posts', function (Blueprint $table) {
            $table->foreignUlid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUlid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->primary(['event_id', 'post_id']);
        });

        Schema::create('event_responsibles', function (Blueprint $table) {
            $table->foreignUlid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['event_id', 'user_id']);
        });

        Schema::create('event_materials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUlid('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->text('url')->nullable();
            $table->string('file_path')->nullable();
            $table->string('disk')->nullable();
            $table->string('mimetype')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_materials');
        Schema::dropIfExists('event_responsibles');
        Schema::dropIfExists('event_posts');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('is_event_private');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
