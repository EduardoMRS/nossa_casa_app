<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\ChurchMailSetting;
use App\Models\Network;
use App\Models\User;
use App\Support\ChurchMailManager;
use Illuminate\Support\Facades\DB;

test('church mail credentials are encrypted and can be inherited by branches', function () {
    $church = Church::factory()->create();
    $branch = Church::factory()->create();
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($leader);
    Network::query()->create(['parent_church_id' => $church->id, 'child_church_id' => $branch->id]);

    $this->actingAs($leader)->put('/dashboard/configuracoes-church', [
        'domain' => $church->slug.'.localhost',
        'mail' => [
            'enabled' => true,
            'allow_branches' => true,
            'host' => 'smtp.example.test',
            'port' => 587,
            'scheme' => 'tls',
            'username' => 'church@example.test',
            'password' => 'super-secret-password',
            'from_address' => 'church@example.test',
            'from_name' => 'Church Mail',
        ],
    ])->assertSessionHasNoErrors();

    $raw = DB::table('church_mail_settings')->where('church_id', $church->id)->first();
    app(ChurchMailManager::class)->configureFor($church);
    expect($raw->password)->not->toContain('super-secret-password')
        ->and(ChurchMailSetting::query()->where('church_id', $church->id)->sole()->password)->toBe('super-secret-password')
        ->and(app(ChurchMailManager::class)->resolveFor($branch)?->church_id)->toBe($church->id)
        ->and(config('mail.default'))->toBe('church')
        ->and(config('mail.mailers.church.host'))->toBe('smtp.example.test');
});

test('branch own server takes precedence over inherited server', function () {
    $parent = Church::factory()->create();
    $branch = Church::factory()->create();
    Network::query()->create(['parent_church_id' => $parent->id, 'child_church_id' => $branch->id]);
    ChurchMailSetting::query()->create(['church_id' => $parent->id, 'enabled' => true, 'allow_branches' => true, 'host' => 'parent.test', 'port' => '587', 'from_address' => 'parent@example.test']);
    ChurchMailSetting::query()->create(['church_id' => $branch->id, 'enabled' => true, 'host' => 'branch.test', 'port' => '587', 'from_address' => 'branch@example.test']);

    expect(app(ChurchMailManager::class)->resolveFor($branch)?->host)->toBe('branch.test');
});

test('application mail uses the authenticated smtp account when no church server exists', function () {
    config()->set('mail.application_from', [
        'address' => 'application@example.test',
        'name' => 'Application Mail',
    ]);
    config()->set('mail.from', [
        'address' => 'church@example.test',
        'name' => 'Church Mail',
    ]);
    config()->set('mail.default', 'church');

    app(ChurchMailManager::class)->reset();

    expect(config('mail.default'))->toBe(config('mail.application_default'))
        ->and(config('mail.from.address'))->toBe('application@example.test')
        ->and(config('mail.from.name'))->toBe('Application Mail');
});
