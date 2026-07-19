<?php

namespace App\Http\Controllers;

use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommunityController extends Controller
{
    public function index()
    {
        $communities = Community::with('churches')->paginate(15);
        return response()->json($communities);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'required|string|max:255|unique:communities',
            'description' => 'nullable|string',
            'found_date'  => 'nullable|date',
            'logo_path'   => 'nullable|string|max:255',
        ]);

        $community = Community::create($validated);

        return response()->json($community, 201);
    }

    public function show(string $id)
    {
        $community = Community::with(['churches', 'members'])->findOrFail($id);
        return response()->json($community);
    }

    public function update(Request $request, string $id)
    {
        $community = Community::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'slug'        => ['sometimes', 'required', 'string', 'max:255', Rule::unique('communities')->ignore($community->id)],
            'description' => 'nullable|string',
            'found_date'  => 'nullable|date',
            'logo_path'   => 'nullable|string|max:255',
        ]);

        $community->update($validated);

        return response()->json($community);
    }

    public function destroy(string $id)
    {
        $community = Community::findOrFail($id);
        $community->delete();

        return response()->json(null, 204);
    }
}
