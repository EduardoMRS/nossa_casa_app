<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Services\ChurchNetworkSettingsData;
use App\Support\ChurchDomainContext;
use Inertia\Inertia;
use Inertia\Response;

final class PublicChurchNetworkController extends Controller
{
    public function __construct(
        private readonly ChurchDomainContext $context,
        private readonly ChurchNetworkSettingsData $network,
    ) {}

    public function __invoke(): Response
    {
        $church = $this->context->church();
        abort_unless($church instanceof Church, 404);

        return Inertia::render('Church/Network', [
            'network' => $this->network->overviewForChurch($church),
        ]);
    }
}
