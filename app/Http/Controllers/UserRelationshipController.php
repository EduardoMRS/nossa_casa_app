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
    public function storeFamilyMember(Request $request): JsonResponse
    {
        $guardian = $request->user()->load('profile');
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'medical_notes' => ['nullable', 'string', 'max:3000'],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'relationship_type' => [
                'nullable',
                Rule::in([
                    UserRelationships::SPOUSE->value,
                    UserRelationships::PARENT->value,
                    UserRelationships::CHILD->value,
                ]),
            ],
        ]);
        $relationshipType = $validated['relationship_type'] ?? UserRelationships::PARENT->value;
        $guardianProfile = $guardian->profile;

        $relative = DB::transaction(function () use ($request, $guardian, $guardianProfile, $validated, $relationshipType): User {
            $relative = User::query()->create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => 'family.'.Str::lower((string) Str::ulid()).'@nossacasa.invalid',
                'password' => Hash::make(Str::random(48)),
                'birth_date' => $validated['birth_date'] ?? null,
                'role' => UserRole::MEMBER,
            ]);
            $avatarPath = $request->hasFile('avatar')
                ? $request->file('avatar')->store("users/{$relative->id}/avatar", (string) config('media.disk'))
                : null;
            $relative->profile()->create([
                'church_id' => $guardianProfile?->church_id,
                'community_id' => $guardianProfile?->community_id,
                'gender' => $validated['gender'] ?? null,
                'medical_notes' => $validated['medical_notes'] ?? null,
                'avatar_path' => $avatarPath,
            ]);

            UserRelationship::query()->create([
                'user_id' => $guardian->id,
                'related_user_id' => $relative->id,
                'relationship_type' => $relationshipType,
            ]);
            UserRelationship::query()->create([
                'user_id' => $relative->id,
                'related_user_id' => $guardian->id,
                'relationship_type' => $this->inverseType($relationshipType),
            ]);

            return $relative;
        });

        return response()->json($relative->load('profile'), 201);
    }

    public function storeChild(Request $request): JsonResponse
    {
        return $this->storeFamilyMember($request);
    }

    public function store(Request $request, string $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        $this->ensureOwnerOrModerator($request, $user->id);
        $isOwner = $request->user()->is($user);
        $churchId = $user->church?->id;

        if (! $isOwner) {
            abort_unless($churchId, 422, __('user.selected_user_church_required'));
            $this->ensureChurchAccess($request, $churchId);
        }

        $validated = $request->validate([
            'related_user_id' => [
                'nullable',
                'required_without:related_user_email',
                Rule::exists('users', 'id'),
            ],
            'related_user_email' => [
                'nullable',
                'required_without:related_user_id',
                'email:rfc',
                'max:255',
                Rule::exists('users', 'email'),
            ],
            'relationship_type' => ['required', Rule::enum(UserRelationships::class)],
        ]);
        $relatedUser = isset($validated['related_user_id'])
            ? User::query()->findOrFail($validated['related_user_id'])
            : User::query()->where('email', Str::lower(trim($validated['related_user_email'])))->firstOrFail();

        abort_if($user->is($relatedUser), 422, __('user.relationship_self_forbidden'));

        if (! $isOwner) {
            abort_unless($relatedUser->church?->id === $churchId, 422, __('user.relationship_same_church_required'));
        }

        $relationship = UserRelationship::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'related_user_id' => $relatedUser->id,
            ],
            [
                'relationship_type' => $validated['relationship_type'],
            ],
        );

        UserRelationship::query()->updateOrCreate(
            ['user_id' => $relatedUser->id, 'related_user_id' => $user->id],
            ['relationship_type' => $this->inverseType($validated['relationship_type'])],
        );

        return response()->json($relationship, 201);
    }

    public function destroy(Request $request, UserRelationship $relationship): JsonResponse
    {
        $this->ensureOwnerOrModerator($request, $relationship->user_id);

        if ($request->user()->id !== $relationship->user_id) {
            $this->ensureChurchAccess($request, $relationship->user->church?->id ?? '');
        }

        UserRelationship::query()
            ->where('user_id', $relationship->related_user_id)
            ->where('related_user_id', $relationship->user_id)
            ->delete();
        $relationship->delete();

        return response()->json([], 204);
    }

    private function inverseType(string $relationshipType): string
    {
        return match ($relationshipType) {
            UserRelationships::PARENT->value => UserRelationships::CHILD->value,
            UserRelationships::CHILD->value => UserRelationships::PARENT->value,
            default => $relationshipType,
        };
    }
}
