<?php

namespace App\Console\Commands;

use App\Events\SystemMetricsUpdated;
use App\Support\SystemMetricsSnapshot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:broadcast-metrics')]
#[Description('Broadcast the current logs and metrics snapshot over WebSockets')]
class BroadcastSystemMetrics extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SystemMetricsSnapshot $snapshot): int
    {
        SystemMetricsUpdated::dispatch($snapshot->make());

        return self::SUCCESS;
    }
}
