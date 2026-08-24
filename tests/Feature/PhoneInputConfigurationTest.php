<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Setting;
use App\Models\User;

test('church leader can configure the church currency', function () {
    $this->withoutVite();
    $church = Church::factory()->create();
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($leader);

    $this->actingAs($leader)->put('/dashboard/configuracoes-church', [
        'currency' => 'USD',
        'brand_name' => $church->name,
    ])->assertSessionHasNoErrors();

    expect(Setting::query()->where('church_id', $church->id)->sole()->options['currency'])->toBe('USD');
});
