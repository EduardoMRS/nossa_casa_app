<?php

namespace App\Http\Controllers;

use App\Http\Requests\Classroom\CheckInClassroomRequest;
use App\Http\Requests\Classroom\CheckOutClassroomRequest;
use App\Http\Requests\Classroom\StoreClassroomRequest;
use App\Http\Requests\Classroom\UpdateClassroomRequest;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ClassroomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $churchId = $request->user()->church?->id;
        abort_unless($churchId, 422, 'A church membership is required to list classrooms.');

        return response()->json(Classroom::query()
            ->where('church_id', $churchId)
            ->with(['teacher:id,first_name,last_name', 'members:id,first_name,last_name'])
            ->withCount(['members', 'presences as active_presences_count' => fn ($query) => $query->whereNull('check_out')])
            ->latest()
            ->paginate(15)
            ->through(fn (Classroom $classroom): Classroom => $classroom->localize()));
    }

    public function store(StoreClassroomRequest $request): JsonResponse
    {
        $church = $request->user()->church;
        abort_unless($church?->exists, 422, 'A church membership is required to create classrooms.');

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
        $this->ensureChurchAccess($request, $classroom->church_id);
        $student = User::query()->with('profile')->findOrFail($request->validated('user_id'));
        $this->ensureEligibleStudent($classroom, $student);

        $alreadyCheckedIn = $classroom->presences()
            ->where('user_id', $student->id)
            ->whereNull('check_out')
            ->exists();

        if (! $alreadyCheckedIn && $classroom->max_members !== null && $classroom->presences()->whereNull('check_out')->count() >= $classroom->max_members) {
            throw ValidationException::withMessages(['user_id' => 'A sala já atingiu o limite de alunos presentes.']);
        }

        $presence = $classroom->presences()->firstOrCreate(
            ['user_id' => $student->id, 'check_out' => null],
            ['check_in' => now()]
        );

        $checkoutPin = null;
        if ($classroom->is_kids && ! $presence->checkout_pin) {
            $checkoutPin = (string) random_int(100000, 999999);
            $presence->update([
                'checkout_pin' => Hash::make($checkoutPin),
                'pin_generated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => __('checkin.checkin_success'),
            'presence' => $presence->fresh(),
            'checkout_pin' => $checkoutPin,
        ]);
    }

    public function checkOut(CheckOutClassroomRequest $request, Classroom $classroom): JsonResponse
    {
        $this->ensureChurchAccess($request, $classroom->church_id);
        $presence = $classroom->presences()
            ->where('user_id', $request->validated('user_id'))
            ->whereNull('check_out')
            ->latest('check_in')
            ->firstOrFail();

        if ($classroom->is_kids && ! $presence->checkout_pin) {
            throw ValidationException::withMessages(['pin' => 'A checkout PIN is required for Kids classrooms.']);
        }

        if ($classroom->is_kids && ! Hash::check((string) $request->validated('pin'), $presence->checkout_pin)) {
            throw ValidationException::withMessages(['pin' => 'O PIN de retirada informado não confere.']);
        }

        $presence->update(['check_out' => now()]);

        return response()->json(['message' => __('checkin.checkout_success')]);
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

    private function ensureEligibleStudent(Classroom $classroom, User $student): void
    {
        if (! $classroom->members()->whereKey($student->id)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'O aluno não pertence a esta sala.']);
        }

        $age = $student->birth_date?->age;
        if (($classroom->min_age !== null && ($age === null || $age < $classroom->min_age))
            || ($classroom->max_age !== null && ($age === null || $age > $classroom->max_age))) {
            throw ValidationException::withMessages(['user_id' => 'A idade do aluno não é compatível com esta sala.']);
        }

        if ($classroom->gender_restriction && $student->profile?->gender !== $classroom->gender_restriction) {
            throw ValidationException::withMessages(['user_id' => 'O gênero do aluno não é compatível com esta sala.']);
        }
    }
}
