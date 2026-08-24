<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Event;
use App\Models\Form;
use App\Models\Post;
use App\Models\User;

test('leader can create and link a form to events and posts from the current church', function () {
    $church = Church::query()->create(['name' => 'Igreja Formulario', 'slug' => 'igreja-formulario']);
    $leader = User::factory()->create(['role' => UserRole::LEADER]);
    $church->assignMember($leader);
    $event = Event::query()->create(['church_id' => $church->id, 'author_id' => $leader->id, 'title' => 'Encontro', 'slug' => 'encontro-formulario', 'start_time' => now()->addDay(), 'end_time' => now()->addDays(1)->addHour()]);
    $post = Post::query()->create(['church_id' => $church->id, 'author_id' => $leader->id, 'title' => 'Aviso', 'slug' => 'aviso-formulario', 'content' => 'Conteudo', 'published_at' => now()]);

    $this->actingAs($leader)->postJson('/api/forms', [
        'title' => 'Inscrição',
        'description' => 'Dados do participante',
        'schema' => ['fields' => [
            ['id' => 'heading-1', 'label' => 'Dados pessoais', 'type' => 'heading'],
            ['id' => 'name-1', 'name' => 'full_name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'width' => 6, 'size' => 'auto'],
            ['id' => 'divider-1', 'type' => 'divider'],
            ['id' => 'phone-1', 'name' => 'phone', 'label' => 'Telefone', 'type' => 'phone'],
            ['id' => 'amount-1', 'name' => 'amount', 'label' => 'Valor', 'type' => 'money'],
        ]],
        'event_ids' => [$event->id],
        'post_ids' => [$post->id],
    ])->assertCreated();

    $form = Form::query()->where('title', 'Inscrição')->firstOrFail();

    expect($form->events()->whereKey($event->id)->exists())->toBeTrue()
        ->and($form->posts()->whereKey($post->id)->exists())->toBeTrue()
        ->and($form->schema['fields'][0]['type'])->toBe('heading')
        ->and($form->schema['fields'][1]['width'])->toBe(6)
        ->and($form->schema['fields'][3]['type'])->toBe('phone')
        ->and($form->schema['fields'][4]['type'])->toBe('money');

    $newPost = $this->actingAs($leader)->postJson('/api/post', [
        'title' => 'Novo aviso',
        'slug' => 'novo-aviso-formulario',
        'content' => 'Conteudo do aviso',
        'published_at' => now()->toDateTimeString(),
        'form_id' => $form->id,
    ])->assertCreated();

    $newEvent = $this->actingAs($leader)->postJson('/api/event', [
        'title' => 'Novo encontro',
        'slug' => 'novo-encontro-formulario',
        'description' => 'Descricao do encontro',
        'start_time' => now()->addDays(2)->toDateTimeString(),
        'end_time' => now()->addDays(2)->addHour()->toDateTimeString(),
        'form_id' => $form->id,
    ])->assertCreated();

    expect($form->fresh()->posts()->whereKey($newPost->json('id'))->exists())->toBeTrue()
        ->and($form->fresh()->events()->whereKey($newEvent->json('id'))->exists())->toBeTrue();
});
