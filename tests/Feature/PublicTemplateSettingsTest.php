<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\Setting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.url' => 'http://platform.test']);
    $this->withoutVite();
});

test('church administrators can select each public page template', function () {
    $community = Community::factory()->create();
    $church = Church::factory()->create([
        'domain' => 'templates.test',
        'community_id' => $community->id,
    ]);
    $admin = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($admin);
    $templates = [
        'home' => 'editorial',
        'posts_index' => 'minimal',
        'posts_show' => 'editorial',
        'events_index' => 'minimal',
        'events_show' => 'editorial',
        'form' => 'minimal',
        'library' => 'editorial',
        'gallery' => 'minimal',
    ];

    $this->actingAs($admin)->put('http://templates.test/dashboard/configuracoes-church', [
        'domain' => 'templates.test',
        'templates' => $templates,
    ])->assertRedirect();

    expect(Setting::query()->where('church_id', $church->id)->firstOrFail()->options['templates'])
        ->toBe($templates);

    $this->get('http://templates.test/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('publicTemplates.home', 'editorial')
            ->where('publicTemplates.gallery', 'minimal'));
});

test('unsupported public template variants are rejected', function () {
    $community = Community::factory()->create();
    $church = Church::factory()->create([
        'domain' => 'invalid-template.test',
        'community_id' => $community->id,
    ]);
    $admin = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($admin);

    $this->actingAs($admin)->put('http://invalid-template.test/dashboard/configuracoes-church', [
        'domain' => 'invalid-template.test',
        'templates' => ['home' => 'unknown'],
    ])->assertSessionHasErrors('templates.home');
});
