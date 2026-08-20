<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();

    Route::middleware('web')->get('/test-error/{status}', function (int $status): never {
        abort($status);
    });
});

test('users can only open the dashboard on their own church domain', function () {
    $ownChurch = Church::factory()->create(['domain' => 'own-dashboard.test']);
    $foreignChurch = Church::factory()->create(['domain' => 'foreign-dashboard.test']);
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $ownChurch->assignMember($leader);

    $foreignSetting = $foreignChurch->settings()->firstOrFail();
    $foreignOptions = $foreignSetting->options;
    $foreignOptions['branding']['primary_color'] = '#1458a6';
    $foreignSetting->update(['options' => $foreignOptions]);

    $this->actingAs($leader)
        ->get('http://own-dashboard.test/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('permissions.accessDashboard', true));

    $this->get('http://foreign-dashboard.test/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.accessDashboard', false));

    $this->get('http://foreign-dashboard.test/dashboard')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ErrorPage')
            ->where('status', 403)
            ->where('branding.primary_color', '#1458a6'));

    $this->get('/dashboard')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ErrorPage')
            ->where('status', 403));
});

test('common and fallback http errors use the inertia error page', function (int $status) {
    $this->get("/test-error/{$status}")
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page
            ->component('ErrorPage')
            ->where('status', $status)
            ->where('portalUrl', rtrim((string) config('app.url'), '/')));
})->with([401, 403, 404, 500, 503, 505, 418]);

test('not found pages inherit branding from a resolved church domain', function () {
    $church = Church::factory()->create(['domain' => 'branded-errors.test']);
    $setting = Setting::query()->where('church_id', $church->id)->firstOrFail();
    $options = $setting->options;
    $options['branding']['brand_name'] = 'Branded Errors';
    $options['branding']['primary_color'] = '#663399';
    $setting->update(['options' => $options]);

    $this->get('http://branded-errors.test/missing-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ErrorPage')
            ->where('status', 404)
            ->where('branding.brand_name', 'Branded Errors')
            ->where('branding.primary_color', '#663399'));
});
