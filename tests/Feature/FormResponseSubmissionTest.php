<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventUser;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\User;

test('authenticated user can submit event registration form response', function () {
    $church = Church::query()->create([
        'name' => 'Igreja Nova Alianca',
        'slug' => 'igreja-nova-alianca',
    ]);

    $author = User::factory()->create([
        'role' => UserRole::LEADER,
    ]);

    $author->profile()->create([
        'church_id' => $church->id,
    ]);

    $attendee = User::factory()->create([
        'role' => UserRole::MEMBER,
    ]);

    $attendee->profile()->create([
        'church_id' => $church->id,
    ]);

    $event = Event::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Escola Biblica',
        'slug' => 'escola-biblica',
        'description' => 'Encontro semanal',
        'start_time' => now()->addWeek(),
        'end_time' => now()->addWeek()->addHours(2),
    ]);

    $form = Form::query()->create([
        'title' => 'Ficha de inscricao',
        'description' => 'Dados do participante',
        'church_id' => $church->id,
        'schema' => [
            [
                'name' => 'full_name',
                'label' => 'Nome completo',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'document',
                'label' => 'Documento',
                'type' => 'document',
                'required' => false,
            ],
        ],
    ]);

    $event->forms()->attach($form->id);

    $response = $this->actingAs($attendee)->postJson("/api/forms/{$form->id}/responses", [
        'answers' => [
            'full_name' => 'Membro Teste',
            'document' => ['type' => 'cpf', 'number' => '123.456.789-09'],
        ],
    ]);

    $response->assertCreated();

    expect(FormResponse::query()->where('form_id', $form->id)->where('user_id', $attendee->id)->exists())->toBeTrue()
        ->and(FormResponse::query()->where('form_id', $form->id)->where('user_id', $attendee->id)->firstOrFail()->answers['document'])->toBe(['type' => 'cpf', 'number' => '123.456.789-09']);
    expect(EventUser::query()->where('event_id', $event->id)->where('user_id', $attendee->id)->exists())->toBeTrue();
});

test('required schema fields are validated on form response submission', function () {
    $church = Church::query()->create([
        'name' => 'Igreja Esperanca',
        'slug' => 'igreja-esperanca',
    ]);

    $author = User::factory()->create([
        'role' => UserRole::LEADER,
    ]);

    $author->profile()->create([
        'church_id' => $church->id,
    ]);

    $attendee = User::factory()->create([
        'role' => UserRole::MEMBER,
    ]);

    $attendee->profile()->create([
        'church_id' => $church->id,
    ]);

    $event = Event::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Culto de domingo',
        'slug' => 'culto-domingo',
        'description' => 'Celebracao semanal',
        'start_time' => now()->addDays(2),
        'end_time' => now()->addDays(2)->addHours(2),
    ]);

    $form = Form::query()->create([
        'title' => 'Inscricao dominical',
        'description' => null,
        'church_id' => $church->id,
        'schema' => [
            [
                'name' => 'full_name',
                'label' => 'Nome completo',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'document',
                'label' => 'Documento',
                'type' => 'document',
                'required' => true,
            ],
        ],
    ]);

    $event->forms()->attach($form->id);

    $response = $this->actingAs($attendee)->postJson("/api/forms/{$form->id}/responses", [
        'answers' => [
            'full_name' => '',
            'document' => ['type' => 'cpf', 'number' => ''],
        ],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['answers.full_name', 'answers.document']);
});
