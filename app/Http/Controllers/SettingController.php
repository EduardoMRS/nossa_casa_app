<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Support\ChurchAccess;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /** @var array<int, UserRole> */
    private const SETTINGS_ROLES = [UserRole::CHURCH_LEADER];

    public function index(Request $request)
    {
        $user = $request->user();

        $settings = Setting::with('church:id,name,slug')
            ->when(
                ! ChurchAccess::isSystem($user),
                fn ($query) => $query->whereIn('church_id', $this->churchAccess()->scopeChurchIds($user)),
            )
            ->paginate(15);
        $settings->getCollection()->each(fn (Setting $setting) => $setting->church?->localize());

        return response()->json($settings);
    }

    public function show(Request $request, Setting $setting)
    {
        $this->ensureCanManageSetting($request, $setting->church_id);
        $setting->load('church:id,name,slug');
        $setting->church?->localize();

        return response()->json($setting);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'church_id' => ['required', 'exists:churches,id'],
            'options' => ['nullable', 'array'],
        ]);
        $this->ensureCanManageSetting($request, $validated['church_id']);

        $setting = Setting::updateOrCreate(['church_id' => $validated['church_id']], ['options' => $validated['options'] ?? []]);

        return response()->json($setting, 201);
    }

    public function update(Request $request, Setting $setting)
    {
        $this->ensureCanManageSetting($request, $setting->church_id);
        $setting->update($request->validate(['options' => ['nullable', 'array']]));

        return response()->json($setting);
    }

    public function destroy(Request $request, Setting $setting)
    {
        $this->ensureCanManageSetting($request, $setting->church_id);
        $setting->delete();

        return response()->noContent();
    }

    private function ensureCanManageSetting(Request $request, ?string $churchId): void
    {
        abort_unless($this->churchAccess()->canManageChurch($request->user(), $churchId, self::SETTINGS_ROLES), 403);
    }
}
