<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Media\StopLiveStream;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use Illuminate\Http\RedirectResponse;

class StopLiveStreamController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(LiveStream $liveStream, StopLiveStream $stopLiveStream): RedirectResponse
    {
        $stopLiveStream->handle($liveStream);

        return back();
    }
}
