<?php

namespace App\Http\Controllers;

use App\Enums\ChurchStatus;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Setting;
use App\Support\ChurchDomainContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ChurchController extends Controller
{
    public function index(): JsonResponse
    {
        $churches = Church::with('community')
            ->paginate(15)
            ->through(fn (Church $church): Church => $church->localize(relations: ['community']));

        return response()->json($churches);
    }

    public function show(string $slug): JsonResponse
    {
        $church = Church::with(['community', 'address', 'settings', 'categories'])->where('slug', $slug)->firstOrFail();

        $church->localize(relations: ['community', 'categories']);

        return response()->json($church);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->filled('domain')) {
            $request->merge(['domain' => ChurchDomainContext::normalizeDomain($request->string('domain')->toString())]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:churches',
            'domain' => [
                'nullable',
                'string',
                'max:255',
                'not_in:'.app(ChurchDomainContext::class)->mainHost(),
                Rule::unique('churches'),
                Rule::unique('church_registration_requests', 'domain')->where('status', 'pending'),
            ],
            'address_id' => 'nullable|string|exists:addresses,id',
            'status' => ['required', Rule::enum(ChurchStatus::class)],
            'found_date' => 'nullable|date',
            'community_id' => 'nullable|string|exists:communities,id',
            'founder_id' => 'nullable|string|exists:users,id',
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        unset($validated['logo'], $validated['icon']);

        if (! $this->canChangeCommunity($request)) {
            $validated['community_id'] = $this->managedCommunityId($request);
        }

        $church = Church::create($validated);
        $this->storeBrandingAssets($church, $request->file('logo'), $request->file('icon'));

        return response()->json($church, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $church = Church::findOrFail($id);
        $this->ensureCommunityAccess($request, $church);

        if ($request->has('domain')) {
            $request->merge([
                'domain' => $request->filled('domain')
                    ? ChurchDomainContext::normalizeDomain($request->string('domain')->toString())
                    : null,
            ]);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('churches')->ignore($church->id)],
            'domain' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'not_in:'.app(ChurchDomainContext::class)->mainHost(),
                Rule::unique('churches')->ignore($church->id),
                Rule::unique('church_registration_requests', 'domain')->where('status', 'pending'),
            ],
            'address_id' => 'nullable|string|exists:addresses,id',
            'status' => ['sometimes', 'required', Rule::enum(ChurchStatus::class)],
            'found_date' => 'nullable|date',
            'community_id' => 'nullable|string|exists:communities,id',
            'founder_id' => 'nullable|string|exists:users,id',
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        unset($validated['logo'], $validated['icon']);

        if (! $this->canChangeCommunity($request)) {
            if ($request->has('community_id')) {
                abort_unless($request->input('community_id') === $church->community_id, 403);
            }

            unset($validated['community_id']);
        }

        $church->update($validated);
        $this->storeBrandingAssets($church, $request->file('logo'), $request->file('icon'));

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
        if (in_array($request->user()->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)) {
            return;
        }

        abort_unless($church->community_id === $this->managedCommunityId($request), 403);
    }

    private function managedCommunityId(Request $request): string
    {
        $profile = $request->user()->profile;
        $communityId = $profile?->community_id;

        if (! is_string($communityId) && $profile?->church_id) {
            $communityId = Church::query()->whereKey($profile->church_id)->value('community_id');
        }

        abort_unless(is_string($communityId) && $communityId !== '', 403);

        return $communityId;
    }

    private function canChangeCommunity(Request $request): bool
    {
        return in_array($request->user()->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true);
    }

    private function storeBrandingAssets(
        Church $church,
        ?UploadedFile $logo,
        ?UploadedFile $icon,
    ): void {
        if ($logo === null && $icon === null) {
            return;
        }

        $setting = Setting::query()->firstOrCreate(['church_id' => $church->id], ['options' => []]);
        $options = is_array($setting->options) ? $setting->options : [];
        $branding = is_array($options['branding'] ?? null) ? $options['branding'] : [];

        foreach (['logo' => $logo, 'icon' => $icon] as $assetName => $uploadedFile) {
            if ($uploadedFile === null) {
                continue;
            }

            $pathKey = $assetName.'_path';
            $currentPath = $branding[$pathKey] ?? null;
            $branding[$pathKey] = $uploadedFile->store(
                "church/{$church->id}/branding",
                (string) config('media.disk'),
            );

            if (is_string($currentPath) && $currentPath !== '' && ! filter_var($currentPath, FILTER_VALIDATE_URL)) {
                Storage::disk((string) config('media.disk'))->delete($currentPath);
            }
        }

        $options['branding'] = $branding;
        $setting->update(['options' => $options]);
    }
}
