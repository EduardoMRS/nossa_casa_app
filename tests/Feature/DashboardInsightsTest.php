<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Post;
use App\Models\PrayerRequest;
use App\Models\PublicAccessLog;
use App\Models\Reaction;
use App\Models\User;
use App\Services\DashboardInsights;

function insightsFor(Church $church, User $user): array
{
    return app(DashboardInsights::class)->forChurch($church, $user);
}

test('recent activity lists the church posts and media', function (): void {
    $church = Church::factory()->create();
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($leader);

    Post::query()->create([
        'church_id' => $church->id,
        'author_id' => $leader->id,
        'title' => 'Aviso importante',
        'slug' => 'aviso-importante',
        'content' => 'Conteúdo',
    ]);

    $insights = insightsFor($church, $leader->fresh());

    expect(collect($insights['recent_activity'])->pluck('title'))
        ->toContain('Aviso importante');
});

test('most liked ranks church content by reaction count', function (): void {
    $church = Church::factory()->create();
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($leader);

    $popular = Post::query()->create([
        'church_id' => $church->id, 'author_id' => $leader->id,
        'title' => 'Popular', 'slug' => 'popular', 'content' => 'x',
    ]);
    $quiet = Post::query()->create([
        'church_id' => $church->id, 'author_id' => $leader->id,
        'title' => 'Quieto', 'slug' => 'quieto', 'content' => 'x',
    ]);

    foreach (range(1, 3) as $i) {
        Reaction::query()->create([
            'user_id' => User::factory()->create()->id,
            'reactionable_type' => Post::class,
            'reactionable_id' => $popular->id,
            'content' => '❤️',
            'type' => 'emoji',
        ]);
    }
    Reaction::query()->create([
        'user_id' => User::factory()->create()->id,
        'reactionable_type' => Post::class,
        'reactionable_id' => $quiet->id,
        'content' => '❤️',
        'type' => 'emoji',
    ]);

    $mostLiked = insightsFor($church, $leader->fresh())['most_liked'];

    expect($mostLiked[0]['title'])->toBe('Popular')
        ->and($mostLiked[0]['likes'])->toBe(3);
});

test('the access map is visible to church leaders and hidden from media role', function (): void {
    $church = Church::factory()->create();
    PublicAccessLog::query()->create([
        'church_id' => $church->id,
        'ip_hash' => str_repeat('a', 64),
        'country_code' => 'BR',
        'country' => 'Brazil',
        'region' => 'Bahia',
        'path' => '/pt',
        'visited_at' => now(),
    ]);

    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($leader);
    $media = User::factory()->create(['role' => UserRole::MEDIA]);
    $church->assignMember($media);

    expect(insightsFor($church, $leader->fresh())['access_map'])->toHaveCount(1)
        ->and(insightsFor($church, $media->fresh())['access_map'])->toHaveCount(0);
});

test('shortcuts vary by role', function (): void {
    $church = Church::factory()->create();
    $media = User::factory()->create(['role' => UserRole::MEDIA]);
    $church->assignMember($media);

    $labels = collect(insightsFor($church, $media->fresh())['shortcuts'])
        ->pluck('label_key');

    expect($labels)->toContain('dashboard.shortcuts.new_post')
        ->and($labels)->toContain('dashboard.shortcuts.gallery')
        ->and($labels)->not->toContain('dashboard.shortcuts.new_event');
});

test('recent prayers are visible to leaders and hidden from media role', function (): void {
    $church = Church::factory()->create();
    PrayerRequest::query()->create([
        'church_id' => $church->id,
        'content' => 'Ore por nossa família.',
        'user_id' => null,
    ]);

    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($leader);
    $media = User::factory()->create(['role' => UserRole::MEDIA]);
    $church->assignMember($media);

    $leaderPrayers = insightsFor($church, $leader->fresh())['recent_prayers'];

    expect($leaderPrayers)->toHaveCount(1)
        ->and($leaderPrayers[0]['is_anonymous'])->toBeTrue()
        ->and(insightsFor($church, $media->fresh())['recent_prayers'])->toHaveCount(0);
});

test('the treasury widget sums the current month and is gated by role', function (): void {
    $church = Church::factory()->create();
    $treasurer = User::factory()->create(['role' => UserRole::TREASURER]);
    $church->assignMember($treasurer);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($member);

    Contribution::factory()->for($church)->tithe()->create([
        'amount' => 200,
        'contributed_at' => now(),
    ]);
    Contribution::factory()->for($church)->offering()->create([
        'amount' => 50,
        'contributed_at' => now(),
    ]);

    $treasury = insightsFor($church, $treasurer->fresh())['treasury'];

    expect($treasury)->not->toBeNull()
        ->and($treasury['tithe_total'])->toBe('200.00')
        ->and($treasury['offering_total'])->toBe('50.00')
        ->and($treasury['total'])->toBe('250.00')
        ->and(insightsFor($church, $member->fresh())['treasury'])->toBeNull();
});
