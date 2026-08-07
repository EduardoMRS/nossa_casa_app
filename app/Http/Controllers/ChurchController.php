<?php

namespace App\Http\Controllers;

use App\Enums\ChurchStatus;
use App\Enums\UserRole;
use App\Models\Church;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChurchController extends Controller
{
    public function index()
    {
        $churches = Church::with('community')
            ->paginate(15)
            ->through(fn (Church $church): Church => $church->localize(relations: ['community']));

        return response()->json($churches);
    }

    public function show(string $slug)
    {
        $church = Church::with(['community', 'address', 'settings', 'categories'])->where('slug', $slug)->firstOrFail();

        $church->localize(relations: ['community', 'categories']);

        return response()->json($church);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:churches',
            'address_id' => 'nullable|string|exists:addresses,id',
            'status' => ['required', Rule::enum(ChurchStatus::class)],
            'found_date' => 'nullable|date',
            'community_id' => 'nullable|string|exists:communities,id',
            'founder_id' => 'nullable|string|exists:users,id',
        ]);

        if ($request->user()->role !== UserRole::SYSTEM) {
            $validated['community_id'] = $this->managedCommunityId($request);
        }

        $church = Church::create($validated);

        return response()->json($church, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $church = Church::findOrFail($id);
        $this->ensureCommunityAccess($request, $church);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('churches')->ignore($church->id)],
            'address_id' => 'nullable|string|exists:addresses,id',
            'status' => ['sometimes', 'required', Rule::enum(ChurchStatus::class)],
            'found_date' => 'nullable|date',
            'community_id' => 'nullable|string|exists:communities,id',
            'founder_id' => 'nullable|string|exists:users,id',
        ]);

        if ($request->user()->role !== UserRole::SYSTEM) {
            $validated['community_id'] = $this->managedCommunityId($request);
        }

        $church->update($validated);

        return response()->json($church);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $church = Church::findOrFail($id);
        $this->ensureCommunityAccess($request, $church);
        $church->delete();

        return response()->json(null, 204);
    }

    private function ensureCommunityAccess(Request $request, Church $church): void
    {
        if ($request->user()->role === UserRole::SYSTEM) {
            return;
        }

        abort_unless($church->community_id === $this->managedCommunityId($request), 403);
    }

    private function managedCommunityId(Request $request): string
    {
        $communityId = $request->user()->profile?->community_id
            ?? $request->user()->church?->community_id;

        abort_unless($communityId, 403);

        return $communityId;
    }
}
