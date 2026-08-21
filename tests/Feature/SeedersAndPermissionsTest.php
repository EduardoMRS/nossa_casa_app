<?php

use App\Enums\CategoryType;
use App\Enums\MediaStatus;
use App\Enums\UserRelationships;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\AiModel;
use App\Models\AiQuery;
use App\Models\Calendar;
use App\Models\Category;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\ClassroomPresence;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Highlight;
use App\Models\Library;
use App\Models\Media;
use App\Models\Network;
use App\Models\Post;
use App\Models\PrayerRequest;
use App\Models\Setting;
use App\Models\Translation;
use App\Models\User;
use App\Models\UserRelationship;
use Database\Seeders\InitialAiModelSeeder;
use Database\Seeders\RelationTesterSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('relation tester seeder creates a complete and repeatable demonstration graph', function () {
    Storage::fake((string) config('media.disk'));
    $this->seed(InitialAiModelSeeder::class);
    $this->seed(RelationTesterSeeder::class);
    $this->seed(RelationTesterSeeder::class);

    $church = Church::query()->where('slug', 'assembleia-de-deus-machadinho-doeste')->firstOrFail();
    $event = Event::query()->where('slug', 'conferencia-da-familia-machadinho')->firstOrFail();
    $post = Post::query()->where('slug', 'familias-firmes-em-cristo-machadinho')->firstOrFail();
    $form = Form::query()->where('church_id', $church->id)->firstOrFail();
    $kidsClassroom = Classroom::query()->where('church_id', $church->id)->where('is_kids', true)->firstOrFail();
    $kidsPresence = ClassroomPresence::query()->where('classroom_id', $kidsClassroom->id)->whereNull('check_out')->firstOrFail();

    $community = $church->community()->firstOrFail();
    $branding = $church->settings()->firstOrFail()->options['branding'];

    expect(Church::query()->where('slug', 'assembleia-de-deus-machadinho-doeste')->count())->toBe(1)
        ->and(Church::query()->where('slug', 'congregacao-ad-bom-futuro')->exists())->toBeTrue()
        ->and(Church::query()->where('slug', 'primeira-igreja-batista-ji-parana')->exists())->toBeTrue()
        ->and(Church::query()->where('slug', 'igreja-batista-esperanca-ariquemes')->exists())->toBeTrue()
        ->and(Network::query()->where('parent_church_id', $church->id)->exists())->toBeTrue()
        ->and($church->members()->count())->toBe(8)
        ->and(User::query()->whereIn('email', [
            'visitante@nossacasa.test',
            config('app.system_user.email'),
            'superadmin@nossacasa.test',
            'church_leader@nossacasa.test',
            'leader@nossacasa.test',
            'media@nossacasa.test',
            'member@nossacasa.test',
            'child@nossacasa.test',
        ])->count())->toBe(8)
        ->and(User::query()->where('email', 'visitante@nossacasa.test')->value('role'))->toBe(UserRole::GUEST)
        ->and(User::query()->where('email', 'superadmin@nossacasa.test')->value('role'))->toBe(UserRole::SUPERADMIN)
        ->and($form->events()->whereKey($event->id)->exists())->toBeTrue()
        ->and($form->posts()->whereKey($post->id)->exists())->toBeTrue()
        ->and(FormResponse::query()->where('form_id', $form->id)->where('user_id', User::query()->where('email', 'member@nossacasa.test')->value('id'))->exists())->toBeTrue()
        ->and($event->users()->where('users.email', 'member@nossacasa.test')->exists())->toBeTrue()
        ->and($event->confirmations()->whereNotNull('check_in_at')->whereNotNull('check_out_at')->exists())->toBeTrue()
        ->and(Address::query()->where('addressable_type', Event::class)->where('addressable_id', $event->id)->exists())->toBeTrue()
        ->and($post->medias()->exists())->toBeTrue()
        ->and($post->classrooms()->exists())->toBeTrue()
        ->and($post->comments()->has('replies')->exists())->toBeTrue()
        ->and(Highlight::query()->where('church_id', $church->id)->count())->toBe(2)
        ->and(Calendar::query()->where('church_id', $church->id)->where('calendarable_id', $event->id)->exists())->toBeTrue()
        ->and(Translation::query()->where('translatable_id', $post->id)->exists())->toBeTrue()
        ->and($kidsClassroom->members()->where('users.email', 'child@nossacasa.test')->exists())->toBeTrue()
        ->and(Hash::check('123456', $kidsPresence->checkout_pin))->toBeTrue()
        ->and(Classroom::query()->where('church_id', $church->id)->where('gender_restriction', 'female')->exists())->toBeTrue()
        ->and(ClassroomPresence::query()->whereNotNull('check_out')->exists())->toBeTrue()
        ->and(PrayerRequest::query()->where('church_id', $church->id)->whereNull('user_id')->exists())->toBeTrue()
        ->and(Library::query()->where('church_id', $church->id)->exists())->toBeTrue()
        ->and(UserRelationship::query()->count())->toBe(3)
        ->and(Setting::query()->where('church_id', $church->id)->exists())->toBeTrue()
        ->and($branding['primary_color'])->toBe('#123B6D')
        ->and($branding['icon_name'])->toBe('Flame')
        ->and(Storage::disk((string) config('media.disk'))->exists($branding['logo_path']))->toBeTrue()
        ->and($community->bible_versions)->toBe(['pt-br-nvi', 'pt-br-nvt', 'pt-almeida-1911'])
        ->and($community->default_bible_version)->toBe('pt-br-nvi')
        ->and(Classroom::query()->whereHas('church', fn ($query) => $query->where('slug', 'primeira-igreja-batista-ji-parana'))->where('name', 'Salinha Sementinhas - 4 a 7 anos')->exists())->toBeTrue()
        ->and(Form::query()->whereHas('events', fn ($query) => $query->where('slug', 'tarde-divertida-pib-kids'))->exists())->toBeTrue()
        ->and(Post::query()->where('slug', 'cafe-com-esperanca-ariquemes')->exists())->toBeTrue()
        ->and(Category::query()->where('church_id', $church->id)->where('type', 'form')->exists())->toBeTrue()
        ->and(AiModel::query()->where('model_id', 'inclusionai/ling-3.0-flash:free')->exists())->toBeTrue()
        ->and(AiQuery::query()->where('church_id', $church->id)->exists())->toBeTrue();
});

