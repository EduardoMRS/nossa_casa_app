<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Traits\UploadsMedia;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    use UploadsMedia;
    
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated([
            'phone' => 'sometimes|nullable|string|max:255',
            'location_lang' => 'sometimes|nullable|string|max:255',
            'church_id' => 'sometimes|nullable|exists:churches,id',
            'community_id' => 'sometimes|nullable|exists:communities,id',
            'gender' => 'sometimes|nullable|in:male,female,other',
            'avatar_path' => 'sometimes|nullable',
        ]);

        

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }
        
        if (array_key_exists('avatar_path', $validated)) {
            $file = $request->file('avatar_path') ?? $request->input('avatar_path');
            $validated['avatar_path'] = $this->handleMediaUpload($file, "avatars/{$request->user()->id}", $request->user()->avatar_path);
        }

        $request->user()->fill($validated);
        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
