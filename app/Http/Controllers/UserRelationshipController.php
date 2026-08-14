<?php

namespace App\Http\Controllers;

use App\Enums\UserRelationships;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserRelationshipController extends Controller
{
    public function storeChild(Request $request): JsonResponse
    {
        $guardian = $request->user()->load('profile');
        abort_unless($guardian->profile?->church_id, 422, 'Select a church before adding a child.');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'medical_notes' => ['nullable', 'string', 'max:3000'],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ]);

        $child = DB::transaction(function () use ($request, $guardian, $validated): User {
            $child = User::query()->create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => 'family.'.Str::lower((string) Str::ulid()).'@nossacasa.invalid',
                'password' => Hash::make(Str::random(48)),
                'birth_date' => $validated['birth_date'],
                'role' => UserRole::MEMBER,
            ]);
            $avatarPath = $request->hasFile('avatar')
                ? $request->file('avatar')->store("users/{$child->id}/avatar", (string) config('media.disk'))
                : null;
            $child->profile()->create([
                'church_id' => $guardian->profile->church_id,
                'community_id' => $guardian->profile->community_id,
                'gender' => $validated['gender'] ?? null,
                'medical_notes' => $validated['medical_notes'] ?? null,
                'avatar_path' => $avatarPath,
            ]);

            UserRelationship::query()->create([
                'user_id' => $guardian->id,
                'related_user_id' => $child->id,
                'relationship_type' => UserRelationships::PARENT->value,
            ]);
            UserRelationship::query()->create([
                'user_id' => $child->id,
                'related_user_id' => $guardian->id,
                'relationship_type' => UserRelationships::CHILD->value,
            ]);

            return $child;
        });

        return response()->json($child->load('profile'), 201);
    }

    public function store(Request $request, string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $this->ensureOwnerOrModerator($request, $user->id);
        $churchId = $user->church?->id;
        abort_unless($churchId, 422, 'The selected user must belong to a church.');
        $this->ensureChurchAccess($request, $churchId);

        $validated = $request->validate([
            'related_user_id' => 'required|exists:users,id',
            'relationship_type' => ['required', Rule::enum(UserRelationships::class)],
        ]);
        $relatedUser = User::query()->findOrFail($validated['related_user_id']);

        abort_if($user->id === $relatedUser->id, 422, 'A user cannot be related to themselves.');
        abort_unless($relatedUser->church?->id === $churchId, 422, 'Both users must belong to the same church.');

        $relationship = UserRelationship::updateOrCreate(
            [
                'user_id' => $user->id,
                'related_user_id' => $validated['related_user_id'],
            ],
            [
                'relationship_type' => $validated['relationship_type'],
            ]
        );

        $inverseType = match ($validated['relationship_type']) {
            UserRelationships::PARENT->value => UserRelationships::CHILD->value,
            UserRelationships::CHILD->value => UserRelationships::PARENT->value,
            default => $validated['relationship_type'],
        };

        UserRelationship::updateOrCreate(
            ['user_id' => $relatedUser->id, 'related_user_id' => $user->id],
            ['relationship_type' => $inverseType],
        );

        return response()->json($relationship, 201);
    }

    public function destroy(Request $request, UserRelationship $relationship): JsonResponse
    {
        $this->ensureOwnerOrModerator($request, $relationship->user_id);
        $this->ensureChurchAccess($request, $relationship->user->church?->id ?? '');

        UserRelationship::query()
            ->where('user_id', $relationship->related_user_id)
            ->where('related_user_id', $relationship->user_id)
            ->delete();
        $relationship->delete();

        return response()->json([], 204);
    }
}
