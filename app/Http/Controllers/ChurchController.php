<?php

namespace App\Http\Controllers;

use App\Models\Church;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\ChurchStatus;

class ChurchController extends Controller
{
    public function index()
    {
        $churches = Church::with('community')->paginate(15);
        return response()->json($churches);
    }

    public function show(string $slug)
    {
        $church = Church::with(['community', 'address', 'settings', 'categories'])->where('slug', $slug)->firstOrFail();
        return response()->json($church);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'slug'         => 'required|string|max:255|unique:churches',
            'address_id'   => 'nullable|string|exists:addresses,id',
            'status'       => ['required', Rule::enum(ChurchStatus::class)],
            'found_date'   => 'nullable|date',
            'community_id' => 'nullable|string|exists:communities,id',
            'founder_id'   => 'nullable|string|exists:users,id',
        ]);

        $church = Church::create($validated);

        return response()->json($church, 201);
    }

    public function update(Request $request, string $id)
    {
        $church = Church::findOrFail($id);

        $validated = $request->validate([
            'name'         => 'sometimes|required|string|max:255',
            'slug'         => ['sometimes', 'required', 'string', 'max:255', Rule::unique('churches')->ignore($church->id)],
            'address_id'   => 'nullable|string|exists:addresses,id',
            'status'       => ['sometimes', 'required', Rule::enum(ChurchStatus::class)],
            'found_date'   => 'nullable|date',
            'community_id' => 'nullable|string|exists:communities,id',
            'founder_id'   => 'nullable|string|exists:users,id',
        ]);

        $church->update($validated);

        return response()->json($church);
    }

    public function destroy(string $id)
    {
        $church = Church::findOrFail($id);
        $church->delete();

        return response()->json(null, 204);
    }
}
