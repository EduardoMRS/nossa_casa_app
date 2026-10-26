<?php

use App\Enums\ContributionType;
use App\Enums\UserRole;
use App\Http\Controllers\GroupPortalController;
use App\Http\Controllers\TreasuryController;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Group;
use App\Models\Post;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Services\DashboardInsights;
use App\Support\ChurchContext;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->bindChurch = function (?Church $church): void {
        $context = app(ChurchDomainContext::class);

        foreach (['church' => $church, 'resolved' => true, 'source' => 'test'] as $property => $value) {
            $reflection = new ReflectionProperty(ChurchContext::class, $property);
            $reflection->setValue($context, $value);
        }
    };
});

test('dashboard insights never leak other churches data when no church is resolved', function (): void {
    $otherChurch = Church::factory()->create();
    $author = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $otherChurch->assignMember($author);
    Post::query()->create([
        'church_id' => $otherChurch->id, 'author_id' => $author->id,
        'title' => 'Segredo de outra igreja', 'slug' => 'segredo', 'content' => 'x',
    ]);
    PrayerRequest::query()->create(['church_id' => $otherChurch->id, 'content' => 'Pedido privado']);
    Contribution::factory()->for($otherChurch)->create(['amount' => 999, 'contributed_at' => now()]);

    $leaderWithoutChurch = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $insights = app(DashboardInsights::class)->forChurch(null, $leaderWithoutChurch);

    expect($insights['recent_activity'])->toBe([])
        ->and($insights['recent_prayers'])->toBe([])
        ->and($insights['most_liked'])->toBe([])
        ->and($insights['access_map'])->toBe([])
        ->and($insights['treasury'])->toBeNull();
});

test('ministry leaders do not see prayer requests on the dashboard', function (): void {
    $church = Church::factory()->create();
    PrayerRequest::query()->create(['church_id' => $church->id, 'content' => 'Pedido']);
    $leader = User::factory()->create(['role' => UserRole::LEADER]);
    $church->assignMember($leader);

    expect(app(DashboardInsights::class)->forChurch($church, $leader->fresh())['recent_prayers'])->toBe([]);
});

test('the treasury csv export neutralizes spreadsheet formula injection', function (): void {
    $church = Church::factory()->create();
    $treasurer = User::factory()->create(['role' => UserRole::TREASURER]);
    $church->assignMember($treasurer);
    ($this->bindChurch)($church);

    Contribution::factory()->for($church)->create([
        'type' => ContributionType::OFFERING->value,
        'amount' => 10,
        'contributor_name' => '=HYPERLINK("http://evil.test","x")',
        'message' => '+cmd',
        'contributed_at' => now(),
    ]);

    $request = Request::create('/dashboard/treasury/export', 'GET');
    $request->setUserResolver(fn () => $treasurer->fresh());

    ob_start();
    (new TreasuryController)->export($request)->sendContent();
    $csv = (string) ob_get_clean();

    expect($csv)->toContain("'=HYPERLINK")
        ->and($csv)->toContain("'+cmd")
        ->and($csv)->not->toMatch('/(^|,)"?=HYPERLINK/m');
});

test('a church leader cannot manage the treasury of another church', function (): void {
    $church = Church::factory()->create();
    $otherChurch = Church::factory()->create();
    $foreignLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $otherChurch->assignMember($foreignLeader);
    ($this->bindChurch)($church);

    $request = Request::create('/dashboard/treasury', 'PUT', ['pix_key' => 'hijack']);
    $request->setUserResolver(fn () => $foreignLeader->fresh());

    // The treasury ignores the request context and always uses the user's own church.
    (new TreasuryController)->updateSettings($request);

    expect($church->treasurySetting()->exists())->toBeFalse()
        ->and($otherChurch->treasurySetting()->firstOrFail()->pix_key)->toBe('hijack');
});

test('system users have no access to billing', function (): void {
    $church = Church::factory()->create();
    $system = User::factory()->create(['role' => UserRole::SYSTEM]);
    $church->assignMember($system);
    ($this->bindChurch)($church);

    $request = Request::create('/dashboard/treasury', 'PUT', ['pix_key' => 'system']);
    $request->setUserResolver(fn () => $system->fresh());

    expect(fn () => (new TreasuryController)->updateSettings($request))->toThrow(HttpException::class);
    expect($church->treasurySetting()->exists())->toBeFalse()
        ->and(app(DashboardInsights::class)->forChurch($church, $system->fresh())['treasury'])->toBeNull();
});

test('the public groups page requires a resolved church', function (): void {
    Group::factory()->create(['is_public' => true]);
    ($this->bindChurch)(null);

    expect(fn () => app(GroupPortalController::class)->index())->toThrow(HttpException::class);
});

test('the public groups page only lists groups of the resolved church', function (): void {
    $church = Church::factory()->create();
    Group::factory()->for($church)->create(['name' => 'Nossa célula', 'is_public' => true]);
    Group::factory()->create(['name' => 'Célula alheia', 'is_public' => true]);
    ($this->bindChurch)($church);

    $response = app(GroupPortalController::class)->index();
    $props = (new ReflectionProperty($response, 'props'))->getValue($response);

    expect(collect($props['groups'])->pluck('name')->all())->toBe(['Nossa célula']);
});
