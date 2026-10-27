<?php

use App\Enums\CategoryType;
use App\Enums\UserRole;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\GroupPortalController;
use App\Models\Category;
use App\Models\Church;
use App\Models\Group;
use App\Models\User;
use App\Support\ChurchContext;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @return array{0: Church, 1: User}
 */
function makeChurchWithLeader(): array
{
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church = Church::create(['name' => 'Nossa Casa', 'slug' => 'nossa-casa-'.uniqid(), 'status' => 'active']);
    $church->assignMember($leader);

    return [$church, $leader->fresh()];
}

function groupController(): GroupController
{
    return app(GroupController::class);
}

/**
 * @param  array<string, mixed>  $payload
 */
function groupRequest(User $actor, array $payload): Request
{
    $request = Request::create('/dashboard/groups', 'POST', $payload);
    $request->setUserResolver(fn () => $actor);

    return $request;
}

test('a church leader can create a group with category, location and responsibles', function (): void {
    [$church, $leader] = makeChurchWithLeader();
    $responsible = User::factory()->create(['role' => UserRole::LEADER]);
    $church->assignMember($responsible);

    $category = Category::query()->create([
        'church_id' => $church->id,
        'name' => 'Célula',
        'slug' => 'celula',
        'type' => CategoryType::GROUP->value,
    ]);

    $this->actingAs($leader);
    $request = groupRequest($leader, [
        'name' => 'Célula Central',
        'description' => 'Encontro semanal de discipulado.',
        'is_public' => true,
        'category_ids' => [$category->id],
        'responsible_ids' => [$responsible->id],
        'address' => [
            'street' => 'Rua da Paz',
            'number' => '100',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zipcode' => '01000-000',
        ],
    ]);

    groupController()->store($request);

    $group = Group::query()->firstOrFail();

    expect($group->name)->toBe('Célula Central')
        ->and($group->church_id)->toBe($church->id)
        ->and($group->is_public)->toBeTrue()
        ->and($group->categories()->pluck('categories.id')->all())->toBe([$category->id])
        ->and($group->responsibleUsers()->pluck('users.id')->all())->toBe([$responsible->id])
        ->and($group->address?->city)->toBe('São Paulo');
});

test('group creation rejects a category from another church or wrong type', function (): void {
    [$church, $leader] = makeChurchWithLeader();

    $wrongTypeCategory = Category::query()->create([
        'church_id' => $church->id,
        'name' => 'Evento',
        'slug' => 'evento',
        'type' => CategoryType::EVENT->value,
    ]);

    $this->actingAs($leader);
    $request = groupRequest($leader, [
        'name' => 'Grupo Inválido',
        'category_ids' => [$wrongTypeCategory->id],
    ]);

    expect(fn () => groupController()->store($request))->toThrow(ValidationException::class);
    expect(Group::query()->count())->toBe(0);
});

test('a church leader can update and delete a group with its address', function (): void {
    [$church, $leader] = makeChurchWithLeader();
    $this->actingAs($leader);

    $group = Group::factory()->for($church)->create(['name' => 'Antigo']);
    $group->address()->create([
        'state' => 'RJ',
        'city' => 'Rio',
        'street' => 'Rua A',
        'zipcode' => '20000-000',
    ]);

    $updateRequest = groupRequest($leader, [
        'name' => 'Estudo Bíblico',
        'is_public' => false,
        'address' => [],
    ]);
    groupController()->update($updateRequest, $group);

    $group->refresh();
    expect($group->name)->toBe('Estudo Bíblico')
        ->and($group->is_public)->toBeFalse()
        ->and($group->address()->exists())->toBeFalse();

    groupController()->destroy(groupRequest($leader, []), $group);
    expect(Group::query()->count())->toBe(0);
});

test('a member cannot manage groups', function (): void {
    [$church, $leader] = makeChurchWithLeader();
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($member);
    $member = $member->fresh();

    $this->actingAs($member);
    $request = groupRequest($member, ['name' => 'Proibido']);

    expect(fn () => groupController()->store($request))->toThrow(HttpException::class);
    expect(Group::query()->count())->toBe(0);
});

