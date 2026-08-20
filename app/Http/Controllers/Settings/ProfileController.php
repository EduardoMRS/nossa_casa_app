<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Churches\TransferChurchMembership;
use App\Enums\UserRelationships;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Church;
use App\Models\ClassroomPresence;
use App\Models\Community;
use App\Models\PrayerRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private readonly TransferChurchMembership $transferChurchMembership) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user()->load([
            'profile',
            'registeredEvents:id,title,slug,start_time,end_time',
            'classrooms:id,name,is_kids,min_age,max_age',
            'relationships.relatedUser.profile',
            'relatedRelationships.user.profile',
        ]);
        $churchId = $user->profile?->church_id;
        $childIds = $user->relationships()
            ->where('relationship_type', UserRelationships::PARENT->value)
            ->pluck('related_user_id');

        return Inertia::render('settings/Workspace', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'workspaceUser' => $user,
            'prayerRequests' => PrayerRequest::query()->where('user_id', $user->id)->latest()->get(),
            'churchMembers' => User::query()
                ->where('id', '!=', $user->id)
                ->whereHas('profile', fn ($query) => $query->where('church_id', $churchId))
                ->with('profile:user_id,gender,avatar_path')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'birth_date']),
            'communities' => Community::query()
                ->with(['churches' => fn ($query) => $query->orderBy('name')->select(['id', 'community_id', 'name', 'slug'])])
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'pendingChildCheckouts' => ClassroomPresence::query()
                ->whereIn('user_id', $childIds)
                ->whereNull('check_out')
                ->whereHas('classroom', fn ($query) => $query->where('is_kids', true))
                ->with(['classroom:id,name', 'user:id,first_name,last_name'])
                ->latest('check_in')
                ->get()
                ->map(fn (ClassroomPresence $presence) => [
                    'id' => $presence->id,
                    'user_id' => $presence->user_id,
                    'child_name' => $presence->user?->name,
                    'classroom_name' => $presence->classroom?->name,
                    'check_in' => $presence->check_in,
                    'checkout_pin' => $presence->checkout_pin_code,
                ]),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $selectedChurch = isset($validated['church_id'])
            ? Church::query()->findOrFail($validated['church_id'])
            : null;

        $nameParts = preg_split('/\s+/', trim($validated['name']), 2) ?: [];
        $request->user()->first_name = $nameParts[0] ?? '';
        $request->user()->last_name = $nameParts[1] ?? '';
        $request->user()->email = $validated['email'];
        if (array_key_exists('birth_date', $validated)) {
            $request->user()->birth_date = $validated['birth_date'];
        }

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();
        $profileUpdates = array_intersect_key($validated, array_flip(['phone', 'gender']));

        if ($selectedChurch !== null && $request->user()->profile?->church_id !== $selectedChurch->id) {
            $this->transferChurchMembership->handle($request->user(), $selectedChurch);
        } else {
            $profileUpdates['community_id'] = $validated['community_id'] ?? null;
            $profileUpdates['church_id'] = $validated['church_id'] ?? null;
        }

        if ($profileUpdates !== []) {
            $request->user()->profile()->updateOrCreate(
                ['user_id' => $request->user()->id],
                $profileUpdates,
            );
        }

        if ($request->hasFile('avatar')) {
            $currentAvatar = $request->user()->profile?->avatar_path;
            $avatarPath = $request->file('avatar')->store("users/{$request->user()->id}/avatar", (string) config('media.disk'));
            $request->user()->profile()->updateOrCreate(
                ['user_id' => $request->user()->id],
                ['avatar_path' => $avatarPath],
            );

            if ($currentAvatar) {
                Storage::disk((string) config('media.disk'))->delete($currentAvatar);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.profile_updated')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(in_array($user->role, [UserRole::SYSTEM, UserRole::SUPERADMIN], true), 403, __('user.protected_delete'));

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
