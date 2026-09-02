<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveChurchNetworkRequest;
use App\Http\Requests\StoreChurchNetworkRequest;
use App\Models\Church;
use App\Models\ChurchNetworkRequest;
use App\Models\Network;
use App\Services\ChurchNetworkService;
use App\Support\ChurchDomainContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NetworkController extends Controller
{
    public function __construct(
        private readonly ChurchNetworkService $networks,
        private readonly ChurchDomainContext $context,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actorChurch = $this->actorChurch($request);
        abort_unless($actorChurch, Response::HTTP_FORBIDDEN);

        $networkIds = array_values(array_unique([
            $actorChurch->id,
            ...$this->networks->relatedIds($actorChurch),
        ]));

        $networks = Network::query()
            ->whereIn('parent_church_id', $networkIds)
            ->whereIn('child_church_id', $networkIds)
            ->with(['parentChurch:id,name', 'childChurch:id,name'])
            ->latest()
            ->get();

        return response()->json($networks);
    }

    public function store(StoreChurchNetworkRequest $request): JsonResponse
    {
        $actorChurch = $request->actorChurch();
        abort_unless($actorChurch, Response::HTTP_FORBIDDEN);
        $parentChurch = Church::query()->findOrFail($request->validated('parent_church_id'));
        $childChurch = Church::query()->findOrFail($request->validated('child_church_id'));
        $networkRequest = $this->networks->request(
            $actorChurch,
            $parentChurch,
            $childChurch,
            $request->user(),
        );

        return response()->json($networkRequest->load([
            'requestingChurch:id,name',
            'parentChurch:id,name',
            'childChurch:id,name',
        ]), Response::HTTP_CREATED);
    }

    public function accept(Request $request, ChurchNetworkRequest $networkRequest): JsonResponse
    {
        $actorChurch = $this->actorChurch($request);
        abort_unless($actorChurch, Response::HTTP_FORBIDDEN);
        $network = $this->networks->accept($actorChurch, $networkRequest, $request->user());

        return response()->json($network->load(['parentChurch:id,name', 'childChurch:id,name']));
    }

    public function reject(Request $request, ChurchNetworkRequest $networkRequest): Response
    {
        $actorChurch = $this->actorChurch($request);
        abort_unless($actorChurch, Response::HTTP_FORBIDDEN);
        $this->networks->reject($actorChurch, $networkRequest, $request->user());

        return response()->noContent();
    }

    public function update(MoveChurchNetworkRequest $request, Network $network): JsonResponse
    {
        $actorChurch = $request->actorChurch();
        abort_unless($actorChurch, Response::HTTP_FORBIDDEN);
        $newParentChurch = Church::query()->findOrFail($request->validated('new_parent_church_id'));
        $network->loadMissing('childChurch');
        $movedNetwork = $this->networks->moveDescendant(
            $actorChurch,
            $network->childChurch,
            $newParentChurch,
        );

        return response()->json($movedNetwork->load(['parentChurch:id,name', 'childChurch:id,name']));
    }

    public function destroy(Request $request, Network $network): Response
    {
        $actorChurch = $this->actorChurch($request);
        abort_unless($actorChurch, Response::HTTP_FORBIDDEN);
        $network->loadMissing(['parentChurch', 'childChurch']);
        $allowedChurchIds = [$actorChurch->id, ...$this->networks->descendantIds($actorChurch)];

        abort_unless(
            in_array($network->parent_church_id, $allowedChurchIds, true)
                && in_array($network->child_church_id, $allowedChurchIds, true),
            Response::HTTP_FORBIDDEN,
        );

        $network->delete();

        return response()->noContent();
    }

    private function actorChurch(Request $request): ?Church
    {
        return $this->context->church() ?? $request->user()?->church;
    }
}
