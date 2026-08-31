<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRegistrationRequest;
use App\Http\Requests\Event\UpdateEventRegistrationRequest;
use App\Models\Event;
use App\Models\EventUser;
use App\Models\Form;
use App\Models\FormResponse;
use App\Support\EventRegistrationExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventRegistrationController extends Controller
{
    public function __construct(private EventRegistrationExporter $exporter) {}

    public function show(Request $request, Event $event): Response
    {
        $this->ensureAccess($request, $event);
        $event->load('church:id,name');
        $form = $event->forms()->first();

        return Inertia::render('Admin/EventRegistrations', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'church' => $event->church,
            ],
            'columns' => $this->columns($form),
            'registrations' => $this->registrations($event, $form),
            'formFields' => $this->formFields($form),
            'statuses' => ['pending', 'approved', 'rejected', 'canceled', 'confirmed'],
        ]);
    }

    public function store(StoreEventRegistrationRequest $request, Event $event): JsonResponse
    {
        $this->ensureAccess($request, $event);
        $validated = $request->validated();
        $registration = $event->registrations()->create([
            ...collect($validated)->except('answers')->all(),
            'status' => $validated['status'] ?? 'pending',
            'answers' => $validated['answers'] ?? [],
        ]);

        return response()->json($registration, 201);
    }

    public function update(
        UpdateEventRegistrationRequest $request,
        Event $event,
        EventUser $registration,
    ): JsonResponse {
        $this->ensureAccess($request, $event);
        abort_unless($registration->event_id === $event->id, 404);
        $validated = $request->validated();
        $answers = $validated['answers'] ?? [];

        if ($registration->user_id) {
            $form = $event->forms()->first();

            if ($form) {
                FormResponse::query()->updateOrCreate(
                    ['form_id' => $form->id, 'user_id' => $registration->user_id],
                    ['answers' => $answers],
                );
            }

            $registration->update(['status' => $validated['status']]);
        } else {
            $registration->update($validated);
        }

        return response()->json($registration->refresh());
    }

    public function export(Request $request, Event $event, string $format): HttpResponse
    {
        $this->ensureAccess($request, $event);
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $event->loadMissing('church.settings');
        $form = $event->forms()->first();
        $columns = $this->selectedColumns($request, $form);
        $registrations = $this->registrations($event, $form);
        $headers = $columns->pluck('label')->all();
        $rows = $registrations->map(fn (array $registration): array => $columns
            ->map(fn (array $column): string => $this->displayValue(
                data_get($registration, $column['key']),
                $column['key'],
            ))
            ->all())->all();
        $contents = $format === 'xlsx'
            ? $this->exporter->xlsx($headers, $rows)
            : $this->exporter->pdf(
                $event->title,
                $headers,
                $rows,
                __('admin.event_registrations.export_generated_at', ['date' => now()->format('d/m/Y H:i')]),
                $this->pdfBranding($event),
                $registrations->pluck('name')->map(fn (mixed $name): string => (string) $name)->all(),
            );
        $filename = Str::slug($event->title).'-'.__('admin.event_registrations.filename').'.'.$format;

        return response($contents, 200, [
            'Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function individualPdf(Request $request, Event $event, EventUser $registration): HttpResponse
    {
        $this->ensureAccess($request, $event);
        abort_unless($registration->event_id === $event->id, 404);
        $event->loadMissing('church.settings');
        $form = $event->forms()->first();
        $item = $this->registrations($event, $form)->firstWhere('id', $registration->id);
        abort_unless($item !== null, 404);
        $columns = collect($this->columns($form));
        $headers = $columns->pluck('label')->all();
        $row = $columns->map(fn (array $column): string => $this->displayValue(
            data_get($item, $column['key']),
            $column['key'],
        ))->all();
        $contents = $this->exporter->pdf(
            $event->title,
            $headers,
            [$row],
            __('admin.event_registrations.individual_export'),
            $this->pdfBranding($event),
        );

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug((string) $item['name']).'.pdf"',
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function registrations(Event $event, ?Form $form): Collection
    {
        $registrations = $event->registrations()->with('user.profile')->latest()->get();
        $responses = $form
            ? FormResponse::query()
                ->where('form_id', $form->id)
                ->whereIn('user_id', $registrations->pluck('user_id')->filter())
                ->get()
                ->keyBy('user_id')
            : collect();

        return $registrations->map(function (EventUser $registration) use ($responses): array {
            $answers = $registration->user_id
                ? ($responses->get($registration->user_id)?->answers ?? [])
                : ($registration->answers ?? []);
            $firstName = $registration->user?->first_name ?? $registration->first_name;
            $lastName = $registration->user?->last_name ?? $registration->last_name;

            return [
                'id' => $registration->id,
                'user_id' => $registration->user_id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => trim("{$firstName} {$lastName}"),
                'email' => $registration->user?->email ?? $registration->email,
                'phone' => $registration->user?->profile?->phone ?? $registration->phone,
                'status' => $registration->status,
                'registered_at' => $registration->created_at,
                'answers' => $answers,
            ];
        });
    }

    /** @return list<array{key: string, label: string}> */
    private function columns(?Form $form): array
    {
        return [
            ['key' => 'name', 'label' => __('admin.event_registrations.columns.name')],
            ['key' => 'email', 'label' => __('admin.event_registrations.columns.email')],
            ['key' => 'phone', 'label' => __('admin.event_registrations.columns.phone')],
            ['key' => 'status', 'label' => __('admin.event_registrations.columns.status')],
            ['key' => 'registered_at', 'label' => __('admin.event_registrations.columns.registered_at')],
            ...$this->formFields($form)->map(fn (array $field): array => [
                'key' => 'answers.'.$field['key'],
                'label' => $field['label'],
            ])->all(),
        ];
    }

    /** @return Collection<int, array{key: string, label: string, type: string}> */
    private function formFields(?Form $form): Collection
    {
        $schema = $form?->schema ?? [];
        $fields = isset($schema['fields']) && is_array($schema['fields']) ? $schema['fields'] : $schema;

        return collect($fields)
            ->filter(fn (mixed $field): bool => is_array($field))
            ->map(fn (array $field): array => [
                'key' => (string) ($field['name'] ?? $field['key'] ?? $field['id'] ?? ''),
                'label' => (string) ($field['label'] ?? $field['name'] ?? $field['key'] ?? ''),
                'type' => (string) ($field['type'] ?? 'text'),
            ])
            ->filter(fn (array $field): bool => $field['key'] !== '')
            ->values();
    }

    /** @return Collection<int, array{key: string, label: string}> */
    private function selectedColumns(Request $request, ?Form $form): Collection
    {
        $available = collect($this->columns($form));
        $selected = collect($request->array('columns'))->filter(fn (mixed $key): bool => is_string($key));

        return $selected->isEmpty()
            ? $available
            : $available->filter(fn (array $column): bool => $selected->contains($column['key']))->values();
    }

    private function displayValue(mixed $value, string $key = ''): string
    {
        if ($key === 'status' && filled($value)) {
            return __('admin.event_registrations.statuses.'.(string) $value);
        }

        if ($key === 'registered_at' && $value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }

        if (is_bool($value)) {
            return $value ? __('common.yes') : __('common.no');
        }

        if (is_array($value)) {
            return collect($value)->map(fn (mixed $item): string => (string) $item)->implode(', ');
        }

        return (string) ($value ?? '');
    }

    /** @return array<string, string> */
    private function pdfBranding(Event $event): array
    {
        $branding = data_get($event->church?->settings?->options, 'branding', []);

        if (! is_array($branding)) {
            $branding = [];
        }

        return [
            'name' => filled($branding['brand_name'] ?? null)
                ? (string) $branding['brand_name']
                : (string) ($event->church?->name ?? config('app.name')),
            'tagline' => (string) ($branding['tagline'] ?? ''),
            'primary_color' => filled($branding['primary_color'] ?? null)
                ? (string) $branding['primary_color']
                : '#342f87',
            'accent_color' => filled($branding['accent_color'] ?? null)
                ? (string) $branding['accent_color']
                : '#5eead4',
            'participant_label' => __('admin.event_registrations.pdf.participant'),
            'project_reference' => __('admin.event_registrations.pdf.project_reference'),
        ];
    }

    private function ensureAccess(Request $request, Event $event): void
    {
        $user = $request->user();
        $isChurchLeader = $user?->role === UserRole::CHURCH_LEADER
            && $user->profile?->church_id === $event->church_id;

        abort_unless(
            in_array($user?->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)
                || $isChurchLeader
                || $event->author_id === $user?->id
                || $event->responsibleUsers()->whereKey($user?->id)->exists(),
            403,
        );
    }
}
