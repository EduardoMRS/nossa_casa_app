<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\TerminologySeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('church leaders can configure organizational and role display terms', function () {
    $this->withoutVite();
    $church = Church::factory()->create(['domain' => 'terms.test']);
    $churchLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($churchLeader);

    $terminology = [
        'units' => [
            'headquarters' => 'temple',
            'branch' => 'congregation',
        ],
        'roles' => [
            'guest' => 'visitor',
            'member' => 'disciple',
            'leader' => 'coordinator',
            'media' => 'communications',
            'church_leader' => 'pastor',
            'superadmin' => 'bishop',
            'system' => 'system',
        ],
    ];

    $this->actingAs($churchLeader)
        ->put(route('admin.branding.update'), ['terminology' => $terminology])
        ->assertRedirect();

    $stored = Setting::query()->where('church_id', $church->id)->firstOrFail();

    expect($stored->options['terminology'])->toBe($terminology);

    $this->get('http://terms.test/dashboard/configuracoes-church')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Branding')
            ->where('terminology.units.headquarters', 'temple')
            ->where('terminology.roles.church_leader', 'pastor')
            ->where('terminologyOptions.units.headquarters.1.singular', __('terminology.units.temple.singular'))
            ->where('terminologyOptions.roles.church_leader.options.1.label', __('terminology.roles.pastor'))
            ->where('terminologyOptions.roles.church_leader.technical_label', __('terminology.technical_roles.church_leader')));

    $this->get('http://terms.test/dashboard/gestao-usuarios')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/UserManagement')
            ->where('terminology.roles.church_leader.label', __('terminology.roles.pastor'))
            ->where('roles.4.value', 'church_leader')
            ->where('roles.4.label', __('terminology.roles.pastor')));
});

test('church terminology rejects unknown keys', function () {
    $church = Church::factory()->create();
    $churchLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($churchLeader);

    $this->actingAs($churchLeader)
        ->from(route('admin.branding.edit'))
        ->put(route('admin.branding.update'), [
            'terminology' => [
                'units' => ['headquarters' => 'cathedral', 'branch' => 'branch'],
                'roles' => [
                    'guest' => 'visitor',
                    'member' => 'member',
                    'leader' => 'leader',
                    'media' => 'media',
                    'church_leader' => 'pastor',
                    'superadmin' => 'senior_leader',
                    'system' => 'system',
                ],
            ],
        ])
        ->assertRedirect(route('admin.branding.edit'))
        ->assertSessionHasErrors('terminology.units.headquarters');
});

test('terminology seeder backfills defaults and preserves valid custom choices', function () {
    $church = Church::factory()->create();
    $setting = $church->settings()->firstOrFail();
    $options = $setting->options;
    $options['custom_feature'] = ['enabled' => true];
    $options['terminology'] = [
        'units' => ['headquarters' => 'temple'],
        'roles' => ['church_leader' => 'pastor'],
    ];
    $setting->update(['options' => $options]);

    $this->seed(TerminologySeeder::class);

    $options = $setting->fresh()->options;

    expect($options['custom_feature']['enabled'])->toBeTrue()
        ->and($options['terminology']['units']['headquarters'])->toBe('temple')
        ->and($options['terminology']['units']['branch'])->toBe('branch')
        ->and($options['terminology']['roles']['church_leader'])->toBe('pastor')
        ->and($options['terminology']['roles']['member'])->toBe('member');
});

test('legacy admin role values migrate to church leader', function () {
    $user = User::factory()->create(['role' => UserRole::MEMBER]);
    DB::table('users')->where('id', $user->id)->update(['role' => 'admin']);

    $migration = require database_path('migrations/2026_08_20_011005_migrate_admin_role_to_church_leader.php');
    $migration->up();

    expect(DB::table('users')->where('id', $user->id)->value('role'))->toBe('church_leader');
});
