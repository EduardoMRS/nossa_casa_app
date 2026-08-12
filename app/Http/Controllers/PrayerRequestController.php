<?php

namespace App\Http\Controllers;

use App\Models\PrayerRequest;
use App\Support\ChurchDomainContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrayerRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requests = PrayerRequest::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json($requests);
    }

    public function store(Request $request, ChurchDomainContext $domainContext): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'is_anonymous' => ['nullable', 'boolean'],
        ]);
        $isAnonymous = (bool) ($validated['is_anonymous'] ?? false);

        $prayerRequest = PrayerRequest::query()->create([
            'content' => $validated['content'],
            'user_id' => $request->user() && ! $isAnonymous
                ? $request->user()->id
                : null,
            'church_id' => $domainContext->churchId() ?? $request->user()?->church?->id,
        ]);

        return response()->json($prayerRequest, 201);
    }
}
