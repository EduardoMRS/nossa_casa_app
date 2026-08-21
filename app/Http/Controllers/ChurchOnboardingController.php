<?php

namespace App\Http\Controllers;

use App\Actions\Churches\TransferChurchMembership;
use App\Enums\ChurchStatus;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\ChurchRegistrationRequest;
use App\Models\Community;
use App\Support\ChurchDomainContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChurchOnboardingController extends Controller
{
    public function __construct(
        private readonly ChurchDomainContext $context,
        private readonly TransferChurchMembership $transferChurchMembership,
    ) {}

    public function storeCommunity(Request $request): JsonResponse
    {
        abort_if(! $this->context->isMainDomain(), 404);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('communities')],
            'description' => ['required', 'string', 'max:5000'],
            'found_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $community = DB::transaction(function () use ($request, $validated): Community {
            $community = Community::query()->create([...$validated, 'owner_id' => $request->user()->id]);
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
        $request->merge(['domain' => ChurchDomainContext::normalizeDomain($request->string('domain')->toString())]);
        $communityId = $request->string('community_id')->toString();
        $this->ensureCommunityMembership($request, $communityId);
        $validated = $request->validate([
            'community_id' => ['required', 'string', 'exists:communities,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('churches'), Rule::unique('church_registration_requests')->where('status', 'pending')],
            'domain' => ['required', 'string', 'max:255', 'not_in:'.$this->context->mainHost(), 'regex:/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', Rule::unique('churches'), Rule::unique('church_registration_requests')->where('status', 'pending')],
            'description' => ['nullable', 'string', 'max:5000'],
            'found_date' => ['nullable', 'date', 'before_or_equal:today'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'proof_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:10240'],
        ]);
        $proofDocument = $request->file('proof_document');
        unset($validated['proof_document']);

        if ($proofDocument) {
            $validated['proof_document_path'] = $proofDocument->store('church-registration-proofs', 'local');
            $validated['proof_document_name'] = $proofDocument->getClientOriginalName();
            $validated['proof_document_mime'] = $proofDocument->getMimeType();
        }

        $registrationRequest = ChurchRegistrationRequest::query()->create([
            ...$validated,
            'requester_id' => $request->user()->id,
            'status' => 'pending',
        ]);

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

    private function ensureCommunityMembership(Request $request, string $communityId): void
    {
        $userCommunityId = $request->user()->profile?->community_id ?? $request->user()->church?->community_id;
        $ownsCommunity = Community::query()->whereKey($communityId)->where('owner_id', $request->user()->id)->exists();
        abort_unless($request->user()->role === UserRole::SYSTEM || $userCommunityId === $communityId || $ownsCommunity, 403);
    }

    private function ensureCanReview(Request $request, ChurchRegistrationRequest $registrationRequest): void
    {
        if (in_array($request->user()->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)) {
            return;
        }

        $sameCommunity = $request->user()->profile?->community_id === $registrationRequest->community_id
            || $request->user()->church?->community_id === $registrationRequest->community_id;
        $ownsCommunity = $registrationRequest->community()->where('owner_id', $request->user()->id)->exists();
        abort_unless($ownsCommunity || ($sameCommunity && in_array($request->user()->role, [UserRole::CHURCH_LEADER, UserRole::SUPERADMIN], true)), 403);
    }
}
