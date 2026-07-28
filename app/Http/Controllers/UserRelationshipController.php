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

        $validated = $request->validate([
            'related_user_id' => 'required|exists:users,id',
            'relationship_type' => ['required', Rule::enum(UserRelationships::class)],
        ]);

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
