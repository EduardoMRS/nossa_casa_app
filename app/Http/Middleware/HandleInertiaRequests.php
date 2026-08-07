<?php

namespace App\Http\Middleware;

use App\Models\Classroom;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $role = $user?->role?->value ?? (string) $user?->role;

        $branding = [
            'brand_name' => config('app.name'),
            'tagline' => '',
            'banner_title' => '',
            'banner_subtitle' => '',
            'primary_color' => '#2f6e79',
            'secondary_color' => '#5f7d95',
            'accent_color' => '#c88b4a',
            'surface_color' => '#f4f7fb',
            'font_family' => 'Manrope, ui-sans-serif',
            'logo_url' => '',
            'icon_name' => 'Sparkles',
            'contact_email' => '',
            'contact_phone' => '',
            'contact_whatsapp' => '',
            'contact_website' => '',
        ];
        $setting = null;

        if ($user?->church) {
            $setting = Setting::query()->where('church_id', $user->church->id)->first();
            $savedBranding = $setting?->options['branding'] ?? [];

            if (is_array($savedBranding)) {
                $branding = array_merge($branding, $savedBranding);
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'branding' => $branding,
            'permissions' => [
                'manageBranding' => in_array($role, ['admin', 'superadmin', 'system'], true),
            ],
            'classrooms' => [
                'hasKids' => $user?->church?->id
                    ? Classroom::query()->where('church_id', $user->church->id)->where('is_kids', true)->exists()
                    : false,
                'separateKidsMinistry' => (bool) ($setting?->options['classrooms']['separate_kids_ministry'] ?? true),
            ],
        ];
    }
}
