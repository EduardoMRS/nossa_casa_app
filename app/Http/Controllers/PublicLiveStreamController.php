<?php

namespace App\Http\Controllers;

use App\Models\LiveStream;
use App\Queries\LiveStreamQuery;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PublicLiveStreamController extends Controller
{
    public function __construct(
        private readonly ChurchDomainContext $context,
        private readonly LiveStreamQuery $liveStreams,
    ) {}

    public function show(Request $request, LiveStream $liveStream): Response
    {
        $churchId = $this->context->churchId();
        abort_unless($churchId, 404);

        return Inertia::render('LiveStreams/Show', $this->liveStreams->show(
            $liveStream,
            $churchId,
            $request->user(),
        )->toArray());
    }
}
