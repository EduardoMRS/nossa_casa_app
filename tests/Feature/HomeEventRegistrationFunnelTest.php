<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventUser;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeFunnelFixtures(): array
{
    $church = Church::query()->create([
        'name' => 'Igreja Caminho da Vida',
        'slug' => 'igreja-caminho-da-vida',
    ]);

    $author = User::factory()->create([
        'role' => UserRole::LEADER,
    ]);

    $author->profile()->create([
        'church_id' => $church->id,
    ]);

    $event = Event::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Conferencia de Voluntariado',
        'slug' => 'conferencia-voluntariado',
        'description' => 'Dia especial para alinhamento da equipe.',
        'start_time' => now()->addDays(4),
        'end_time' => now()->addDays(4)->addHours(3),
    ]);

    $form = Form::query()->create([
        'title' => 'Formulario de inscricao',
        'description' => 'Preencha os dados para participar.',
        'church_id' => $church->id,
        'schema' => [
            [
                'name' => 'full_name',
                'label' => 'Nome completo',
                'type' => 'text',
                'required' => true,
            ],
        ],
    ]);

    $event->forms()->attach($form->id);

    return compact('church', 'author', 'event', 'form');
}

test('guest can navigate the full public funnel from home to event registration page', function () {
    $fixtures = makeFunnelFixtures();
    /** @var Event $event */
    $event = $fixtures['event'];
    /** @var Form $form */
    $form = $fixtures['form'];

    $this->get('/')
        ->assertSuccessful();

    $this->get('/events')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Index')
            ->has('events.data', 1)
        );

    $this->get("/events/{$event->slug}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('event.slug', $event->slug)
            ->where('registration.form_id', $form->id)
        );

    $this->get("/events/{$event->slug}/register")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Register')
            ->where('form.id', $form->id)
        );
});

test('authenticated member can complete home to event registration funnel', function () {
    $fixtures = makeFunnelFixtures();
    /** @var Church $church */
    $church = $fixtures['church'];
    /** @var Event $event */
    $event = $fixtures['event'];
    /** @var Form $form */
    $form = $fixtures['form'];

    $attendee = User::factory()->create([
        'role' => UserRole::MEMBER,
    ]);

    $attendee->profile()->create([
        'church_id' => $church->id,
    ]);

    $this->actingAs($attendee)
        ->get('/')
        ->assertSuccessful();

    $this->actingAs($attendee)
        ->get("/events/{$event->slug}")
        ->assertSuccessful();

    $this->actingAs($attendee)
        ->get("/events/{$event->slug}/register")
        ->assertSuccessful();

    $this->actingAs($attendee)
        ->postJson("/api/forms/{$form->id}/responses", [
            'answers' => [
                'full_name' => 'Membro Teste',
            ],
        ])
        ->assertCreated();

    expect(
        FormResponse::query()
            ->where('form_id', $form->id)
            ->where('user_id', $attendee->id)
            ->exists()
    )->toBeTrue();

    expect(
        EventUser::query()
            ->where('event_id', $event->id)
            ->where('user_id', $attendee->id)
            ->exists()
    )->toBeTrue();
});
