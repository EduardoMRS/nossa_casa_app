<?php

test('privacy and terms page is available in the requested locale', function () {
    $this->get('/privacy-and-terms')
        ->assertOk()
        ->assertSee('Privacy policy and terms of use')
        ->assertSee('Information we handle');

    $this->withHeader('X-Locale', 'pt')
        ->get('/privacy-and-terms')
        ->assertOk()
        ->assertSee('Política de privacidade e termos de uso')
        ->assertSee('Informações tratadas');
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
