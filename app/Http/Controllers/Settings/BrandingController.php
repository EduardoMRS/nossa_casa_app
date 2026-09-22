<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateChurchSettingsRequest;
use App\Models\Church;
use App\Models\Setting;
use App\Services\ChurchNetworkSettingsData;
use App\Support\ChurchBrandingResolver;
use App\Support\ChurchDomainContext;
use App\Support\ChurchTerminology;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandingController extends Controller
{
    public function __construct(
        private ChurchTerminology $terminology,
        private ChurchBrandingResolver $brandingResolver,
        private ChurchDomainContext $domainContext,
        private ChurchNetworkSettingsData $networkSettings,
    ) {}

    public function edit(Request $request): Response
    {
        $church = $this->churchForRequest($request);
        if ($church === null) {
            abort(403);
        }

        $setting = Setting::query()->where('church_id', $church->id)->first();
        $branding = $setting?->options['branding'] ?? [];
        $templates = $setting?->options['templates'] ?? [];
        $savedTerminology = $setting?->options['terminology'] ?? [];
        $currentHost = ChurchDomainContext::normalizeDomain($request->getHost());
        $defaultDomain = $church->domain ?: (
            $currentHost === app(ChurchDomainContext::class)->mainHost()
                ? $church->slug.'.'.$currentHost
                : $currentHost
        );
        if (is_array($branding) && ! empty($branding['logo_path'])) {
            $branding['logo_url'] = genUrl($branding['logo_path']);
        } else {
            $branding = array_merge(
                is_array($branding) ? $branding : [],
                $this->brandingResolver->sharedLogo($church),
            );
        }
        $address = $church->address()->first();
        $mailSetting = $church->mailSetting()->first();

        return Inertia::render('Admin/Branding', [
            'branding' => array_merge(
                $this->defaultBranding(),
                is_array($branding) ? $branding : [],
                [
                    'domain' => $defaultDomain,
                    'address' => [
                        'country' => (string) ($address?->country ?? ''),
                        'state' => (string) ($address?->state ?? ''),
                        'city' => (string) ($address?->city ?? ''),
                        'neighborhood' => (string) ($address?->neighborhood ?? ''),
                        'street' => (string) ($address?->street ?? ''),
                        'number' => (string) ($address?->number ?? ''),
                        'complement' => (string) ($address?->complement ?? ''),
                        'zipcode' => (string) ($address?->zipcode ?? ''),
                        'latitude' => $address?->latitude,
                        'longitude' => $address?->longitude,
                    ],
                ],
            ),
            'templates' => array_merge($this->defaultTemplates(), is_array($templates) ? $templates : []),
            'terminology' => $this->terminology->selections(is_array($savedTerminology) ? $savedTerminology : []),
            'terminologyOptions' => $this->terminology->options(),
            'currency' => is_string($setting?->options['currency'] ?? null)
                ? $setting->options['currency']
                : 'BRL',
            'defaultLocale' => (string) ($setting?->options['default_locale']
                ?? $church->community?->default_locale
                ?? config('app.locale')),
            'mainDomain' => $this->domainContext->mainHost(),
            'networkSettings' => $this->networkSettings->forChurch($church),
            'mailSettings' => [
                'enabled' => $mailSetting?->enabled ?? false,
                'allow_branches' => $mailSetting?->allow_branches ?? false,
                'host' => $mailSetting?->host ?? '',
                'port' => $mailSetting?->port ?? '587',
                'scheme' => $mailSetting?->scheme ?? 'tls',
                'username' => $mailSetting?->username ?? '',
                'from_address' => $mailSetting?->from_address ?? '',
                'from_name' => $mailSetting?->from_name ?? $church->name,
                'has_password' => filled($mailSetting?->password),
            ],
            'registrationProof' => $this->registrationProofData($church),
        ]);
    }

    public function registrationProof(Request $request): BinaryFileResponse
    {
        $church = $this->churchForRequest($request);
        abort_unless($church, 403);
        $setting = Setting::query()->where('church_id', $church->id)->first();
        $proof = $setting?->options['registration_proof'] ?? null;

        abort_unless(is_array($proof) && is_string($proof['path'] ?? null), 404);
        abort_unless(Storage::disk('local')->exists($proof['path']), 404);

        return response()->file(Storage::disk('local')->path($proof['path']), [
            'Content-Type' => $proof['mime'] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.basename((string) ($proof['name'] ?? 'registration-proof')).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{name: string, url: string}|null */
    private function registrationProofData(Church $church): ?array
    {
        $proof = Setting::query()->where('church_id', $church->id)->first()?->options['registration_proof'] ?? null;

        return is_array($proof) && is_string($proof['name'] ?? null)
            ? ['name' => $proof['name'], 'url' => route('admin.branding.registrationProof')]
            : null;
    }

    public function network(Request $request): Response
    {
        $church = $this->churchForRequest($request);
        abort_unless($church, 403);

        return Inertia::render('Admin/ChurchNetwork', [
            'network' => $this->networkSettings->forChurch($church),
        ]);
    }

    public function update(UpdateChurchSettingsRequest $request): RedirectResponse
    {
        $church = $this->churchForRequest($request);
        abort_unless($church, 403);
        $validated = $request->validated();
        $domain = Arr::pull($validated, 'domain');
        $templates = Arr::pull($validated, 'templates', []);
        $terminology = Arr::pull($validated, 'terminology', []);
        $currency = Arr::pull($validated, 'currency');
        $defaultLocale = Arr::pull($validated, 'default_locale');
        $mail = Arr::pull($validated, 'mail');
        $address = Arr::pull($validated, 'address', []);
        $removeLogo = (bool) Arr::pull($validated, 'remove_logo', false);
        Arr::forget($validated, 'logo');
        $validated['map_embed'] = $this->sanitizeMapEmbed($validated['map_embed'] ?? null);
        $validated['weekly_schedule'] = $validated['weekly_schedule'] ?? [];

        $setting = Setting::query()->firstOrCreate(
            ['church_id' => $church->id],
            ['options' => []],
        );

        $options = is_array($setting->options) ? $setting->options : [];
        $currentBranding = array_merge(
            $this->defaultBranding(),
            is_array($options['branding'] ?? null) ? $options['branding'] : [],
        );
        Arr::forget($currentBranding, [
            'contact_website',
            // Remove legacy location data that used to live inside branding JSON.
            'address',
            'latitude',
            'longitude',
        ]);

        if ($removeLogo) {
            $this->deleteStoredLogo($currentBranding['logo_path'] ?? null);
            $validated['logo_path'] = '';
            $validated['logo_url'] = '';
        }

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store("church/{$church->id}/branding", (string) config('media.disk'));
            $this->deleteStoredLogo($currentBranding['logo_path'] ?? null);
            $validated['logo_path'] = $logoPath;
            $validated['logo_url'] = genUrl($logoPath);
        }

        $options['branding'] = array_merge($currentBranding, $validated);
        $options['templates'] = array_merge($this->defaultTemplates(), $templates);
        $options['terminology'] = $this->terminology->selections($terminology);
        $options['terminology_source'] = 'church';
        $options['currency'] = $currency ?? $options['currency'] ?? 'BRL';
        if (is_string($defaultLocale) && $defaultLocale !== '') {
            $options['default_locale'] = $defaultLocale;
        }

        $addressData = array_merge(
            $this->defaultAddress(),
            is_array($address) ? $address : [],
        );

        if (collect($addressData)->contains(fn (mixed $value): bool => filled($value))) {
            $addressModel = $church->address()->first() ?? $church->address()->make();
            $addressModel->fill($addressData);
            $addressModel->save();
        } else {
            $church->address()->delete();
        }

        $church->update(['domain' => $domain]);
        $setting->update(['options' => $options]);

        if (is_array($mail)) {
            $mailSetting = $church->mailSetting()->firstOrNew();
            $mailSetting->fill(Arr::except($mail, ['password']));

            if (filled($mail['password'] ?? null)) {
                $mailSetting->password = $mail['password'];
            }

            $mailSetting->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.church_settings_updated')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultBranding(): array
    {
        return [
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
            'icon_path' => '',
            'icon_name' => 'Sparkles',
            'contact_email' => '',
            'contact_phone' => '',
            'contact_whatsapp' => '',
            'map_embed' => '',
            'weekly_schedule' => [],
            'social_links' => [],
        ];
    }

    /**
     * @return array{
     *     country: string,
     *     state: string,
     *     city: string,
     *     neighborhood: string,
     *     street: string,
     *     number: string,
     *     complement: string,
     *     zipcode: string,
     *     latitude: float|null,
     *     longitude: float|null
     * }
     */
    private function defaultAddress(): array
    {
        return [
            'country' => '',
            'state' => '',
            'city' => '',
            'neighborhood' => '',
            'street' => '',
            'number' => '',
            'complement' => '',
            'zipcode' => '',
            'latitude' => null,
            'longitude' => null,
        ];
    }

    /** @return array<string, string> */
    private function defaultTemplates(): array
    {
        return [
            'home' => 'classic',
            'posts_index' => 'classic',
            'posts_show' => 'classic',
            'events_index' => 'classic',
            'events_show' => 'classic',
            'form' => 'classic',
            'library' => 'classic',
            'gallery' => 'classic',
        ];
    }

    private function deleteStoredLogo(mixed $logoPath): void
    {
        if (is_string($logoPath) && $logoPath !== '') {
            Storage::disk((string) config('media.disk'))->delete($logoPath);
        }
    }

    private function sanitizeMapEmbed(?string $mapEmbed): string
    {
        if (! $mapEmbed) {
            return '';
        }

        $mapUrl = trim($mapEmbed);

        if (str_contains($mapUrl, '<iframe')) {
            preg_match('/\bsrc=["\']([^"\']+)["\']/i', $mapUrl, $matches);
            $mapUrl = html_entity_decode($matches[1] ?? '');
        }

        $host = mb_strtolower((string) parse_url($mapUrl, PHP_URL_HOST));
        $scheme = mb_strtolower((string) parse_url($mapUrl, PHP_URL_SCHEME));
        $allowedHosts = ['google.com', 'googleusercontent.com', 'openstreetmap.org'];
        $isAllowedHost = collect($allowedHosts)->contains(
            fn (string $allowedHost): bool => $host === $allowedHost || str_ends_with($host, '.'.$allowedHost),
        );

        if ($scheme !== 'https' || ! $isAllowedHost) {
            throw ValidationException::withMessages([
                'map_embed' => __('church.map_embed_secure_required'),
            ]);
        }

        return $mapUrl;
    }

    private function churchForRequest(Request $request): ?Church
    {
        $church = $this->domainContext->church();

        if ($church || in_array($request->user()?->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)) {
            return $church;
        }

        $userChurch = $request->user()?->church;

        return $this->domainContext->isMainDomain() && blank($userChurch?->domain)
            ? $userChurch
            : null;
    }
}