test('the group policy only lets managers of the same church manage a group', function (): void {
    [$church, $leader] = makeChurchWithLeader();
    $group = Group::factory()->for($church)->create();

    $otherLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $otherChurch = Church::create(['name' => 'Outra', 'slug' => 'outra-'.uniqid(), 'status' => 'active']);
    $otherChurch->assignMember($otherLeader);

    expect($leader->can('update', $group))->toBeTrue()
        ->and($otherLeader->fresh()->can('update', $group))->toBeFalse();
});

test('the public portal lists only public groups', function (): void {
    $church = Church::create(['name' => 'Nossa Casa', 'slug' => 'nossa-casa-'.uniqid(), 'status' => 'active']);
    Group::factory()->for($church)->create(['name' => 'Público', 'is_public' => true]);
    Group::factory()->for($church)->create(['name' => 'Privado', 'is_public' => false]);

    $visible = Group::query()->where('church_id', $church->id)->where('is_public', true)->get();

    expect($visible)->toHaveCount(1)
        ->and($visible->first()->name)->toBe('Público');
});

test('media, leader and pastor can manage groups; members cannot', function (): void {
    $church = Church::create(['name' => 'Casa', 'slug' => 'casa-'.uniqid(), 'status' => 'active']);

    foreach ([UserRole::MEDIA, UserRole::LEADER, UserRole::CHURCH_LEADER] as $role) {
        $manager = User::factory()->create(['role' => $role]);
        $church->assignMember($manager);

        groupController()->store(groupRequest($manager->fresh(), [
            'name' => 'Grupo '.$role->value,
        ]));
    }

    expect(Group::query()->where('church_id', $church->id)->count())->toBe(3);

    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($member);
    expect(fn () => groupController()->store(groupRequest($member->fresh(), ['name' => 'Proibido'])))
        ->toThrow(HttpException::class);
});

test('a group stores a social media link and rejects an invalid url', function (): void {
    [$church, $leader] = makeChurchWithLeader();
    $this->actingAs($leader);

    groupController()->store(groupRequest($leader, [
        'name' => 'Célula WhatsApp',
        'social_link' => 'https://chat.whatsapp.com/abc123',
    ]));

    expect(Group::query()->firstOrFail()->social_link)->toBe('https://chat.whatsapp.com/abc123');

    expect(fn () => groupController()->store(groupRequest($leader, [
        'name' => 'Inválida',
        'social_link' => 'não-é-url',
    ])))->toThrow(ValidationException::class);
});

test('the public portal groups by category and computes distance to the nearest group', function (): void {
    $church = Church::factory()->create();

    $cell = Category::query()->create(['church_id' => $church->id, 'name' => 'Célula', 'slug' => 'celula', 'type' => CategoryType::GROUP->value]);
    $study = Category::query()->create(['church_id' => $church->id, 'name' => 'Estudo', 'slug' => 'estudo', 'type' => CategoryType::GROUP->value]);

    $near = Group::factory()->for($church)->create(['name' => 'Perto', 'is_public' => true]);
    $near->categories()->attach($cell->id);
    $near->address()->create(['state' => 'SP', 'city' => 'SP', 'street' => 'A', 'zipcode' => '01000-000', 'latitude' => -23.56, 'longitude' => -46.64]);

    $far = Group::factory()->for($church)->create(['name' => 'Longe', 'is_public' => true]);
    $far->categories()->attach($study->id);
    $far->address()->create(['state' => 'RJ', 'city' => 'RJ', 'street' => 'B', 'zipcode' => '20000-000', 'latitude' => -22.90, 'longitude' => -43.20]);

    $context = app(ChurchDomainContext::class);
    foreach (['church' => $church, 'resolved' => true, 'source' => 'test'] as $property => $value) {
        (new ReflectionProperty(ChurchContext::class, $property))->setValue($context, $value);
    }

    $request = Request::create('/groups', 'GET', ['latitude' => -23.55, 'longitude' => -46.63]);
    $response = app(GroupPortalController::class)->index($request);
    $props = (new ReflectionProperty($response, 'props'))->getValue($response);

    expect(collect($props['categories'])->pluck('category')->all())->toBe(['Célula', 'Estudo'])
        ->and($props['nearest']['name'])->toBe('Perto')
        ->and($props['nearest']['distance_km'])->toBeLessThan(5.0)
        ->and($props['hasLocation'])->toBeTrue();
});
