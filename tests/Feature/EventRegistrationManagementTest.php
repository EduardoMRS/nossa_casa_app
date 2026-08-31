<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventUser;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->church = Church::query()->create([
        'name' => 'Registration Church',
        'slug' => 'registration-church',
        'status' => 'active',
    ]);
    $this->leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $this->church->assignMember($this->leader);
    $this->event = Event::query()->create([
        'church_id' => $this->church->id,
        'author_id' => $this->leader->id,
        'title' => 'Conferência Comunitária',
        'slug' => 'community-conference-'.Str::lower((string) Str::ulid()),
        'start_time' => now()->addDay(),
        'end_time' => now()->addDays(2),
    ]);
    $this->form = Form::query()->create([
        'church_id' => $this->church->id,
        'title' => 'Registration form',
        'schema' => [
            'fields' => [
                ['name' => 'diet', 'label' => 'Dietary needs', 'type' => 'text'],
            ],
        ],
    ]);
    $this->event->forms()->attach($this->form);
});

test('church administrator can view registered users and their submitted answers', function () {
    $attendee = User::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Silva',
        'email' => 'ana@example.test',
    ]);
    EventUser::query()->create([
        'event_id' => $this->event->id,
        'user_id' => $attendee->id,
        'status' => 'pending',
    ]);
    FormResponse::query()->create([
        'form_id' => $this->form->id,
        'user_id' => $attendee->id,
        'answers' => ['diet' => 'Vegetarian'],
    ]);

    $this->actingAs($this->leader)
        ->get("/dashboard/eventos/{$this->event->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/EventRegistrations')
            ->where('registrations.0.name', 'Ana Silva')
            ->where('registrations.0.answers.diet', 'Vegetarian')
            ->where('columns.5.key', 'answers.diet'));
});

test('church administrator can add and edit a registration without creating a user account', function () {
    $usersBefore = User::query()->count();

    $this->actingAs($this->leader)
        ->postJson("/dashboard/eventos/{$this->event->id}/inscritos", [
            'first_name' => 'Guest',
            'last_name' => 'Participant',
            'email' => 'guest@example.test',
            'phone' => '555-0100',
            'answers' => ['diet' => 'None'],
        ])
        ->assertCreated();

    $registration = EventUser::query()->sole();

    expect($registration->user_id)->toBeNull()
        ->and(User::query()->count())->toBe($usersBefore);

    $this->actingAs($this->leader)
        ->putJson("/dashboard/eventos/{$this->event->id}/inscritos/{$registration->id}", [
            'first_name' => 'Updated',
            'last_name' => 'Participant',
            'email' => 'guest@example.test',
            'phone' => '555-0101',
            'status' => 'approved',
            'answers' => ['diet' => 'Vegan'],
        ])
        ->assertSuccessful();

    expect($registration->refresh())
        ->first_name->toBe('Updated')
        ->status->toBe('approved')
        ->answers->toBe(['diet' => 'Vegan']);
});

test('registration exports produce PDF and XLSX downloads with selected columns', function () {
    $registration = EventUser::query()->create([
        'event_id' => $this->event->id,
        'first_name' => 'Guest',
        'last_name' => 'Export',
        'email' => 'export@example.test',
        'status' => 'pending',
        'answers' => ['diet' => 'None'],
    ]);

    EventUser::query()->create([
        'event_id' => $this->event->id,
        'first_name' => 'Ana Júlia',
        'last_name' => 'Gonçalves',
        'email' => 'ana@example.test',
        'status' => 'confirmed',
        'answers' => ['diet' => 'Sem glúten'],
    ]);

    $pdf = $this->actingAs($this->leader)
        ->get("/dashboard/eventos/{$this->event->id}/inscritos/exportar/pdf?columns[]=name&columns[]=answers.diet")
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
    $xlsx = $this->get("/dashboard/eventos/{$this->event->id}/inscritos/exportar/xlsx?columns[]=name")
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $individualPdf = $this->get("/dashboard/eventos/{$this->event->id}/inscritos/{$registration->id}/pdf")
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    expect($pdf->getContent())->toStartWith('%PDF-1.4')
        ->and($pdf->getContent())->toContain('Registration Church')
        ->and($pdf->getContent())->toContain('Dietary needs')
        ->and($pdf->getContent())->toContain('(None)')
        ->and($pdf->getContent())->not->toContain('(Email)')
        ->and($pdf->getContent())->not->toContain('(Field)')
        ->and($pdf->getContent())->not->toContain('(Information)')
        ->and($pdf->getContent())->toContain('/MediaBox [0 0 595 842]')
        ->and($pdf->getContent())->not->toContain('/MediaBox [0 0 842 595]')
        ->and($pdf->getContent())->toContain('/Encoding /WinAnsiEncoding')
        ->and($pdf->getContent())->toContain(iconv('UTF-8', 'Windows-1252', 'Conferência Comunitária'))
        ->and(substr_count($pdf->getContent(), '(Participant '))->toBe(2)
        ->and($individualPdf->getContent())->toContain('/MediaBox [0 0 595 842]')
        ->and($individualPdf->getContent())->toContain('(Dietary needs)')
        ->and($individualPdf->getContent())->toContain('(None)')
        ->and($individualPdf->getContent())->not->toContain('(Field)')
        ->and($individualPdf->getContent())->not->toContain('(Information)')
        ->and($individualPdf->getContent())->toContain('Nossa Casa - open source project')
        ->and($xlsx->getContent())->toStartWith('PK');
});

test('church administrator cannot manage registrations from another church', function () {
    $otherChurch = Church::query()->create([
        'name' => 'Other Church',
        'slug' => 'other-registration-church',
        'status' => 'active',
    ]);
    $otherLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $otherChurch->assignMember($otherLeader);

    $this->actingAs($otherLeader)
        ->get("/dashboard/eventos/{$this->event->id}")
        ->assertForbidden();
});
