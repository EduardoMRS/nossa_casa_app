<?php

use App\Enums\UserRelationships;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Community;
use App\Models\Event;
use App\Models\EventUser;
use App\Models\Post;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.url' => 'http://platform.test']);
    $this->withoutVite();
});

function createForeignAccessChurch(string $name, string $domain): Church
{
    $community = Community::factory()->create([
        'name' => $name.' Community',
        'slug' => str($name)->slug()->append('-community')->toString(),
    ]);

    return Church::factory()->create([
        'name' => $name,
        'slug' => str($name)->slug()->toString(),
        'domain' => $domain,
        'community_id' => $community->id,
    ]);
}

test('a foreign church visitor can use public interactions but not private routes', function () {
    $homeChurch = createForeignAccessChurch('Home Church', 'home-church.test');
    $visitedChurch = createForeignAccessChurch('Visited Church', 'visited-church.test');
    $visitor = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $child = User::factory()->create(['birth_date' => now()->subYears(7)->toDateString()]);
    $author = User::factory()->create(['role' => UserRole::MEDIA]);
    $homeChurch->assignMember($visitor);
    $homeChurch->assignMember($child);
    $visitedChurch->assignMember($author);
    $visitor->relationships()->create([
        'related_user_id' => $child->id,
        'relationship_type' => UserRelationships::PARENT->value,
    ]);
    $post = Post::query()->create([
        'church_id' => $visitedChurch->id,
        'author_id' => $author->id,
        'title' => 'Visited update',
        'slug' => 'visited-update',
        'content' => 'Public content',
        'published_at' => now(),
    ]);

    $this->actingAs($visitor)
        ->get('http://visited-church.test/posts/visited-update')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canInteract', true)
            ->where('churchContext.isForeignChurch', true)
            ->where('churchContext.userChurch.url', 'https://home-church.test/'));

    $commentId = $this->postJson('http://visited-church.test/api/comments', [
        'commentable_type' => 'post',
        'commentable_id' => $post->id,
        'content' => 'A visitor comment',
    ])->assertCreated()->json('id');

    $this->postJson('http://visited-church.test/api/reactions', [
        'reactionable_type' => 'post',
        'reactionable_id' => $post->id,
        'content' => '🙏',
    ])->assertCreated();

    $this->putJson("http://visited-church.test/api/comments/{$commentId}", [
        'content' => 'Updated visitor comment',
    ])->assertSuccessful();

    $kidsClassroom = Classroom::query()->create([
        'church_id' => $visitedChurch->id,
        'name' => 'Visitors Kids',
        'min_age' => 5,
        'max_age' => 10,
        'is_kids' => true,
    ]);
    $regularClassroom = Classroom::query()->create([
        'church_id' => $visitedChurch->id,
        'name' => 'Membership Class',
        'is_kids' => false,
    ]);

    $checkIn = $this->postJson("http://visited-church.test/api/classrooms/{$kidsClassroom->id}/check-in", [
        'user_id' => $child->id,
    ])->assertSuccessful();

    $this->postJson("http://visited-church.test/api/classrooms/{$regularClassroom->id}/check-in", [
        'user_id' => $visitor->id,
    ])->assertForbidden();

    $this->postJson("http://visited-church.test/api/classrooms/{$kidsClassroom->id}/check-out", [
        'user_id' => $child->id,
        'pin' => $checkIn->json('checkout_pin'),
    ])->assertSuccessful();

    $this->get('http://visited-church.test/dashboard')
        ->assertForbidden();
    $this->get('http://visited-church.test/settings/profile')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('settings/Workspace'));
    $this->postJson('http://visited-church.test/api/event', [])->assertForbidden();
});

test('a confirmed participant from another church can access the event participant area', function () {
    $homeChurch = createForeignAccessChurch('Participant Home Church', 'participant-home.test');
    $visitedChurch = createForeignAccessChurch('Participant Event Church', 'participant-event.test');
    $visitor = User::factory()->create(['role' => UserRole::MEMBER]);
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $homeChurch->assignMember($visitor);
    $visitedChurch->assignMember($leader);
    $event = Event::query()->create([
        'church_id' => $visitedChurch->id,
        'author_id' => $leader->id,
        'title' => 'Event for visitors',
        'slug' => 'event-for-visitors',
        'description' => 'A public event with a confirmed participant area.',
        'start_time' => now()->addDay(),
        'end_time' => now()->addDays(2),
    ]);
    Address::query()->create([
        'addressable_type' => Event::class,
        'addressable_id' => $event->id,
        'country' => 'Brasil',
        'state' => 'RO',
        'city' => 'Ji-Parana',
        'street' => 'Rua do Evento',
        'number' => '42',
        'zipcode' => '76900-000',
    ]);
    EventUser::query()->create([
        'event_id' => $event->id,
        'user_id' => $visitor->id,
        'status' => 'confirmed',
    ]);

    $this->actingAs($visitor)
        ->get("http://participant-event.test/events/{$event->slug}/area")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/PrivateArea')
            ->where('event.slug', $event->slug)
            ->where('event.address.city', 'Ji-Parana')
            ->missing('event.address.id'));
});

test('membership transfer requires confirmation and downgrades local elevated roles', function () {
    $homeChurch = createForeignAccessChurch('Leadership Church', 'leadership.test');
    $visitedChurch = createForeignAccessChurch('New Church', 'new-church.test');
    $leader = User::factory()->create(['role' => UserRole::SUPERADMIN]);
    $homeChurch->assignMember($leader);

    $this->actingAs($leader)
        ->post('http://new-church.test/church-membership/switch')
        ->assertSessionHasErrors('confirmed');

    expect($leader->fresh()->profile->church_id)->toBe($homeChurch->id)
        ->and($leader->fresh()->role)->toBe(UserRole::SUPERADMIN);

    $this->post('http://new-church.test/church-membership/switch', [
        'confirmed' => true,
    ])->assertRedirect('http://new-church.test');

    expect($leader->fresh()->profile->church_id)->toBe($visitedChurch->id)
        ->and($leader->fresh()->profile->community_id)->toBe($visitedChurch->community_id)
        ->and($leader->fresh()->role)->toBe(UserRole::MEMBER);
});
