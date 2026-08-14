<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('media');
    config()->set('media.disk', 'media');
});

test('legacy public uploads can be moved to private storage', function () {
    Storage::disk('public')->put('church/example/logo.png', 'private-logo');

    $this->artisan('media:storage:migrate')
        ->expectsOutputToContain('Moved 1 file(s)')
        ->assertSuccessful();

    Storage::disk('public')->assertMissing('church/example/logo.png');
    Storage::disk('media')->assertExists('church/example/logo.png');
});

test('private files are only served by encrypted temporary signed urls', function () {
    Storage::disk('media')->put('church/example/private.txt', 'private-content');

    $url = genUrl('church/example/private.txt');

    expect($url)->toContain('signature=')->toContain('expires=');

    $response = $this->get($url)->assertSuccessful();

    expect($response->baseResponse->getFile()->getContent())->toBe('private-content');

    $this->get($url.'&tampered=1')->assertForbidden();
});
