<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\Network;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.url' => 'http://platform.test']);
    Storage::fake('media');
    $this->withoutVite();
});

function brandingLogoPng(string $suffix = ''): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=').$suffix;
}

function storeChurchBrandingLogo(Church $church, string $path, string $contents): void
{
    Storage::disk('media')->put($path, $contents);
    $setting = $church->settings()->firstOrFail();
    $options = $setting->options;
    $options['branding']['logo_path'] = $path;
    $setting->update(['options' => $options]);
}

test('church branding inherits the nearest available logo through every headquarters level', function () {
    $community = Community::factory()->create();
    $seniorHeadquarters = Church::factory()->for($community)->create(['domain' => 'senior-logo.test']);
    $headquarters = Church::factory()->for($community)->create(['domain' => 'headquarters-logo.test']);
    $branch = Church::factory()->for($community)->create(['domain' => 'branch-logo.test']);
    Network::query()->create([
        'community_id' => $community->id,
        'parent_church_id' => $seniorHeadquarters->id,
        'child_church_id' => $headquarters->id,
    ]);
    Network::query()->create([
        'community_id' => $community->id,
        'parent_church_id' => $headquarters->id,
        'child_church_id' => $branch->id,
    ]);
    $seniorLogo = brandingLogoPng('senior');
    storeChurchBrandingLogo($seniorHeadquarters, 'church/senior/branding/logo.png', $seniorLogo);
    $seniorIcon = brandingLogoPng('senior-icon');
    Storage::disk('media')->put('church/senior/branding/icon.png', $seniorIcon);
    $seniorOptions = $seniorHeadquarters->settings()->firstOrFail()->options;
    $seniorOptions['branding']['icon_path'] = 'church/senior/branding/icon.png';
    $seniorHeadquarters->settings()->firstOrFail()->update(['options' => $seniorOptions]);

    $churchLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $branch->assignMember($churchLeader);

    $this->actingAs($churchLeader)
        ->get('http://branch-logo.test/dashboard/configuracoes-church')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('branding.logo_inherited', true)
            ->where('branding.logo_source_church_name', $seniorHeadquarters->name));

    $this->get('http://branch-logo.test/')
        ->assertSuccessful()
        ->assertSee('href="/branding/icon.svg"', escape: false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('branding.logo_path', 'church/senior/branding/logo.png')
            ->where('branding.logo_url', '/branding/logo')
            ->where('branding.icon_url', '/branding/icon.svg')
            ->where('branding.logo_source_church_id', $seniorHeadquarters->id)
            ->where('branding.logo_inherited', true)
            ->where('branding.logo_fallback', false));

    $this->get('http://branch-logo.test/branding/logo')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/png')
        ->assertContent($seniorLogo);

    $this->get('http://branch-logo.test/branding/icon.svg')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
        ->assertSee(base64_encode($seniorIcon), escape: false);

    $this->get('http://branch-logo.test/manifest.webmanifest')
        ->assertSuccessful()
        ->assertJsonPath('icons.0.src', '/branding/icon.svg')
        ->assertJsonPath('icons.0.sizes', 'any')
        ->assertJsonPath('icons.1.src', '/branding/logo');

    $headquartersLogo = brandingLogoPng('headquarters');
    storeChurchBrandingLogo($headquarters, 'church/headquarters/branding/logo.png', $headquartersLogo);

    $this->get('http://branch-logo.test/branding/logo')
        ->assertSuccessful()
        ->assertContent($headquartersLogo);

    $branchLogo = brandingLogoPng('branch');
    storeChurchBrandingLogo($branch, 'church/branch/branding/logo.png', $branchLogo);

    $this->get('http://branch-logo.test/branding/logo')
        ->assertSuccessful()
        ->assertContent($branchLogo);
});

test('branding falls back to the Nossa Casa mark and safely handles network cycles', function () {
    $community = Community::factory()->create();
    $firstChurch = Church::factory()->for($community)->create(['domain' => 'cycle-logo.test']);
    $secondChurch = Church::factory()->for($community)->create();
    Network::query()->create([
        'community_id' => $community->id,
        'parent_church_id' => $firstChurch->id,
        'child_church_id' => $secondChurch->id,
    ]);
    Network::query()->create([
        'community_id' => $community->id,
        'parent_church_id' => $secondChurch->id,
        'child_church_id' => $firstChurch->id,
    ]);
    $fallbackLogo = file_get_contents(resource_path('pwa/nossa-casa-mark.png'));

    expect($fallbackLogo)->toBeString()->not->toBeEmpty();

    $this->get('http://cycle-logo.test/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('branding.logo_source_church_id', null)
            ->where('branding.logo_inherited', false)
            ->where('branding.logo_fallback', true));

    $this->get('http://cycle-logo.test/branding/logo')
        ->assertSuccessful()
        ->assertContent($fallbackLogo);

    $this->get('http://cycle-logo.test/favicon.ico')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');

    $this->get('http://cycle-logo.test/sw.js')
        ->assertSuccessful()
        ->assertSee('/branding/icon.svg', escape: false)
        ->assertSee('/branding/logo', escape: false);
});
