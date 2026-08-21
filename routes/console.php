<?php

use App\Console\Commands\BroadcastSystemMetrics;
use App\Console\Commands\ReconcileMediaMtxPaths;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ReconcileMediaMtxPaths::class)
    ->everyMinute()
    ->withoutOverlapping(1)
    ->onOneServer();

Schedule::command(BroadcastSystemMetrics::class)
    ->everyFiveSeconds()
    ->withoutOverlapping(1)
    ->onOneServer();
