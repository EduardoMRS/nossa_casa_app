<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\Network;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.url' => 'http://platform.test']);
    $this->withoutVite();
});

test('church leaders can update portal settings including header menu, layout, sections, footer, and custom css', function () {
    $community = Community::factory()->create();
    $church = Church::factory()->for($community)->create([
        'domain' => 'portal-settings.test',
    ]);

    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($leader);

    $portalPayload = [
        'brand_name' => 'Church With Portal',
        'domain' => 'portal-settings.test',
        'default_locale' => 'pt-BR',
        'currency' => 'BRL',
        'portal' => [
            'header_menu' => [
                ['key' => 'home', 'enabled' => '1'],
                ['key' => 'events', 'enabled' => '1'],
                ['key' => 'posts', 'enabled' => '0'],
                ['key' => 'give', 'enabled' => '1'],
            ],
            'home_layout' => 'single_column',
            'home_sections' => [
                'banner' => '1',
                'next_event' => '1',
                'daily_verse' => '0',
                'featured_events' => '1',
                'latest_posts' => '0',
                'latest_media' => '1',
                'calendar' => '1',
                'prayer_contact' => '1',
                'location_map' => '1',
            ],
            'footer' => [
                'message' => 'Nossa igreja no coração da cidade.',
                'links' => [
                    ['label' => 'Instagram Oficial', 'url' => 'https://instagram.com/church'],
                ],
            ],
            'custom_css' => '<script>alert(1)</script>body { font-size: 16px; }',
            'propagate_to_branches' => '1',
        ],
    ];

    $response = $this->actingAs($leader)
        ->from('http://portal-settings.test/dashboard/configuracoes-church')
        ->put('http://portal-settings.test/dashboard/configuracoes-church', $portalPayload);

    $response->assertRedirect('http://portal-settings.test/dashboard/configuracoes-church');

    $setting = $church->settings()->firstOrFail();
    $portal = $setting->options['portal'] ?? [];

    expect($portal['home_layout'])->toBe('single_column')
        ->and($portal['propagate_to_branches'])->toBeTrue()
        ->and($portal['home_sections']['daily_verse'])->toBeFalse()
        ->and($portal['home_sections']['banner'])->toBeTrue()
        ->and($portal['footer']['message'])->toBe('Nossa igreja no coração da cidade.')
        ->and($portal['footer']['links'])->toHaveCount(1)
        ->and($portal['footer']['links'][0]['label'])->toBe('Instagram Oficial')
        ->and($portal['custom_css'])->not->toContain('<script>')
        ->and($portal['custom_css'])->toContain('body { font-size: 16px; }');
});

test('child church inherits portal settings when headquarters has propagate_to_branches enabled', function () {
    $community = Community::factory()->create();
    $parentChurch = Church::factory()->for($community)->create([
        'name' => 'Matriz Central',
        'domain' => 'matriz-portal.test',
    ]);
    $childChurch = Church::factory()->for($community)->create([
        'name' => 'Filial Norte',
        'domain' => 'filial-portal.test',
    ]);

    Network::query()->create([
        'community_id' => $community->id,
        'parent_church_id' => $parentChurch->id,
        'child_church_id' => $childChurch->id,
    ]);

    $parentSetting = $parentChurch->settings()->firstOrFail();
    $options = $parentSetting->options;
    $options['portal'] = [
        'home_layout' => 'single_column',
        'propagate_to_branches' => true,
        'footer' => [
            'message' => 'Mensagem compartilhada da matriz.',
            'links' => [],
        ],
        'custom_css' => '.matriz-theme { color: red; }',
    ];
    $parentSetting->update(['options' => $options]);

    $childLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $childChurch->assignMember($childLeader);

    $this->actingAs($childLeader)
        ->get('http://filial-portal.test/dashboard/configuracoes-church')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Branding')
            ->has('portal', fn (Assert $portal) => $portal
                ->where('home_layout', 'single_column')
                ->where('is_inherited', true)
                ->where('source_church_name', 'Matriz Central')
                ->where('footer.message', 'Mensagem compartilhada da matriz.')
                ->where('custom_css', '.matriz-theme { color: red; }')
                ->etc()
            )
        );

    // Also check on the public home page of the child church
    $this->get('http://filial-portal.test/pt')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('portal', fn (Assert $portal) => $portal
                ->where('home_layout', 'single_column')
                ->where('footer.message', 'Mensagem compartilhada da matriz.')
                ->etc()
            )
        );
});

test('child church can override inherited portal settings with its own', function () {
    $community = Community::factory()->create();
    $parentChurch = Church::factory()->for($community)->create([
        'domain' => 'parent-override.test',
    ]);
    $childChurch = Church::factory()->for($community)->create([
        'domain' => 'child-override.test',
    ]);

    Network::query()->create([
        'community_id' => $community->id,
        'parent_church_id' => $parentChurch->id,
        'child_church_id' => $childChurch->id,
    ]);

    // Parent propagates single_column
    $parentSetting = $parentChurch->settings()->firstOrFail();
    $options = $parentSetting->options;
    $options['portal'] = [
        'home_layout' => 'single_column',
        'propagate_to_branches' => true,
    ];
    $parentSetting->update(['options' => $options]);

    // Child sets two_columns
    $childSetting = $childChurch->settings()->firstOrFail();
    $childOptions = $childSetting->options;
    $childOptions['portal'] = [
        'home_layout' => 'two_columns',
        'propagate_to_branches' => false,
    ];
    $childSetting->update(['options' => $childOptions]);

    $this->get('http://child-override.test/pt')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('portal.home_layout', 'two_columns')
        );
});
