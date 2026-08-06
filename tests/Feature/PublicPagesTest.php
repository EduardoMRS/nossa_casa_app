<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Event;
use App\Models\Form;
use App\Models\User;

test('guests can visit public home page', function () {
    $this->withoutVite();

    $response = $this->get(route('home'));

    $response->assertOk();
});

test('guests can visit public events page', function () {
    $this->withoutVite();

    $response = $this->get(route('events.index'));

    $response->assertOk();
});

test('guests can visit public gallery page', function () {
    $this->withoutVite();

    $response = $this->get(route('gallery.index'));

    $response->assertOk();
});

test('guests can visit public event detail and register pages', function () {
    $this->withoutVite();

    $church = Church::query()->create([
        'name' => 'Igreja Central',
        'slug' => 'igreja-central',
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
        'title' => 'Conferencia de Jovens',
        'slug' => 'conferencia-jovens',
        'description' => '# Programacao',
        'start_time' => now()->addDays(5),
        'end_time' => now()->addDays(5)->addHours(3),
    ]);

    $form = Form::query()->create([
        'title' => 'Inscricao',
        'description' => 'Formulario oficial',
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

    $this->get(route('events.show', ['event' => $event->slug]))->assertOk();
    $this->get(route('events.register', ['event' => $event->slug]))->assertOk();
});
