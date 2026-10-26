<?php

use App\Http\Controllers\Settings\BrandingController;

function invokeBrandingMethod(string $method, array $args): mixed
{
    $controller = app(BrandingController::class);
    $reflection = new ReflectionMethod(BrandingController::class, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($controller, $args);
}

test('an OpenStreetMap embed is built from coordinates', function (): void {
    $embed = invokeBrandingMethod('buildOpenStreetMapEmbed', [-23.55, -46.63]);

    expect($embed)->toContain('openstreetmap.org/export/embed.html')
        ->and($embed)->toContain('marker=')
        ->and($embed)->toContain('-23.55')
        ->and($embed)->toContain('-46.63');
});

test('no embed is built without valid coordinates', function (): void {
    expect(invokeBrandingMethod('buildOpenStreetMapEmbed', [null, null]))->toBe('')
        ->and(invokeBrandingMethod('buildOpenStreetMapEmbed', ['abc', 'def']))->toBe('');
});

test('OpenStreetMap embeds are detected and other providers are not', function (): void {
    expect(invokeBrandingMethod('isOpenStreetMapEmbed', ['https://www.openstreetmap.org/export/embed.html?bbox=1,2,3,4']))->toBeTrue()
        ->and(invokeBrandingMethod('isOpenStreetMapEmbed', ['https://www.google.com/maps/embed?pb=xyz']))->toBeFalse();
});