test('church-scoped workflows reject requests from another congregation', function () {
    $church = Church::query()->create(['name' => 'Igreja Local', 'slug' => 'igreja-local']);
    $otherChurch = Church::query()->create(['name' => 'Igreja Externa', 'slug' => 'igreja-externa']);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $mediaModerator = User::factory()->create(['role' => UserRole::MEDIA]);
    $leader = User::factory()->create(['role' => UserRole::LEADER]);
    $otherLeader = User::factory()->create(['role' => UserRole::LEADER]);
    $otherMember = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($member);
    $church->assignMember($mediaModerator);
    $church->assignMember($leader);
    $otherChurch->assignMember($otherLeader);
    $otherChurch->assignMember($otherMember);

    $event = Event::query()->create([
        'church_id' => $otherChurch->id,
        'author_id' => $otherLeader->id,
        'title' => 'Evento externo',
        'slug' => 'evento-externo',
        'start_time' => now()->addDay(),
        'end_time' => now()->addDays(2),
    ]);
    $form = Form::query()->create([
        'church_id' => $otherChurch->id,
        'title' => 'Formulario externo',
        'schema' => ['fields' => [['id' => 'name', 'name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true]]],
    ]);
    $post = Post::query()->create([
        'church_id' => $otherChurch->id,
        'author_id' => $otherLeader->id,
        'title' => 'Post externo',
        'slug' => 'post-externo',
        'content' => 'Conteudo externo',
        'published_at' => now(),
    ]);
    $media = Media::query()->create([
        'church_id' => $otherChurch->id,
        'uploader_id' => $otherLeader->id,
        'file_path' => 'seeders/externa.jpg',
        'mimetype' => 'image/jpeg',
        'size' => 100,
        'gallery' => true,
        'status' => MediaStatus::PENDING,
    ]);
    $classroom = Classroom::query()->create(['church_id' => $otherChurch->id, 'name' => 'Sala externa']);
    $category = Category::query()->create([
        'church_id' => $otherChurch->id,
        'name' => 'Categoria externa',
        'slug' => 'categoria-externa',
        'type' => CategoryType::POST,
    ]);

    $this->actingAs($member)->postJson("/api/forms/{$form->id}/responses", ['answers' => ['name' => 'Member']])->assertForbidden();
    $this->actingAs($member)->postJson("/api/event/{$event->id}/checkin")->assertForbidden();
    $this->actingAs($member)->postJson('/api/comments', [
        'commentable_type' => 'post',
        'commentable_id' => $post->id,
        'content' => 'Comentario externo',
    ])->assertForbidden();
    $this->actingAs($member)->getJson("/api/classrooms/{$classroom->id}")->assertForbidden();
    $this->actingAs($mediaModerator)->putJson("/api/admin/media/{$media->id}/status", [
        'status' => MediaStatus::APPROVED->value,
    ])->assertForbidden();
    $this->actingAs($mediaModerator)->putJson("/api/church/{$otherChurch->id}/highlights", [
        'highlights' => [],
    ])->assertForbidden();
    $this->actingAs($leader)->postJson("/api/items/post/{$post->id}/categorize", [
        'category_id' => $category->id,
    ])->assertForbidden();
    $this->actingAs($leader)->postJson("/api/users/{$leader->id}/family-relationship", [
        'related_user_id' => $otherMember->id,
        'relationship_type' => UserRelationships::FRIEND->value,
    ])->assertStatus(422);
});

test('event check-in requires a registration and anonymous prayer requests keep the sender private', function () {
    $church = Church::query()->create(['name' => 'Igreja Esperanca', 'slug' => 'igreja-esperanca']);
    $leader = User::factory()->create(['role' => UserRole::LEADER]);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($leader);
    $church->assignMember($member);

    $event = Event::query()->create([
        'church_id' => $church->id,
        'author_id' => $leader->id,
        'title' => 'Culto de domingo',
        'slug' => 'culto-de-domingo',
        'start_time' => now()->addDay(),
        'end_time' => now()->addDays(2),
    ]);

    $this->actingAs($member)->postJson("/api/event/{$event->id}/checkin")->assertStatus(422);

    $event->users()->attach($member->id);

    $this->actingAs($member)->postJson("/api/event/{$event->id}/checkin")->assertSuccessful();
    $this->actingAs($member)->postJson('/api/prayer-requests', [
        'content' => 'Pedido de oracao anonimo.',
        'is_anonymous' => true,
    ])->assertCreated();

    expect(PrayerRequest::query()->where('content', 'Pedido de oracao anonimo.')->value('user_id'))->toBeNull()
        ->and(PrayerRequest::query()->where('content', 'Pedido de oracao anonimo.')->value('church_id'))->toBe($church->id);
});
