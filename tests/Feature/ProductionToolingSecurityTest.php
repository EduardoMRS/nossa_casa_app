<?php

use Illuminate\Support\Facades\Route;

test('development tooling is not exposed outside the local environment', function () {
    expect(config('boost.enabled'))->toBeFalse()
        ->and(config('boost.browser_logs_watcher'))->toBeFalse()
        ->and(Route::has('boost.browser-logs'))->toBeFalse();

    $this->postJson('/_boost/browser-logs', ['logs' => []])->assertNotFound();
});

test('MCP packages remain development-only and no web server is registered', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $aiRoutes = file_get_contents(base_path('routes/ai.php'));

    expect($composer['require'])->not->toHaveKey('laravel/mcp')
        ->and($composer['require-dev'])->toHaveKeys(['laravel/boost', 'laravel/mcp'])
        ->and($aiRoutes)->toBe("<?php\n");
});
