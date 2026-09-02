<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('a user without church can open every configured Bible version', function () {
    Cache::forget('bible-api.versions.v2');
    Http::fake([
        '*/bibles.json' => Http::response([]),
    ]);

    $this->get('/library/bible')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Bible')
            ->has('versions', 4)
            ->where('defaultVersion', 'en-kjv'));
});
