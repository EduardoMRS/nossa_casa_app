<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Form;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->church = Church::factory()->create();
    $this->teacher = User::factory()->create(['role' => UserRole::LEADER]);
    $this->member = User::factory()->create(['role' => UserRole::MEMBER]);
    $this->outsider = User::factory()->create(['role' => UserRole::MEMBER]);
    $this->church->assignMember($this->teacher);
    $this->church->assignMember($this->member);
    $this->classroom = Classroom::query()->create([
        'church_id' => $this->church->id,
        'teacher_id' => $this->teacher->id,
        'name' => 'Discipulado',
        'description' => 'Sala privada',
        'portal_enabled' => true,
    ]);
    $this->classroom->members()->attach($this->member);
});

test('only classroom participants can open its private portal', function () {
    $this->actingAs($this->member)
        ->get("/classrooms/{$this->classroom->slug}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Classrooms/Portal')
            ->where('classroom.name', 'Discipulado'));

    $this->actingAs($this->outsider)
        ->get("/classrooms/{$this->classroom->slug}")
        ->assertForbidden();
});

test('classroom posts stay private and honor interaction switches', function () {
    $post = Post::query()->create([
        'church_id' => $this->church->id,
        'author_id' => $this->teacher->id,
        'title' => 'Aviso privado',
        'slug' => 'aviso-'.Str::lower((string) Str::ulid()),
        'content' => 'Somente a turma',
        'published_at' => now(),
        'visibility' => 'classroom_private',
        'comments_enabled' => false,
        'reactions_enabled' => false,
    ]);
    $this->classroom->posts()->attach($post);

    $this->actingAs($this->member)
        ->get("/classrooms/{$this->classroom->slug}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('wallPosts.0.post.title', 'Aviso privado')
            ->where('wallPosts.0.canComment', false)
            ->where('wallPosts.0.canReact', false));

    $this->postJson('/api/comments', [
        'commentable_type' => 'post', 'commentable_id' => $post->id, 'content' => 'Tentativa',
    ])->assertForbidden();
    $this->postJson('/api/reactions', [
        'reactionable_type' => 'post', 'reactionable_id' => $post->id, 'content' => '👍',
    ])->assertForbidden();

    $this->actingAs($this->outsider)->get("/posts/{$post->slug}")->assertNotFound();
});

test('members submit dynamic activities within the configured attempt limit', function () {
    $form = Form::query()->create([
        'church_id' => $this->church->id,
        'title' => 'Revisão',
        'schema' => ['fields' => [
            ['name' => 'resposta', 'label' => 'Resposta', 'type' => 'textarea', 'required' => true],
        ]],
    ]);
    $activity = $this->classroom->activities()->create([
        'form_id' => $form->id,
        'created_by_id' => $this->teacher->id,
        'title' => 'Lição 1',
        'published_at' => now(),
        'max_attempts' => 1,
        'is_published' => true,
    ]);

    $this->actingAs($this->member)
        ->postJson("/classrooms/{$this->classroom->slug}/activities/{$activity->id}/submissions", ['answers' => []])
        ->assertUnprocessable();

    $this->postJson("/classrooms/{$this->classroom->slug}/activities/{$activity->id}/submissions", [
        'answers' => ['resposta' => 'Minha resposta'],
    ])->assertCreated();

    $this->postJson("/classrooms/{$this->classroom->slug}/activities/{$activity->id}/submissions", [
        'answers' => ['resposta' => 'Outra'],
    ])->assertUnprocessable();
});

test('classroom forum accepts discussions and locks new replies', function () {
    $discussionId = $this->actingAs($this->member)
        ->postJson("/classrooms/{$this->classroom->slug}/discussions", [
            'title' => 'Dúvida da aula', 'content' => 'Podemos revisar este tópico?',
        ])
        ->assertCreated()
        ->json('id');

    $this->postJson("/classrooms/{$this->classroom->slug}/discussions/{$discussionId}/replies", [
        'content' => 'Complementando a dúvida',
    ])->assertCreated();

    $this->classroom->discussions()->findOrFail($discussionId)->update(['is_locked' => true]);

    $this->postJson("/classrooms/{$this->classroom->slug}/discussions/{$discussionId}/replies", [
        'content' => 'Não deve entrar',
    ])->assertStatus(423);
});
