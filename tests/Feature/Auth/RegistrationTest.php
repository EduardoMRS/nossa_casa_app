<?php

use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register with optional profile details and a simple eight character password', function () {
    Mail::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'birth_date' => '1990-05-20',
        'phone' => '+55 (69) 99999-9999',
        'gender' => 'other',
        'location_lang' => 'pt-BR',
        'terms_accepted' => '1',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));

    $user = auth()->user()?->load('profile');

    expect($user)->not->toBeNull()
        ->and($user->birth_date?->format('Y-m-d'))->toBe('1990-05-20')
        ->and($user->profile?->phone)->toBe('+55 (69) 99999-9999')
        ->and($user->profile?->gender)->toBe('other')
        ->and($user->profile?->location_lang)->toBe('pt-BR');

    Mail::assertQueued(
        WelcomeMail::class,
        fn (WelcomeMail $mail): bool => $mail->hasTo($user->email)
            && $mail->locale === 'pt',
    );
});

test('registration rejects passwords shorter than eight characters', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'terms_accepted' => '1',
        'password' => '1234567',
        'password_confirmation' => '1234567',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('password');
});

test('registration requires acceptance of the privacy policy and terms', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('terms_accepted');
});
