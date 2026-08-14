<?php

namespace App\Services\Media;

use App\Models\LiveStream;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class MediaMtxClient
{
    public function addPath(LiveStream $liveStream): void
    {
        $this->request()
            ->post('/v3/config/paths/add/'.rawurlencode($liveStream->path), [
                'source' => $liveStream->input_mode === 'publisher' ? 'publisher' : $liveStream->source_url,
                'sourceOnDemand' => $liveStream->source_on_demand,
                'record' => $liveStream->record,
                'recordPath' => rtrim((string) config('media.recordings_root'), '/\\')
                    .'/%path/%Y-%m-%d_%H-%M-%S-%f',
                'recordSegmentDuration' => config('media.mediamtx.record_segment_duration'),
                'recordDeleteAfter' => '0s',
            ])
            ->throw();
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
            ->retry([100, 500]);

        $apiToken = config('media.mediamtx.api_token');

        return is_string($apiToken) && $apiToken !== ''
            ? $request->withToken($apiToken)
            : $request;
    }
}
