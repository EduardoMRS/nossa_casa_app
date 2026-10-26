<?php

use App\Enums\ContributionType;
use App\Enums\UserRole;
use App\Http\Controllers\TreasuryController;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\TreasurySetting;
use App\Models\User;
use App\Support\ChurchContext;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

function bindChurchContext(Church $church): void
{
    $context = app(ChurchDomainContext::class);

    foreach (['church' => $church, 'resolved' => true, 'source' => 'test'] as $property => $value) {
        $prop = new ReflectionProperty(ChurchContext::class, $property);
        $prop->setAccessible(true);
        $prop->setValue($context, $value);
    }
}

/**
 * @param  array<string, mixed>  $payload
 */
function treasuryRequest(?User $actor, array $payload = [], string $method = 'POST'): Request
{
    $request = Request::create('/give', $method, $payload);
    $request->setUserResolver(fn () => $actor);

    return $request;
}

test('a guest can register an anonymous contribution', function (): void {
    $church = Church::factory()->create();
    TreasurySetting::query()->create(['church_id' => $church->id, 'pix_key' => 'abc', 'accepts_contributions' => true]);
    bindChurchContext($church);

    (new TreasuryController)->contribute(treasuryRequest(null, [
        'type' => ContributionType::OFFERING->value,
        'amount' => '50.00',
        'contributor_name' => 'Visitante',
    ]));

    $contribution = Contribution::query()->firstOrFail();

    expect($contribution->user_id)->toBeNull()
        ->and($contribution->is_anonymous)->toBeTrue()
        ->and($contribution->church_id)->toBe($church->id)
        ->and((float) $contribution->amount)->toBe(50.0);
});

test('a logged-in member can register a contribution linked to their account', function (): void {
    $church = Church::factory()->create();
    TreasurySetting::query()->create(['church_id' => $church->id, 'accepts_contributions' => true]);
    bindChurchContext($church);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);

    (new TreasuryController)->contribute(treasuryRequest($member, [
        'type' => ContributionType::TITHE->value,
        'amount' => '120.50',
    ]));

    expect(Contribution::query()->firstOrFail()->user_id)->toBe($member->id);
});

test('contributions are rejected when the church is not accepting them', function (): void {
    $church = Church::factory()->create();
    TreasurySetting::query()->create(['church_id' => $church->id, 'accepts_contributions' => false]);
    bindChurchContext($church);

    expect(fn () => (new TreasuryController)->contribute(treasuryRequest(null, [
        'type' => ContributionType::TITHE->value,
        'amount' => '10',
    ])))->toThrow(HttpException::class);

    expect(Contribution::query()->count())->toBe(0);
});

test('a treasurer can update the treasury settings', function (): void {
    $church = Church::factory()->create();
    $treasurer = User::factory()->create(['role' => UserRole::TREASURER]);
    $church->assignMember($treasurer);
    bindChurchContext($church);

    (new TreasuryController)->updateSettings(treasuryRequest($treasurer->fresh(), [
        'pix_key_type' => 'email',
        'pix_key' => 'finance@church.test',
        'accepts_contributions' => true,
    ], 'PUT'));

    $settings = $church->treasurySetting()->firstOrFail();

    expect($settings->pix_key)->toBe('finance@church.test')
        ->and($settings->pix_key_type)->toBe('email');
});

test('a member cannot manage the treasury settings', function (): void {
    $church = Church::factory()->create();
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($member);
    bindChurchContext($church);

    expect(fn () => (new TreasuryController)->updateSettings(treasuryRequest($member->fresh(), [
        'pix_key' => 'hack',
    ], 'PUT')))->toThrow(HttpException::class);

    expect(TreasurySetting::query()->count())->toBe(0);
});

test('the pix key and bank account are stored encrypted', function (): void {
    $church = Church::factory()->create();
    TreasurySetting::query()->create([
        'church_id' => $church->id,
        'pix_key' => 'secret-key',
        'bank_account' => 'secret-account',
    ]);

    $raw = DB::table('treasury_settings')->where('church_id', $church->id)->first();

    expect($raw->pix_key)->not->toBe('secret-key')
        ->and($raw->bank_account)->not->toBe('secret-account')
        ->and(TreasurySetting::query()->firstOrFail()->pix_key)->toBe('secret-key');
});

test('the monthly report and csv export restrict to the selected month', function (): void {
    $church = Church::factory()->create();
    $treasurer = User::factory()->create(['role' => UserRole::TREASURER]);
    $church->assignMember($treasurer);
    bindChurchContext($church);

    $month = Carbon::create(2026, 5, 15);
    Contribution::factory()->for($church)->tithe()->create(['amount' => 100, 'contributed_at' => $month]);
    Contribution::factory()->for($church)->offering()->create(['amount' => 40, 'contributed_at' => $month]);
    Contribution::factory()->for($church)->tithe()->create(['amount' => 999, 'contributed_at' => $month->copy()->subMonth()]);

    $exportRequest = Request::create('/dashboard/treasury/export?month=2026-05', 'GET');
    $exportRequest->setUserResolver(fn () => $treasurer->fresh());
    $response = (new TreasuryController)->export($exportRequest);

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('100.00')
        ->and($csv)->toContain('40.00')
        ->and($csv)->not->toContain('999.00');
});
