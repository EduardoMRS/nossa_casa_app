<?php

namespace App\Http\Middleware;

use App\Models\Classroom;
use App\Models\Setting;
use App\Support\ChurchDomainContext;
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
        $domainContext = app(ChurchDomainContext::class);
        $currentChurch = $domainContext->church();
        $classroomChurchId = $currentChurch?->id ?? $user?->profile?->church_id ?? $user?->church?->id;

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
            'logo_path' => '',
            'logo_url' => '',
            'icon_name' => 'Sparkles',
            'contact_email' => '',
            'contact_phone' => '',
            'contact_whatsapp' => '',
            'address' => '',
            'map_embed' => '',
            'weekly_schedule' => [],
        ];
        $setting = null;

        $brandingChurch = $currentChurch ?? ($domainContext->isMainDomain() ? null : $user?->church);

        if ($brandingChurch) {
            $setting = Setting::query()->where('church_id', $brandingChurch->id)->first();
            $savedBranding = $setting?->options['branding'] ?? [];

            if (is_array($savedBranding)) {
                if (! empty($savedBranding['logo_path'])) {
                    $savedBranding['logo_url'] = genUrl($savedBranding['logo_path']);
                }

                $branding = array_merge($branding, $savedBranding);
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $user,
                'notifications' => $user?->unreadNotifications()
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn ($notification) => [
                        'id' => $notification->id,
                        'type' => $notification->data['type'] ?? null,
                        'child_name' => $notification->data['child_name'] ?? null,
                        'classroom_name' => $notification->data['classroom_name'] ?? null,
                        'pickup_name' => $notification->data['pickup_name'] ?? null,
                        'pickup_phone' => $notification->data['pickup_phone'] ?? null,
                        'created_at' => $notification->created_at,
                    ]) ?? [],
            ],
            'churchContext' => [
                'isMainDomain' => $domainContext->isMainDomain(),
                'church' => $currentChurch ? [
                    'id' => $currentChurch->id,
                    'name' => $currentChurch->name,
                    'slug' => $currentChurch->slug,
                    'domain' => $currentChurch->domain,
                    'community' => $currentChurch->community,
                ] : null,
                'membershipPending' => $currentChurch && $request->session()->get('church_membership_pending') === $currentChurch->id,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'branding' => $branding,
            'permissions' => [
                'accessDashboard' => in_array($role, ['leader', 'media', 'admin', 'superadmin', 'system'], true),
                'manageBranding' => in_array($role, ['admin', 'superadmin', 'system'], true),
            ],
            'classrooms' => [
                'hasKids' => $classroomChurchId
                    ? Classroom::query()->where('church_id', $classroomChurchId)->where('is_kids', true)->exists()
                    : false,
            ],
            'separateKidsMinistry' => (bool) data_get(
                Setting::query()->where('church_id', $classroomChurchId)->value('options'),
                'classrooms.separate_kids_ministry',
                true,
            ),
        ];
    }
}
