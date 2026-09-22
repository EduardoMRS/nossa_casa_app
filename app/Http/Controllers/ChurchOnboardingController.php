<?php

namespace App\Http\Controllers;

use App\Actions\Churches\TransferChurchMembership;
use App\Enums\ChurchStatus;
use App\Enums\UserRole;
use App\Mail\ChurchRegistrationRequestedMail;
use App\Models\Church;
use App\Models\ChurchRegistrationRequest;
use App\Models\Community;
use App\Models\Network;
use App\Models\User;
use App\Services\AddressGeocoder;
use App\Services\ChurchIdentity;
use App\Services\UniqueSlugger;
use App\Support\ChurchDomainContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChurchOnboardingController extends Controller
{
    public function __construct(
        private readonly ChurchDomainContext $context,
        private readonly TransferChurchMembership $transferChurchMembership,
        private readonly AddressGeocoder $addressGeocoder,
        private readonly ChurchIdentity $churchIdentity,
        private readonly UniqueSlugger $slugs,
    ) {}

    public function storeCommunity(Request $request): JsonResponse
    {
        abort_if(! $this->context->isMainDomain(), 404);
        $request->merge([
            'default_locale' => $request->input('default_locale') ?: app()->getLocale(),
        ]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'found_date' => ['nullable', 'date', 'before_or_equal:today'],
            'default_locale' => ['required', Rule::in(['pt', 'en'])],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ]);

        $validated['slug'] = $this->slugs->make($validated['name'], 'communities');
        $community = DB::transaction(function () use ($request, $validated): Community {
            $address = collect($validated)->only(['address', 'latitude', 'longitude'])->all();
            $community = Community::query()->create([
                ...collect($validated)->except(['address', 'latitude', 'longitude'])->all(),
                'owner_id' => $request->user()->id,
            ]);

            if (filled($address['address'] ?? null) || isset($address['latitude'], $address['longitude'])) {
                $community->address()->create([
                    'street' => $address['address'] ?? null,
                    'latitude' => $address['latitude'] ?? null,
                    'longitude' => $address['longitude'] ?? null,
                ]);
            }

            $request->user()->profile()->updateOrCreate(
                ['user_id' => $request->user()->id],
                ['community_id' => $community->id],
            );

            return $community;
        });

        return response()->json($community, 201);
    }

    public function storeChurchRequest(Request $request): JsonResponse
    {
        abort_if(! $this->context->isMainDomain(), 404);
        $communityId = $request->string('community_id')->toString();
        $communityLocale = Community::query()->whereKey($communityId)->value('default_locale');
        $request->merge([
            'domain' => ChurchDomainContext::normalizeDomain($request->string('domain')->toString()),
            'locale' => $request->input('locale') ?: $communityLocale ?: app()->getLocale(),
        ]);
        $validated = $request->validate([
            'community_id' => ['required', 'string', 'exists:communities,id'],
            'parent_church_id' => [
                'nullable',
                'string',
                Rule::exists('churches', 'id')->where(fn ($query) => $query
                    ->where('community_id', $communityId)
                    ->where('status', ChurchStatus::ACTIVE->value)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'domain' => [
                'nullable',
                'string',
                'max:255',
                Rule::notIn([
                    $this->context->mainHost(),
                    'www.'.$this->context->mainHost(),
                ]),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'found_date' => ['nullable', 'date', 'before_or_equal:today'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'country' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:2'],
            'city' => ['required', 'string', 'max:255'],
            'neighborhood' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:50'],
            'complement' => ['nullable', 'string', 'max:255'],
            'zipcode' => ['required', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'locale' => ['required', Rule::in(['pt', 'en'])],
            'proof_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:10240'],
        ]);
        $proofDocument = $request->file('proof_document');
        $requestedParentChurchId = $validated['parent_church_id'] ?? null;
        unset($validated['proof_document'], $validated['parent_church_id']);

        $coordinates = $this->addressGeocoder->coordinates($validated);
        if ($coordinates) {
            $validated['latitude'] = $coordinates['latitude'];
            $validated['longitude'] = $coordinates['longitude'];
        }

        if ($proofDocument) {
            $validated['proof_document_path'] = $proofDocument->store('church-registration-proofs', 'local');
            $validated['proof_document_name'] = $proofDocument->getClientOriginalName();
            $validated['proof_document_mime'] = $proofDocument->getMimeType();
        }

        $registrationRequest = ChurchRegistrationRequest::query()->create([
            ...$validated,
            'requester_id' => $request->user()->id,
            'requested_parent_church_id' => $requestedParentChurchId,
            'status' => 'pending',
        ]);

        if (in_array($request->user()->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)) {
            return $this->approve($request, $registrationRequest);
        }

        $this->notifyRegistrationReviewers($registrationRequest);

        return response()->json($registrationRequest, 201);
    }

    public function proofDocument(Request $request, ChurchRegistrationRequest $registrationRequest): BinaryFileResponse
    {
        $this->ensureCanReview($request, $registrationRequest);
        $path = $registrationRequest->proof_document_path;

        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $registrationRequest->proof_document_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.basename((string) $registrationRequest->proof_document_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Request $request, ChurchRegistrationRequest $registrationRequest): JsonResponse
    {
        $this->ensureCanReview($request, $registrationRequest);
        abort_unless($registrationRequest->status === 'pending', 422);
        $church = DB::transaction(function () use ($request, $registrationRequest): Church {
            $church = Church::query()->create([
                'name' => $registrationRequest->name,
                'slug' => $registrationRequest->slug,
                'domain' => $registrationRequest->domain,
                'community_id' => $registrationRequest->community_id,
                'found_date' => $registrationRequest->found_date,
                'status' => ChurchStatus::ACTIVE,
            ]);

            if ($registrationRequest->address || $registrationRequest->street || $registrationRequest->city || $registrationRequest->zipcode || ($registrationRequest->latitude !== null && $registrationRequest->longitude !== null)) {
                $church->address()->create([
                    'country' => $registrationRequest->country,
                    'state' => $registrationRequest->state,
                    'city' => $registrationRequest->city,
                    'neighborhood' => $registrationRequest->neighborhood,
                    'street' => $registrationRequest->street ?: $registrationRequest->address,
                    'number' => $registrationRequest->number,
                    'complement' => $registrationRequest->complement,
                    'zipcode' => $registrationRequest->zipcode,
                    'latitude' => $registrationRequest->latitude,
                    'longitude' => $registrationRequest->longitude,
                ]);
            }

            $setting = $church->settings()->firstOrFail();
            $options = is_array($setting->options) ? $setting->options : [];
            $options['default_locale'] = $registrationRequest->locale;
            if ($registrationRequest->proof_document_path
                && Storage::disk('local')->exists($registrationRequest->proof_document_path)) {
                $extension = strtolower(pathinfo((string) $registrationRequest->proof_document_name, PATHINFO_EXTENSION));
                $proofPath = "church/{$church->id}/registration-proof.{$extension}";
                Storage::disk('local')->copy($registrationRequest->proof_document_path, $proofPath);
                $options['registration_proof'] = [
                    'path' => $proofPath,
                    'name' => $registrationRequest->proof_document_name,
                    'mime' => $registrationRequest->proof_document_mime,
                ];
            }
            $setting->update(['options' => $options]);
            if ($registrationRequest->requested_parent_church_id) {
                $parentChurch = Church::query()
                    ->whereKey($registrationRequest->requested_parent_church_id)
                    ->where('community_id', $registrationRequest->community_id)
                    ->where('status', ChurchStatus::ACTIVE)
                    ->lockForUpdate()
                    ->first();
                abort_unless($parentChurch, 422);

                Network::query()->create([
                    'parent_church_id' => $parentChurch->id,
                    'child_church_id' => $church->id,
                    'community_id' => $registrationRequest->community_id,
                ]);
            }

            $registrationRequest->requester->profile()->updateOrCreate(
                ['user_id' => $registrationRequest->requester_id],
                ['community_id' => $registrationRequest->community_id, 'church_id' => $church->id],
            );

            if ($registrationRequest->requester->role !== UserRole::SYSTEM) {
                $registrationRequest->requester->update(['role' => UserRole::CHURCH_LEADER]);
            }

            $registrationRequest->update([
                'status' => 'approved',
                'approved_church_id' => $church->id,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $request->string('review_notes')->toString() ?: null,
            ]);

            return $church;
        });

        return response()->json(['church' => $church, 'request' => $registrationRequest->fresh()]);
    }

    public function reject(Request $request, ChurchRegistrationRequest $registrationRequest): JsonResponse
    {
        $this->ensureCanReview($request, $registrationRequest);
        abort_unless($registrationRequest->status === 'pending', 422);
        $validated = $request->validate(['review_notes' => ['required', 'string', 'max:2000']]);
        $registrationRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'],
        ]);

        return response()->json($registrationRequest->fresh());
    }

    public function handoff(Request $request): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string', 'size:64']]);
        $payload = Cache::pull('church-auth-handoff:'.$request->string('token')->toString());
        abort_unless(is_array($payload), 403);
        abort_unless($this->context->churchId() === ($payload['church_id'] ?? null), 403);
        abort_unless(hash_equals((string) ($payload['user_agent'] ?? ''), hash('sha256', (string) $request->userAgent())), 403);
        Auth::guard('web')->loginUsingId($payload['user_id']);
        $request->session()->regenerate();
        $request->session()->forget('church_membership_pending');

        $canAccessDashboard = in_array($request->user()?->role, [
            UserRole::LEADER,
            UserRole::MEDIA,
            UserRole::CHURCH_LEADER,
            UserRole::SUPERADMIN,
            UserRole::SYSTEM,
        ], true);

        return redirect()->route($canAccessDashboard ? 'dashboard' : 'home');
    }

    public function switchMembership(Request $request): RedirectResponse
    {
        $request->validate(['confirmed' => ['required', 'accepted']]);
        $church = $this->context->church();
        abort_unless($church && $request->user()->profile?->church_id !== $church->id, 403);

        $this->transferChurchMembership->handle($request->user(), $church);

        return redirect()->route('home');
    }

    public function declineMembership(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function ensureCanReview(Request $request, ChurchRegistrationRequest $registrationRequest): void
    {
        if (in_array($request->user()->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)) {
            return;
        }

        if ($registrationRequest->requested_parent_church_id) {
            $reviewerChurchId = $request->user()->profile?->church_id ?? $request->user()->church?->id;
            $isSelectedParentLeader = $reviewerChurchId === $registrationRequest->requested_parent_church_id
                && $request->user()->role === UserRole::CHURCH_LEADER;
            abort_unless($isSelectedParentLeader, 403);

            return;
        }

        $sameCommunity = $request->user()->profile?->community_id === $registrationRequest->community_id
            || $request->user()->church?->community_id === $registrationRequest->community_id;
        $ownsCommunity = $registrationRequest->community()->where('owner_id', $request->user()->id)->exists();
        abort_unless($ownsCommunity || ($sameCommunity && in_array($request->user()->role, [UserRole::CHURCH_LEADER, UserRole::SUPERADMIN], true)), 403);
    }

    private function notifyRegistrationReviewers(ChurchRegistrationRequest $registrationRequest): void
    {
        $registrationRequest->loadMissing(['community', 'requestedParentChurch', 'requester']);

        $reviewers = User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->when(
                $registrationRequest->requested_parent_church_id,
                fn ($query, string $parentChurchId) => $query
                    ->where('role', UserRole::CHURCH_LEADER)
                    ->whereHas('profile', fn ($profile) => $profile->where('church_id', $parentChurchId)),
                fn ($query) => $query->where(function ($reviewers) use ($registrationRequest): void {
                    $reviewers
                        ->whereKey($registrationRequest->community->owner_id)
                        ->orWhere(function ($communityLeaders) use ($registrationRequest): void {
                            $communityLeaders
                                ->where('role', UserRole::CHURCH_LEADER)
                                ->whereHas('profile', fn ($profile) => $profile
                                    ->where('community_id', $registrationRequest->community_id));
                        });
                }),
            )
            ->get()
            ->unique('email');

        foreach ($reviewers as $reviewer) {
            Mail::to($reviewer)->queue(
                (new ChurchRegistrationRequestedMail($registrationRequest))
                    ->locale($reviewer->preferredLocale()),
            );
        }
    }
}
