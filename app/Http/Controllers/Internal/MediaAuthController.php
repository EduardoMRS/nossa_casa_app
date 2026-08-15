<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MediaAuthController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if ($request->string('action')->toString() !== 'publish') {
            return response('Unauthorized', 401);
        }

        $liveStream = LiveStream::query()
            ->where('path', $request->string('path')->toString())
            ->where('input_mode', 'publisher')
            ->where('active_slot', 1)
            ->first();
        $providedToken = $this->providedToken($request);

        if (! $liveStream || $providedToken === '' || ! hash_equals((string) $liveStream->publish_token, $providedToken)) {
            return response('Unauthorized', 401);
        }

        return response()->noContent();
    }

    private function providedToken(Request $request): string
    {
        $providedToken = $request->string('token')->toString();

        if ($providedToken !== '') {
            return $providedToken;
        }

        parse_str(ltrim($request->string('query')->toString(), '?'), $queryParameters);
        $queryToken = $queryParameters['token'] ?? null;

        return is_string($queryToken) ? $queryToken : '';
    }
}
