<?php

// app/Http/Controllers/CommunityController.php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Community;
use App\Traits\UploadsMedia;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule; // Importando a trait

class CommunityController extends Controller
{
    use UploadsMedia; // Usando a trait

    public function index()
    {
        $communities = Community::with('churches')
            ->paginate(15)
            ->through(fn (Community $community): Community => $community->localize(relations: ['churches']));

        return response()->json($communities);
    }

    public function store(Request $request)
    {
        $this->ensureSystemAccess($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:communities',
            'description' => 'nullable|string',
            'found_date' => 'nullable|date',
            'logo_path' => 'sometimes|nullable', // Aceita arquivo, null ou string
        ]);

        if (array_key_exists('logo_path', $validated)) {
            $file = $request->file('logo_path') ?? $request->input('logo_path');
            $validated['logo_path'] = $this->handleMediaUpload($file, 'communities/logos');
        }

        $community = Community::create($validated);

        return response()->json($community, 201);
    }

    public function show(string $id)
    {
        $community = Community::with(['churches', 'members'])->findOrFail($id);

        $community->localize(relations: ['churches']);

        return response()->json($community);
    }

    public function update(Request $request, string $id)
    {
        $this->ensureSystemAccess($request);
        $community = Community::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('communities')->ignore($community->id)],
            'description' => 'nullable|string',
            'found_date' => 'nullable|date',
            'logo_path' => 'sometimes|nullable',
        ]);

        if (array_key_exists('logo_path', $validated)) {
            $file = $request->file('logo_path') ?? $request->input('logo_path');
            $validated['logo_path'] = $this->handleMediaUpload($file, 'communities/logos', $community->logo_path);
        }

        $community->update($validated);

        return response()->json($community);
    }

    public function destroy(Request $request, string $id)
    {
        $this->ensureSystemAccess($request);
        $community = Community::findOrFail($id);
        $community->delete();

        return response()->json(null, 204);
    }

    private function ensureSystemAccess(Request $request): void
    {
        abort_unless($request->user()?->role === UserRole::SYSTEM, 403);
    }
}
