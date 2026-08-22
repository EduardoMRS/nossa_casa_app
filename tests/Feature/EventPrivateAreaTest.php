<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventUser;
use App\Models\Form;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->church = Church::factory()->create();
    $this->manager = User::factory()->create(['role' => UserRole::LEADER]);
    $this->member = User::factory()->create(['role' => UserRole::MEMBER]);
    $this->church->assignMember($this->manager);
    $this->church->assignMember($this->member);
    $this->event = Event::query()->create([
        'church_id' => $this->church->id, 'author_id' => $this->manager->id,
        'title' => 'Private Conference', 'slug' => 'private-conference-'.Str::lower((string) Str::ulid()),
        'start_time' => now()->addDay(), 'end_time' => now()->addDays(2),
    ]);
});

test('only confirmed participants can see private event posts', function () {
    $post = Post::query()->create([
        'church_id' => $this->church->id, 'author_id' => $this->manager->id,
        'title' => 'Participant notice', 'slug' => 'participant-notice-'.Str::lower((string) Str::ulid()),
        'content' => 'Private instructions', 'published_at' => now(), 'is_event_private' => true,
    ]);
    $this->event->privatePosts()->attach($post);
    EventUser::query()->create(['event_id' => $this->event->id, 'user_id' => $this->member->id, 'status' => 'pending']);

    $this->actingAs($this->member)->get("/events/{$this->event->slug}/area")->assertForbidden();
    EventUser::query()->where('event_id', $this->event->id)->update(['status' => 'confirmed']);

    $this->get("/events/{$this->event->slug}/area")->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Events/PrivateArea')->where('posts.0.title', 'Participant notice'));
});

test('paid registration remains pending until an event manager confirms it', function () {
    $this->event->update(['price' => 125]);
    $this->event->responsibleUsers()->sync([$this->manager->id]);
    $form = Form::query()->create([
        'church_id' => $this->church->id,
        'title' => 'Paid registration',
        'schema' => ['fields' => [['name' => 'name', 'type' => 'text']]],
    ]);
    $this->event->forms()->attach($form);

    $this->actingAs($this->member)->postJson("/api/forms/{$form->id}/responses", ['answers' => ['name' => 'Member']])->assertCreated();
    $registration = EventUser::query()->where('event_id', $this->event->id)->sole();
    expect($registration->status)->toBe('pending');

    $this->actingAs($this->manager)->putJson("/dashboard/eventos/{$this->event->id}/inscritos/{$registration->id}", [
        'status' => 'confirmed', 'answers' => [],
    ])->assertSuccessful();
    expect($registration->refresh()->status)->toBe('confirmed');
});

test('unassigned leaders cannot manage private event content', function () {
    $otherLeader = User::factory()->create(['role' => UserRole::LEADER]);
    $this->church->assignMember($otherLeader);
    $this->actingAs($otherLeader)->get("/dashboard/eventos/{$this->event->id}/conteudos")->assertForbidden();
    $this->actingAs($this->manager)->get("/dashboard/eventos/{$this->event->id}/conteudos")->assertSuccessful();
});

test('event manager creates private posts with the full post workflow', function () {
    $this->actingAs($this->manager)
        ->get("/dashboard/eventos/{$this->event->id}/conteudos/publicacoes/criar")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Posts/Form')
            ->where('privateEvent.id', $this->event->id));

    $this->actingAs($this->manager)->postJson("/dashboard/eventos/{$this->event->id}/conteudos/publicacoes", [
        'title' => 'Internal schedule',
        'slug' => 'internal-schedule',
        'content' => '**Only confirmed participants**',
        'published_at' => now()->format('Y-m-d H:i:s'),
        'category_ids' => [],
    ])->assertCreated();

    $post = Post::query()->where('slug', 'internal-schedule')->sole();
    expect($post->is_event_private)->toBeTrue()
        ->and($this->event->privatePosts()->whereKey($post->id)->exists())->toBeTrue();

    $this->actingAs($this->manager)
        ->get('/dashboard/posts')
        ->assertInertia(fn (Assert $page) => $page->where('posts.data', []));
});
