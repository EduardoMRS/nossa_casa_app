<?php

use App\Enums\LiveStreamStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->unsignedTinyInteger('active_slot')->nullable()->after('status');
        });

        DB::table('live_streams')
            ->whereIn('status', [
                LiveStreamStatus::READY->value,
                LiveStreamStatus::LIVE->value,
                LiveStreamStatus::OFFLINE->value,
            ])
            ->update(['active_slot' => 1]);

        Schema::table('live_streams', function (Blueprint $table) {
            $table->unique(['church_id', 'active_slot'], 'live_streams_one_active_per_church');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->index();
            $table->foreignUlid('pinned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pinned_at')->nullable();
        });

        Schema::table('medias', function (Blueprint $table) {
            $table->string('disk')->nullable()->after('file_path');
        });

        Schema::table('recordings', function (Blueprint $table) {
            $table->foreignUlid('media_id')->nullable()->unique()->constrained('medias')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_id');
        });

        Schema::table('medias', function (Blueprint $table) {
            $table->dropColumn('disk');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pinned_by_id');
            $table->dropColumn(['is_pinned', 'pinned_at']);
        });

        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropUnique('live_streams_one_active_per_church');
            $table->dropColumn('active_slot');
        });
    }
};
