<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRelationship;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\UserRelationships;

class UserRelationshipController extends Controller
{
    public function store(Request $request, string $userId)
    {
        $user = User::findOrFail($userId);
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

        return response()->json($relationship, 201);
    }
}
