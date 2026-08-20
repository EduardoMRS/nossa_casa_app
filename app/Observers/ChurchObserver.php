<?php

namespace App\Observers;

use App\Actions\Churches\ProvisionChurchStarterKit;
use App\Models\Church;

final class ChurchObserver
{
    public function __construct(private ProvisionChurchStarterKit $provisionChurchStarterKit) {}

    public function created(Church $church): void
    {
        $this->provisionChurchStarterKit->handle($church);
    }
}
