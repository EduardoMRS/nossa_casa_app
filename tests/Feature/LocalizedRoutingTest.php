<?php

test('a missing URL locale redirects to the closest browser language', function () {
    $this->withHeader('Accept-Language', 'pt-BR, en;q=0.8')
        ->get('/posts')
        ->assertRedirect('/pt/posts');
});

test('a missing URL locale preserves the complete public path', function () {
    $this->withHeader('Accept-Language', 'en-US, pt;q=0.8')
        ->get('/posts/summer-camp')
        ->assertRedirect('/en/posts/summer-camp');
});

test('an unsupported browser language falls back to the application locale', function () {
    config()->set('app.locale', 'en');

    $this->withHeader('Accept-Language', 'fr-FR, de;q=0.8')
        ->get('/posts')
        ->assertRedirect('/en/posts');
});
