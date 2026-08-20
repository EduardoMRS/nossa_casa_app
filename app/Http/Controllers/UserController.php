<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['profile', 'church'])->paginate(15);
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

        $validated['password'] = Hash::make($validated['password']);
        $profile = collect($validated)->only(['church_id', 'phone', 'gender', 'location_lang'])->all();
        unset($validated['church_id'], $validated['phone'], $validated['gender'], $validated['location_lang']);

        $user = User::create($validated);
        $user->profile()->updateOrCreate(['user_id' => $user->id], $profile);

        return response()->json($user->load(['profile', 'church']), 201);
    }

    public function show(string $id)
    {
        $user = User::with(['profile', 'church'])->findOrFail($id);
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

    private function ensureCanManage(Request $request, User $user): void
    {
        $actor = $request->user();
        $actorRole = $actor?->role?->value ?? (string) $actor?->role;

        if ($actorRole === UserRole::CHURCH_LEADER->value) {
            abort_unless($user->church?->id === $actor?->church?->id, 403);
            abort_if(in_array($user->role, [UserRole::SYSTEM, UserRole::SUPERADMIN], true), 403);
        }

        if ($actorRole === UserRole::SUPERADMIN->value) {
            abort_if($user->role === UserRole::SYSTEM, 403);
        }
    }

    private function ensureRoleCanBeAssigned(Request $request, string $role): void
    {
        $actorRole = $request->user()?->role?->value ?? (string) $request->user()?->role;
        abort_if($role === UserRole::SYSTEM->value && $actorRole !== UserRole::SYSTEM->value, 403);
        abort_if($role === UserRole::SUPERADMIN->value && $actorRole === UserRole::CHURCH_LEADER->value, 403);
    }
}
