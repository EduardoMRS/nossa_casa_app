<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Event;
use App\Models\PrayerRequest;
use App\Models\Setting;
use App\Models\User;
use App\Support\ChurchDomainContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('media');
});

test('church branding stores location embed and grouped weekly schedules for the public home', function () {
    $this->withoutVite();
    $church = Church::query()->create([
        'name' => 'Weekly Church',
        'slug' => 'weekly-church',
        'domain' => 'weekly.test',
        'status' => 'active',
    ]);
    $admin = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($admin);
    $event = Event::query()->create([
        'church_id' => $church->id,
        'author_id' => $admin->id,
        'title' => 'Community conference',
        'slug' => 'community-conference',
        'start_time' => now()->addDays(5),
        'end_time' => now()->addDays(5)->addHours(2),
    ]);
    $logo = UploadedFile::fake()->createWithContent(
        'weekly-logo.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII='),
    );

    $this->actingAs($admin)
        ->put('http://weekly.test/dashboard/configuracoes-church', [
            'domain' => 'https://WEEKLY-NEW.test/welcome',
            'brand_name' => 'Weekly Church',
            'logo' => $logo,
            'primary_color' => '#342f87',
            'secondary_color' => '#5f7d95',
            'accent_color' => '#c88b4a',
            'surface_color' => '#f4f7fb',
            'font_family' => 'Nunito Sans, sans-serif',
            'address' => '100 Main Street, Manaus',
            'map_embed' => '<iframe src="https://www.google.com/maps/embed?pb=church"></iframe>',
            'weekly_schedule' => [
                [
                    'title' => 'Worship service',
                    'day_of_week' => 0,
                    'start_time' => '09:00',
                    'end_time' => '10:30',
                ],
                [
                    'title' => 'Worship service',
                    'day_of_week' => 0,
                    'start_time' => '18:00',
                    'end_time' => '19:30',
                ],
            ],
        ])
        ->assertRedirect();

    $branding = Setting::query()->where('church_id', $church->id)->value('options')['branding'];

    expect($branding['address'])->toBe('100 Main Street, Manaus')
        ->and($church->refresh()->domain)->toBe('weekly-new.test')
        ->and($branding['logo_path'])->toStartWith('church/'.$church->id.'/branding/')
        ->and($branding['map_embed'])->toBe('https://www.google.com/maps/embed?pb=church')
        ->and($branding['weekly_schedule'])->toHaveCount(2)
        ->and($branding['weekly_schedule'][0]['title'])->toBe('Worship service');
    expect(Storage::disk('media')->exists($branding['logo_path']))->toBeTrue();

    $this->get('http://weekly-new.test/')
        ->assertSuccessful()
        ->assertSee('--church-primary: #342f87', false)
        ->assertSee('--church-surface: #f4f7fb', false)
        ->assertSee('--church-font: Nunito Sans, sans-serif', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('branding.logo_path', $branding['logo_path'])
            ->where('branding.logo_url', '/branding/logo')
            ->where('branding.address', '100 Main Street, Manaus')
            ->where('branding.map_embed', 'https://www.google.com/maps/embed?pb=church')
            ->has('branding.weekly_schedule', 2)
            ->where('calendarEvents.0.id', $event->id));
});

test('branding rejects unsafe map embeds', function () {
    $church = Church::query()->create([
        'name' => 'Safe Church',
        'slug' => 'safe-church',
        'domain' => 'safe-church.test',
        'status' => 'active',
    ]);
    $admin = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($admin);

    $this->actingAs($admin)
        ->from('http://safe-church.test/dashboard/configuracoes-church')
        ->put('http://safe-church.test/dashboard/configuracoes-church', [
            'map_embed' => '<iframe src="https://unsafe.example/map"></iframe>',
        ])
        ->assertRedirect('http://safe-church.test/dashboard/configuracoes-church')
        ->assertSessionHasErrors('map_embed');
});

test('church settings keep the current domain when the domain input is omitted', function () {
    $church = Church::query()->create([
        'name' => 'Current Domain Church',
        'slug' => 'current-domain-church',
        'domain' => 'current-domain.test',
        'status' => 'active',
    ]);
    $admin = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($admin);

    $this->actingAs($admin)
        ->put('http://current-domain.test/dashboard/configuracoes-church', [
            'brand_name' => 'Updated Church Name',
        ])
        ->assertRedirect();

    expect($church->refresh()->domain)->toBe('current-domain.test');
});

test('church settings derive a church domain when none has been configured', function () {
    $church = Church::query()->create([
        'name' => 'Generated Domain Church',
        'slug' => 'generated-domain-church',
        'status' => 'active',
    ]);
    $admin = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($admin);

    $this->actingAs($admin)
        ->put(route('admin.branding.update'), [
            'brand_name' => 'Generated Domain Church',
        ])
        ->assertRedirect();

    expect($church->refresh()->domain)->toBe(
        'generated-domain-church.'.app(ChurchDomainContext::class)->mainHost(),
    );
});

test('public church pages expose dashboard access only to roles above member', function () {
    $this->withoutVite();
    $church = Church::query()->create([
        'name' => 'Access Church',
        'slug' => 'access-church',
        'domain' => 'access-church.test',
        'status' => 'active',
    ]);
    $leader = User::factory()->create(['role' => UserRole::LEADER]);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($leader);
    $church->assignMember($member);

    $this->actingAs($leader)
        ->get('http://access-church.test/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.accessDashboard', true));

    $this->actingAs($member)
        ->get('http://access-church.test/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.accessDashboard', false));
});

test('guests can submit a prayer request to the church selected by domain', function () {
    $church = Church::query()->create([
        'name' => 'Prayer Church',
        'slug' => 'prayer-church',
        'domain' => 'prayer.test',
        'status' => 'active',
    ]);

    $this->postJson('http://prayer.test/api/prayer-requests', [
        'content' => 'Please pray for my family.',
    ])->assertCreated();

    $prayerRequest = PrayerRequest::query()
        ->where('content', 'Please pray for my family.')
        ->firstOrFail();

    expect($prayerRequest->church_id)->toBe($church->id)
        ->and($prayerRequest->user_id)->toBeNull();
});
