<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventUser;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\User;
use App\Support\EventRegistrationExporter;
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
                ['name' => 'diet', 'label' => 'Dietary needs', 'type' => 'text', 'width' => 6],
                ['name' => 'arrival', 'label' => 'Arrival time', 'type' => 'time', 'width' => 6],
                ['id' => 'registration-break', 'type' => 'line_break'],
                ['name' => 'event_day', 'label' => 'Event day', 'type' => 'date', 'width' => 6],
                ['name' => 'contact_phone', 'label' => 'Contact phone', 'type' => 'phone', 'width' => 6],
                ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'width' => 8],
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
            ->where('columns.5.key', 'answers.diet')
            ->where('formFields.0.width', 6)
            ->where('formFields.1.width', 6)
            ->where('formFields.2.width', 6)
            ->where('formFields.2.break_before', true)
            ->where('formFields.3.width', 6)
            ->where('formFields.4.width', 8));
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
        'phone' => '+5569984011007',
        'status' => 'pending',
        'answers' => [
            'diet' => 'None',
            'arrival' => '18:30',
            'event_day' => '2026-09-15',
            'contact_phone' => '69984011007',
        ],
    ]);

    EventUser::query()->create([
        'event_id' => $this->event->id,
        'first_name' => 'Ana Júlia',
        'last_name' => 'Gonçalves',
        'email' => 'ana@example.test',
        'phone' => '+5569984011008',
        'status' => 'confirmed',
        'answers' => [
            'diet' => 'Sem glúten',
            'arrival' => '19:45',
            'event_day' => '2026-09-16',
            'contact_phone' => '69984011008',
        ],
    ]);

    $pdf = $this->actingAs($this->leader)
        ->get("/dashboard/eventos/{$this->event->id}/inscritos/exportar/pdf?columns[]=name&columns[]=phone&columns[]=answers.diet&columns[]=answers.arrival&columns[]=answers.event_day&columns[]=answers.contact_phone")
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
        ->and($pdf->getContent())->toContain('(18:30)')
        ->and($pdf->getContent())->toContain('(15/09/2026)')
        ->and($pdf->getContent())->toContain('+55 \\(69\\) 98401-1007')
        ->and($pdf->getContent())->toContain('(\\(69\\) 98401-1007)')
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
        ->and($individualPdf->getContent())->toContain('(15/09/2026)')
        ->and($individualPdf->getContent())->toContain('+55 \\(69\\) 98401-1007')
        ->and($individualPdf->getContent())->not->toContain('(Field)')
        ->and($individualPdf->getContent())->not->toContain('(Information)')
        ->and($individualPdf->getContent())->toContain('Nossa Casa - open source project')
        ->and($xlsx->getContent())->toStartWith('PK');
});

test('PDF exporter respects the twelve column form grid and explicit row breaks', function () {
    $pdf = app(EventRegistrationExporter::class)->pdf(
        'Grid event',
        ['First name', 'Last name', 'E-mail'],
        [['Ana', 'Silva', 'ana@example.test']],
        fieldLayout: [
            ['width' => 6],
            ['width' => 6],
            ['width' => 12, 'break_before' => true],
        ],
    );

    preg_match('/([0-9.]+) ([0-9.]+) Td \\(First name\\)/', $pdf, $firstName);
    preg_match('/([0-9.]+) ([0-9.]+) Td \\(Last name\\)/', $pdf, $lastName);
    preg_match('/([0-9.]+) ([0-9.]+) Td \\(E-mail\\)/', $pdf, $email);

    expect($firstName)->not->toBeEmpty()
        ->and($lastName)->not->toBeEmpty()
        ->and($email)->not->toBeEmpty()
        ->and((float) $lastName[1])->toBeGreaterThan((float) $firstName[1])
        ->and((float) $lastName[2])->toBe((float) $firstName[2])
        ->and((float) $email[1])->toBe((float) $firstName[1])
        ->and((float) $email[2])->toBeLessThan((float) $firstName[2]);
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
