<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Enums\UserRole;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['profile', 'church'])->paginate(15);
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'password'   => 'required|string|min:8|confirmed',
            'birth_date' => 'nullable|date',
            'role'       => ['required', Rule::enum(UserRole::class)],
            'church_id'  => 'nullable|string|exists:churches,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $churchId = $validated['church_id'] ?? null;
        unset($validated['church_id']);

        $user = User::create($validated);
        $user->profile()->updateOrCreate(['user_id' => $user->id], ['church_id' => $churchId]);

        return response()->json($user, 201);
    }

    public function show(string $id)
    {
        $user = User::with(['profile', 'church'])->findOrFail($id);
        return response()->json($user);
    }

    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name'  => 'sometimes|required|string|max:255',
            'email'      => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password'   => 'nullable|string|min:8|confirmed',
            'birth_date' => 'nullable|date',
            'role'       => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'church_id'  => 'nullable|string|exists:churches,id',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $churchId = array_key_exists('church_id', $validated) ? $validated['church_id'] : null;
        unset($validated['church_id']);
        $user->update($validated);

        if (array_key_exists('church_id', $request->all())) {
            $user->profile()->updateOrCreate(['user_id' => $user->id], ['church_id' => $churchId]);
        }

        return response()->json($user);
    }

    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(null, 204);
    }
}
