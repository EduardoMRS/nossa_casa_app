<?php

namespace App\Http\Controllers;

use App\Models\Network;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NetworkController extends Controller
{
    public function index()
    {
        $networks = Network::with(['parentChurch', 'childChurch', 'community'])->paginate(15);
        $networks->getCollection()->each(function (Network $network): void {
            $network->parentChurch?->localize();
            $network->childChurch?->localize();
            $network->community?->localize();
        });

        return response()->json($networks);
    }

    public function show(Network $network)
    {
        $network->load(['parentChurch', 'childChurch', 'community']);
        $network->parentChurch?->localize();
        $network->childChurch?->localize();
        $network->community?->localize();

        return response()->json($network);
    }

    public function store(Request $request)
    {
        $network = Network::create($request->validate([
            'parent_church_id' => ['required', 'different:child_church_id', 'exists:churches,id'],
            'child_church_id' => [
                'required',
                'exists:churches,id',
                Rule::unique('networks')->where('parent_church_id', $request->parent_church_id),
            ],
            'community_id' => ['nullable', 'exists:communities,id'],
        ]));

        return response()->json($network, 201);
    }

    public function update(Request $request, Network $network)
    {
        $network->update($request->validate([
            'community_id' => ['nullable', 'exists:communities,id'],
        ]));

        return response()->json($network);
    }

    public function destroy(Network $network)
    {
        $network->delete();

        return response()->noContent();
    }
}
