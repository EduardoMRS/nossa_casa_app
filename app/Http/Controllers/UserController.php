<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ChurchAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /** @var array<int, UserRole> */
    private const USER_MANAGER_ROLES = [UserRole::CHURCH_LEADER];

    public function index(Request $request)
    {
        $user = $request->user();

        $users = User::with(['profile', 'church'])
            ->when(
                ! ChurchAccess::isSystem($user),
                fn ($query) => $query->whereHas(
                    'profile',
                    fn ($profile) => $profile->whereIn('church_id', $this->churchAccess()->scopeChurchIds($user)),
                ),
            )
            ->paginate(15);
        $users->getCollection()->each(fn (User $user) => $user->church?->localize());

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $this->ensureRoleCanBeAssigned($request, (string) $request->input('role'));

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'birth_date' => 'nullable|date',
            'role' => ['required', Rule::enum(UserRole::class)],
            'church_id' => 'nullable|string|exists:churches,id',
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'location_lang' => ['nullable', 'string', 'max:10'],
        ]);

        // A user is always created inside a church the actor manages.
        $validated['church_id'] ??= ChurchAccess::homeChurchId($request->user());
        $this->ensureCanManageChurchUsers($request, $validated['church_id']);

        $validated['password'] = Hash::make($validated['password']);
        $profile = collect($validated)->only(['church_id', 'phone', 'gender', 'location_lang'])->all();
        unset($validated['church_id'], $validated['phone'], $validated['gender'], $validated['location_lang']);

        $user = User::create($validated);
        $user->profile()->updateOrCreate(['user_id' => $user->id], $profile);

        return response()->json($user->load(['profile', 'church']), 201);
    }

    public function show(Request $request, string $id)
    {
        $user = User::with(['profile', 'church'])->findOrFail($id);
        $this->ensureCanManage($request, $user);
        $user->church?->localize();

        return response()->json($user);
    }

    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $this->ensureCanManage($request, $user);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'birth_date' => 'nullable|date',
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'church_id' => 'nullable|string|exists:churches,id',
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'location_lang' => ['nullable', 'string', 'max:10'],
        ]);

        if (isset($validated['role'])) {
            $this->ensureRoleCanBeAssigned($request, $validated['role']);
        }

        // Moving a user is only allowed into another church the actor manages.
        if (array_key_exists('church_id', $validated) && $validated['church_id'] !== null) {
            $this->ensureCanManageChurchUsers($request, $validated['church_id']);
        }

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $profileKeys = ['church_id', 'phone', 'gender', 'location_lang'];
        $profile = collect($validated)->only($profileKeys)->all();
        foreach ($profileKeys as $profileKey) {
            unset($validated[$profileKey]);
        }
        $user->update($validated);

        if ($profile !== []) {
            $user->profile()->updateOrCreate(['user_id' => $user->id], $profile);
        }

        return response()->json($user->load(['profile', 'church']));
    }

    public function destroy(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $this->ensureCanManage($request, $user);
        abort_if(in_array($user->role, [UserRole::SYSTEM, UserRole::SUPERADMIN], true), 403, __('user.protected_delete'));
        $user->delete();

        return response()->json(null, 204);
    }

    /**
     * SYSTEM manages everyone. Church leadership manages users of its church
     * and descendant churches, never SYSTEM/SUPERADMIN accounts nor itself
     * through these endpoints.
     */
    private function ensureCanManage(Request $request, User $user): void
    {
        $actor = $request->user();

        if (ChurchAccess::isSystem($actor)) {
            return;
        }

        abort_if(in_array($user->role, [UserRole::SYSTEM, UserRole::SUPERADMIN], true), 403);
        $this->ensureCanManageChurchUsers($request, ChurchAccess::homeChurchId($user));
    }

    private function ensureCanManageChurchUsers(Request $request, ?string $churchId): void
    {
        abort_unless($this->churchAccess()->canManageChurch($request->user(), $churchId, self::USER_MANAGER_ROLES), 403);
    }

    /**
     * Only SYSTEM grants SYSTEM or SUPERADMIN; nobody else may escalate.
     */
    private function ensureRoleCanBeAssigned(Request $request, string $role): void
    {
        $isSystem = ChurchAccess::isSystem($request->user());

        abort_if(
            in_array($role, [UserRole::SYSTEM->value, UserRole::SUPERADMIN->value], true) && ! $isSystem,
            403,
        );
    }
}
