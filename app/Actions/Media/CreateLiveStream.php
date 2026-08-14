<?php

namespace App\Actions\Media;

use App\Enums\LiveStreamStatus;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\LiveStream;
use App\Models\User;
use App\Services\Media\MediaMtxClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateLiveStream
{
    public function __construct(private MediaMtxClient $mediaMtx) {}

    /**
     * @param  array{name: string, source_url?: string, input_mode?: string, source_on_demand?: bool, record?: bool, church_id?: string|null}  $data
     */
    public function handle(User $user, array $data): LiveStream
    {
        $church = $user->role === UserRole::SYSTEM && isset($data['church_id'])
            ? Church::query()->find($data['church_id'])
            : $user->church()->first();

        if (! $church) {
            throw ValidationException::withMessages([
                'church' => __('A church membership is required to create a live stream.'),
            ]);
        }

        try {
            $inputMode = $data['input_mode'] ?? 'pull';
            $liveStream = DB::transaction(fn (): LiveStream => LiveStream::query()->create([
                'name' => $data['name'],
                'source_url' => $inputMode === 'publisher' ? 'publisher' : $data['source_url'],
                'input_mode' => $inputMode,
                'publish_token' => $inputMode === 'publisher' ? Str::random(64) : null,
                'token_rotated_at' => $inputMode === 'publisher' ? now() : null,
                'source_on_demand' => $data['source_on_demand'] ?? false,
                'record' => $data['record'] ?? true,
                'church_id' => $church->getKey(),
                'created_by_id' => $user->id,
                'path' => 'stream-'.Str::lower((string) Str::ulid()),
                'status' => LiveStreamStatus::READY,
                'active_slot' => 1,
                'worker_id' => config('media.worker_id'),
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'church' => __('This church already has an active live stream.'),
            ]);
        }

        try {
            $this->mediaMtx->addPath($liveStream);
        } catch (Throwable $exception) {
            $liveStream->update([
                'status' => LiveStreamStatus::FAILED,
                'last_error' => $exception->getMessage(),
                'active_slot' => null,
            ]);

            throw $exception;
        }

        return $liveStream;
    }
}
