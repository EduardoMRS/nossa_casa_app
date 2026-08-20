<?php

namespace App\Services\Media;

use App\Models\LiveStream;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class MediaMtxClient
{
    public function addPath(LiveStream $liveStream): void
    {
        $this->request()
            ->post('/v3/config/paths/add/'.rawurlencode($liveStream->path), $this->pathConfiguration($liveStream))
            ->throw();
    }

    /** @return list<string> */
    public function configuredPathNames(): array
    {
        $response = $this->request()
            ->get('/v3/config/paths/list')
            ->throw();

        return collect($response->json('items', []))
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name))
            ->values()
            ->all();
    }

    public function removePath(LiveStream $liveStream): void
    {
        $response = $this->request()
            ->delete('/v3/config/paths/delete/'.rawurlencode($liveStream->path));

        if ($response->failed() && ! $response->notFound()) {
            throw new RequestException($response);
        }
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('media.mediamtx.api_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout((float) config('media.mediamtx.connect_timeout'))
            ->timeout((float) config('media.mediamtx.timeout'))
            ->retry(
                [100, 500],
                when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
                throw: false,
            );

        $apiToken = config('media.mediamtx.api_token');

        return is_string($apiToken) && $apiToken !== ''
            ? $request->withToken($apiToken)
            : $request;
    }

    /**
     * @return array{source: string, sourceOnDemand: bool, record: bool, recordPath: string, recordSegmentDuration: mixed, recordDeleteAfter: string}
     */
    private function pathConfiguration(LiveStream $liveStream): array
    {
        return [
            'source' => $liveStream->input_mode === 'publisher' ? 'publisher' : $liveStream->source_url,
            'sourceOnDemand' => $liveStream->source_on_demand,
            'record' => $liveStream->record,
            'recordPath' => rtrim((string) config('media.recordings_root'), '/\\')
                .'/%path/%Y-%m-%d_%H-%M-%S-%f',
            'recordSegmentDuration' => config('media.mediamtx.record_segment_duration'),
            'recordDeleteAfter' => '0s',
        ];
    }
}
