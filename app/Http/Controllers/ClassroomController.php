<?php

namespace App\Http\Controllers;

use App\Enums\UserRelationships;
use App\Enums\UserRole;
use App\Http\Requests\Classroom\CheckInClassroomRequest;
use App\Http\Requests\Classroom\CheckOutClassroomRequest;
use App\Http\Requests\Classroom\StoreClassroomRequest;
use App\Http\Requests\Classroom\UpdateClassroomRequest;
use App\Models\Classroom;
use App\Models\ClassroomPresence;
use App\Models\User;
use App\Notifications\ChildCheckedInNotification;
use App\Notifications\ChildReleasedNotification;
use App\Support\ChurchDomainContext;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $churchId = app(ChurchDomainContext::class)->churchId() ?? $request->user()->church?->id;
        abort_unless($churchId, 422, __('church.membership_classroom_list_required'));
        $isForeignChurch = $request->user()->profile?->church_id !== $churchId;

        return response()->json(Classroom::query()
            ->where('church_id', $churchId)
            ->when($isForeignChurch, fn ($query) => $query->where('is_kids', true))
            ->with(['teacher:id,first_name,last_name'])
            ->when(! $isForeignChurch, fn ($query) => $query->with('members:id,first_name,last_name'))
            ->withCount(['members', 'presences as active_presences_count' => fn ($query) => $query->whereNull('check_out')])
            ->latest()
            ->paginate(15)
            ->through(fn (Classroom $classroom): Classroom => $classroom->localize()));
    }

    public function store(StoreClassroomRequest $request): JsonResponse
    {
        $church = $request->user()->church;
        abort_unless($church?->exists, 422, __('church.membership_classroom_create_required'));

        $classroom = $church->classrooms()->create($request->safe()->only([
            'name', 'description', 'min_age', 'max_age', 'gender_restriction', 'is_kids', 'max_members', 'teacher_id',
        ]));
        $this->syncMembers($classroom, $request->validated()['member_ids'] ?? [], $church->id);

        return response()->json($classroom->load(['teacher:id,first_name,last_name', 'members:id,first_name,last_name']), 201);
    }

    public function show(Request $request, Classroom $classroom): JsonResponse
    {
        $this->ensureChurchAccess($request, $classroom->church_id);

        $classroom->load([
            'teacher:id,first_name,last_name',
            'members:id,first_name,last_name',
            'presences' => fn ($query) => $query->with('user:id,first_name,last_name')->latest('check_in'),
        ])->localize();

        return response()->json($classroom);
    }

    public function update(UpdateClassroomRequest $request, Classroom $classroom): JsonResponse
    {
        $this->ensureChurchAccess($request, $classroom->church_id);
        $classroom->update($request->safe()->only([
            'name', 'description', 'min_age', 'max_age', 'gender_restriction', 'is_kids', 'max_members', 'teacher_id',
        ]));

        if ($request->has('member_ids')) {
            $this->syncMembers($classroom, $request->validated()['member_ids'], $classroom->church_id);
        }

        return response()->json($classroom->load(['teacher:id,first_name,last_name', 'members:id,first_name,last_name']));
    }

    public function destroy(Request $request, Classroom $classroom): JsonResponse
    {
        $this->ensureChurchAccess($request, $classroom->church_id);
        $classroom->delete();

        return response()->json(status: 204);
    }

    public function checkIn(CheckInClassroomRequest $request, Classroom $classroom): JsonResponse
    {
        $student = User::query()->with('profile')->findOrFail($request->validated('user_id'));
        $isVisitingGuardian = $this->isVisitingGuardian($request, $classroom, $student);
        $this->ensureClassroomAttendanceAccess($request, $classroom, $isVisitingGuardian);
        $this->ensureEligibleStudent($classroom, $student, requireMembership: ! $isVisitingGuardian);
        $handoff = $isVisitingGuardian
            ? $this->authenticatedGuardianHandoff($request, 'dropoff')
            : $this->validatedHandoff($request, $student, $classroom->is_kids);

        $alreadyCheckedIn = $classroom->presences()
            ->where('user_id', $student->id)
            ->whereNull('check_out')
            ->exists();

        if (! $alreadyCheckedIn && $classroom->max_members !== null && $classroom->presences()->whereNull('check_out')->count() >= $classroom->max_members) {
            throw ValidationException::withMessages(['user_id' => __('classroom.capacity_reached')]);
        }

        $presence = $classroom->presences()->firstOrCreate(
            ['user_id' => $student->id, 'check_out' => null],
            array_merge(['check_in' => now()], $handoff)
        );

        $checkoutPin = null;
        if ($classroom->is_kids && ! $presence->checkout_pin) {
            $checkoutPin = (string) random_int(100000, 999999);
            $presence->update([
                'checkout_pin' => Hash::make($checkoutPin),
                'checkout_pin_code' => $checkoutPin,
                'pin_generated_at' => now(),
            ]);
        }

        if ($classroom->is_kids && $presence->wasRecentlyCreated) {
            $this->notifyGuardiansAboutCheckIn($student, $classroom, $presence->check_in->toIso8601String());
        }

        return response()->json([
            'message' => __('checkin.checkin_success'),
            'presence' => $presence->fresh(),
            'checkout_pin' => $checkoutPin,
            'label_url' => $checkoutPin ? route('admin.classrooms.labels', $presence) : null,
        ]);
    }

    public function checkOut(CheckOutClassroomRequest $request, Classroom $classroom): JsonResponse
    {
        $student = User::query()->with('profile')->findOrFail($request->validated('user_id'));
        $isVisitingGuardian = $this->isVisitingGuardian($request, $classroom, $student);
        $this->ensureClassroomAttendanceAccess($request, $classroom, $isVisitingGuardian);
        $presence = $classroom->presences()
            ->where('user_id', $request->validated('user_id'))
            ->whereNull('check_out')
            ->latest('check_in')
            ->firstOrFail();
        $handoff = $isVisitingGuardian
            ? $this->authenticatedGuardianHandoff($request, 'pickup')
            : $this->validatedHandoff($request, $student, $classroom->is_kids, 'pickup');

        if ($classroom->is_kids && ! $presence->checkout_pin) {
            throw ValidationException::withMessages(['pin' => __('classroom.checkout_pin_required')]);
        }

        if ($classroom->is_kids && ! Hash::check((string) $request->validated('pin'), $presence->checkout_pin)) {
            throw ValidationException::withMessages(['pin' => __('classroom.checkout_pin_invalid')]);
        }

        $checkedOutAt = now();
        $presence->update(array_merge([
            'check_out' => $checkedOutAt,
            'checkout_pin_code' => null,
        ], $handoff));

        if ($classroom->is_kids && ! $request->validated('guardian_user_id')) {
            $this->notifyGuardiansAboutPickup($student, $classroom, $handoff, $checkedOutAt->toIso8601String());
        }

        return response()->json(['message' => __('checkin.checkout_success')]);
    }

    public function labels(Request $request, ClassroomPresence $presence): Response
    {
        $presence->load(['classroom:id,church_id,name,is_kids', 'user:id,first_name,last_name,birth_date', 'dropoffUser:id,first_name,last_name']);
        $this->ensureChurchAccess($request, $presence->classroom->church_id);
        abort_unless($presence->classroom->is_kids && $presence->checkout_pin_code, 404);
        $qrSvg = (new Writer(new ImageRenderer(
            new RendererStyle(320, 4),
            new SvgImageBackEnd,
        )))->writeString("NC-CHECKOUT:{$presence->checkout_pin_code}");

        return Inertia::render('Admin/ClassroomLabels', [
            'label' => [
                'presence_id' => $presence->id,
                'pin' => $presence->checkout_pin_code,
                'child_name' => $presence->user->name,
                'child_age' => $presence->user->birth_date?->age,
                'classroom_name' => $presence->classroom->name,
                'dropoff_name' => $presence->dropoffUser?->name ?? $presence->dropoff_name,
                'dropoff_phone' => $presence->dropoff_phone,
                'qr_data_url' => 'data:image/svg+xml;base64,'.base64_encode($qrSvg),
            ],
        ]);
    }

    /** @param array<int, string> $memberIds */
    private function syncMembers(Classroom $classroom, array $memberIds, string $churchId): void
    {
        $eligibleMemberIds = User::query()
            ->whereHas('profile', fn ($query) => $query->where('church_id', $churchId))
            ->whereIn('id', $memberIds)
            ->pluck('id');

        $classroom->members()->sync($eligibleMemberIds);
    }

    private function ensureEligibleStudent(Classroom $classroom, User $student, bool $requireMembership = true): void
    {
        if ($requireMembership && ! $classroom->members()->whereKey($student->id)->exists()) {
            throw ValidationException::withMessages(['user_id' => __('classroom.student_membership_required')]);
        }

        $age = $student->birth_date?->age;
        if (($classroom->min_age !== null && ($age === null || $age < $classroom->min_age))
            || ($classroom->max_age !== null && ($age === null || $age > $classroom->max_age))) {
            throw ValidationException::withMessages(['user_id' => __('classroom.student_age_mismatch')]);
        }

        if ($classroom->gender_restriction && $student->profile?->gender !== $classroom->gender_restriction) {
            throw ValidationException::withMessages(['user_id' => __('classroom.student_gender_mismatch')]);
        }
    }

    private function isVisitingGuardian(Request $request, Classroom $classroom, User $student): bool
    {
        return $classroom->is_kids
            && $request->user()->profile?->church_id !== $classroom->church_id
            && $this->guardiansFor($student)->contains('id', $request->user()->id);
    }

    private function ensureClassroomAttendanceAccess(Request $request, Classroom $classroom, bool $isVisitingGuardian): void
    {
        if ($isVisitingGuardian) {
            return;
        }

        $this->ensureChurchAccess($request, $classroom->church_id);
        abort_unless(in_array($request->user()->role, [
            UserRole::LEADER,
            UserRole::MEDIA,
            UserRole::CHURCH_LEADER,
            UserRole::SUPERADMIN,
            UserRole::SYSTEM,
        ], true), 403);
    }

    /** @return array<string, string|null> */
    private function authenticatedGuardianHandoff(Request $request, string $prefix): array
    {
        return [
            "{$prefix}_user_id" => $request->user()->id,
            "{$prefix}_name" => $request->user()->name,
            "{$prefix}_phone" => $request->user()->profile?->phone,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function validatedHandoff(Request $request, User $student, bool $required, string $prefix = 'dropoff'): array
    {
        $guardianId = $request->input('guardian_user_id');
        $name = trim((string) $request->input('handoff_name'));
        $phone = trim((string) $request->input('handoff_phone'));

        if (! $required) {
            return [];
        }

        if ($guardianId) {
            $guardian = $this->guardiansFor($student)->firstWhere('id', $guardianId);
            if (! $guardian) {
                throw ValidationException::withMessages(['guardian_user_id' => __('classroom.guardian_relationship_required')]);
            }

            return [
                "{$prefix}_user_id" => $guardian->id,
                "{$prefix}_name" => $guardian->name,
                "{$prefix}_phone" => $guardian->profile?->phone,
            ];
        }

        if ($name === '' || $phone === '') {
            throw ValidationException::withMessages([
                'handoff_name' => __('classroom.handoff_contact_required'),
            ]);
        }

        return [
            "{$prefix}_user_id" => null,
            "{$prefix}_name" => $name,
            "{$prefix}_phone" => $phone,
        ];
    }

    /** @return Collection<int, User> */
    private function guardiansFor(User $student): Collection
    {
        $student->loadMissing([
            'relationships.relatedUser.profile',
            'relatedRelationships.user.profile',
        ]);

        return collect()
            ->merge($student->relationships
                ->where('relationship_type', UserRelationships::CHILD)
                ->pluck('relatedUser'))
            ->merge($student->relatedRelationships
                ->where('relationship_type', UserRelationships::PARENT)
                ->pluck('user'))
            ->filter()
            ->unique('id')
            ->values();
    }

    /** @param array<string, string|null> $handoff */
    private function notifyGuardiansAboutPickup(User $student, Classroom $classroom, array $handoff, string $checkedOutAt): void
    {
        $pickupName = (string) ($handoff['pickup_name'] ?? '');
        $pickupPhone = $handoff['pickup_phone'] ?? null;

        $this->guardiansFor($student)->each(function (User $guardian) use ($student, $classroom, $pickupName, $pickupPhone, $checkedOutAt): void {
            $guardian->notify((new ChildReleasedNotification(
                childName: $student->name,
                classroomName: $classroom->name,
                pickupName: $pickupName,
                pickupPhone: $pickupPhone,
                checkedOutAt: $checkedOutAt,
            ))->afterCommit());
        });
    }

    private function notifyGuardiansAboutCheckIn(User $student, Classroom $classroom, string $checkedInAt): void
    {
        $this->guardiansFor($student)->each(function (User $guardian) use ($student, $classroom, $checkedInAt): void {
            $guardian->notify((new ChildCheckedInNotification(
                childName: $student->name,
                classroomName: $classroom->name,
                checkedInAt: $checkedInAt,
            ))->afterCommit());
        });
    }
}
