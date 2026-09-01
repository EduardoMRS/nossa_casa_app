<?php

use App\Mail\WelcomeMail;
use App\Models\Church;
use App\Models\User;
use App\Notifications\BrandedResetPasswordNotification;
use App\Support\MailBrandingResolver;
use Illuminate\Support\Facades\App;

beforeEach(function () {
    config([
        'app.name' => 'Nossa Casa App',
        'app.url' => 'https://nossacasa.test',
    ]);
});

test('application welcome email uses the Nossa Casa identity and recipient locale', function () {
    $user = User::factory()->create(['first_name' => 'Eduardo']);

    (new WelcomeMail($user))
        ->locale('pt')
        ->assertSeeInHtml('Nossa Casa App')
        ->assertSeeInHtml('Boas-vindas, Eduardo!')
        ->assertSeeInHtml('https://nossacasa.test/branding/logo', escape: false)
        ->assertSeeInText('Tecnologia a serviço de comunidades de fé.');
});

test('church welcome email uses its own name colors logo and domain', function () {
    $church = Church::factory()->create(['domain' => 'esperanca.test']);
    $setting = $church->settings()->firstOrCreate();
    $options = is_array($setting->options) ? $setting->options : [];
    $options['branding'] = [
        'brand_name' => 'Igreja Esperança',
        'tagline' => 'Uma família para você',
        'primary_color' => '#123456',
        'secondary_color' => '#654321',
        'accent_color' => '#d4a017',
    ];
    $setting->update(['options' => $options]);
    $user = User::factory()->create(['first_name' => 'Marcos']);

    $branding = app(MailBrandingResolver::class)->resolve($church);

    expect($branding['name'])->toBe('Igreja Esperança')
        ->and($branding['primary_color'])->toBe('#123456')
        ->and($branding['secondary_color'])->toBe('#654321')
        ->and($branding['logo_url'])->toBe('https://esperanca.test/branding/logo')
        ->and($branding['portal_url'])->toBe('https://esperanca.test')
        ->and($branding['is_church'])->toBeTrue();

    (new WelcomeMail($user, $church))
        ->locale('pt')
        ->assertSeeInHtml('Igreja Esperança')
        ->assertSeeInHtml('Uma família para você')
        ->assertSeeInHtml('#123456', escape: false)
        ->assertSeeInHtml('https://esperanca.test/branding/logo', escape: false);
});

test('password reset notification uses the branded responsive view', function () {
    App::setLocale('pt');
    $user = User::factory()->create();
    $user->profile()->create(['location_lang' => 'pt-BR']);
    $message = (new BrandedResetPasswordNotification('reset-token'))->toMail($user);
    $html = $message->render();

    expect($html)
        ->toContain('Redefina sua senha')
        ->toContain('Nossa Casa App')
        ->toContain('reset-password/reset-token');
});
