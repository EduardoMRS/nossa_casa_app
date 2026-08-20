<?php

namespace App\Http\Middleware;

use App\Enums\LiveStreamStatus;
use App\Models\Classroom;
use App\Models\LiveStream;
use App\Models\Setting;
use App\Support\ChurchDomainContext;
use App\Support\ChurchTerminology;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private ChurchTerminology $terminology) {}

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
        $isForeignChurch = $currentChurch !== null
            && $user !== null
            && $user->role?->value !== 'system'
            && $user->profile?->church_id !== $currentChurch->id;
        $isOwnChurch = $currentChurch !== null
            && $user !== null
            && $user->profile?->church_id === $currentChurch->id;
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
            'latitude' => null,
            'longitude' => null,
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
            'portalUrl' => rtrim((string) config('app.url'), '/'),
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
                'userChurch' => $user?->church ? [
                    'id' => $user->church->id,
                    'name' => $user->church->name,
                    'domain' => $user->church->domain,
                ] : null,
                'isForeignChurch' => $isForeignChurch,
            ],
            'activeLiveStream' => $currentChurch
                ? LiveStream::query()
                    ->where('church_id', $currentChurch->id)
                    ->publiclyVisible()
                    ->where('status', LiveStreamStatus::LIVE)
                    ->where('active_slot', 1)
                    ->first(['id', 'name', 'started_at'])
                    ?->only(['id', 'name', 'started_at'])
                : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'branding' => $branding,
            'publicTemplates' => array_merge([
                'home' => 'classic',
                'posts_index' => 'classic',
                'posts_show' => 'classic',
                'events_index' => 'classic',
                'events_show' => 'classic',
                'form' => 'classic',
                'library' => 'classic',
                'gallery' => 'classic',
            ], is_array($setting?->options['templates'] ?? null) ? $setting->options['templates'] : []),
            'terminology' => $this->terminology->resolved(
                is_array($setting?->options['terminology'] ?? null)
                    ? $setting->options['terminology']
                    : [],
            ),
            'pwa' => [
                'publicKey' => config('services.webpush.public_key'),
            ],
            'permissions' => [
                'accessDashboard' => ($role === 'system' || $isOwnChurch)
                    && in_array($role, ['leader', 'media', 'church_leader', 'superadmin', 'system'], true),
                'manageBranding' => ($role === 'system' || $isOwnChurch)
                    && in_array($role, ['church_leader', 'superadmin', 'system'], true),
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
