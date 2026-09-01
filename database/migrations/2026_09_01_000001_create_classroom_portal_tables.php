<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('description');
            $table->string('accent_color', 7)->nullable()->after('cover_path');
            $table->boolean('portal_enabled')->default(true)->after('accent_color');
            $table->json('portal_settings')->nullable()->after('portal_enabled');
        });

        DB::table('classrooms')->orderBy('id')->get(['id', 'church_id', 'name'])->each(function (object $classroom): void {
            $base = Str::slug((string) $classroom->name) ?: 'classroom';
            $slug = $base;
            $suffix = 2;

            while (DB::table('classrooms')
                ->where('church_id', $classroom->church_id)
                ->where('slug', $slug)
                ->where('id', '!=', $classroom->id)
                ->exists()) {
                $slug = $base.'-'.$suffix++;
            }

            DB::table('classrooms')->where('id', $classroom->id)->update(['slug' => $slug]);
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->unique(['church_id', 'slug']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('visibility', 30)->default('public')->index()->after('is_event_private');
            $table->boolean('comments_enabled')->default(true)->after('visibility');
            $table->boolean('reactions_enabled')->default(true)->after('comments_enabled');
        });

        DB::table('posts')->where('is_event_private', true)->update(['visibility' => 'event_private']);

        Schema::create('classroom_activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignUlid('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->unsignedSmallInteger('max_attempts')->default(1);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['classroom_id', 'is_published']);
        });

        Schema::create('classroom_activity_submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('classroom_activity_id')->constrained('classroom_activities')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('answers');
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['classroom_activity_id', 'user_id', 'attempt'], 'classroom_activity_attempt_unique');
        });

        Schema::create('classroom_materials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignUlid('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('url')->nullable();
            $table->string('file_path')->nullable();
            $table->string('disk')->nullable();
            $table->string('mimetype')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('classroom_discussions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignUlid('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('pinned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->index(['classroom_id', 'is_pinned', 'last_activity_at']);
        });

        Schema::create('classroom_discussion_replies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('classroom_discussion_id')->constrained('classroom_discussions')->cascadeOnDelete();
            $table->foreignUlid('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('parent_id')->nullable()->constrained('classroom_discussion_replies')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_discussion_replies');
        Schema::dropIfExists('classroom_discussions');
        Schema::dropIfExists('classroom_materials');
        Schema::dropIfExists('classroom_activity_submissions');
        Schema::dropIfExists('classroom_activities');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['visibility', 'comments_enabled', 'reactions_enabled']);
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropUnique(['church_id', 'slug']);
            $table->dropColumn(['cover_path', 'accent_color', 'portal_enabled', 'portal_settings']);
        });
    }
};
