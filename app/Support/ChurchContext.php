<?php

namespace App\Support;

use App\Enums\ChurchStatus;
use App\Enums\UserRole;
use App\Exceptions\ChurchContextException;
use App\Models\Church;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChurchContext
{
    private ?Church $church = null;

    private bool $mainDomain = false;

    private bool $resolved = false;

    private ?string $source = null;

    public function resolveWeb(Request $request, bool $failWhenUnknown = true): void
    {
        $this->reset();
        $host = self::normalizeDomain($request->getHost());
        $this->mainDomain = $host === $this->mainHost();
        $this->resolved = true;
        $this->source = 'domain';

        if ($this->mainDomain) {
            return;
        }

        $query = $this->activeChurchQuery()->where('domain', $host);
        $this->church = $failWhenUnknown ? $query->firstOrFail() : $query->first();
    }

    public function resolveApi(Request $request, bool $required = false, bool $public = false): void
    {
        $this->reset();
        $this->resolved = true;
        $this->mainDomain = self::normalizeDomain($request->getHost()) === $this->mainHost();
        $requestedChurchId = $request->header('X-Church-ID');

        if (is_string($requestedChurchId) && $requestedChurchId !== '') {
            if (! Str::isUlid($requestedChurchId)) {
                throw new ChurchContextException('CHURCH_CONTEXT_INVALID', 'church.context.invalid', 422);
            }

            $this->church = $this->activeChurchQuery()->find($requestedChurchId);
            $this->source = 'header';

            if (! $this->church) {
                throw new ChurchContextException('CHURCH_NOT_FOUND', 'church.context.not_found', 404);
            }
        } elseif (! $this->mainDomain) {
            $this->church = $this->activeChurchQuery()
                ->where('domain', self::normalizeDomain($request->getHost()))
                ->first();
            $this->source = 'domain';
        } elseif ($request->user() instanceof User) {
            $this->church = $this->automaticChurchFor($request->user());
            $this->source = $this->church ? 'membership' : null;
        }

        if ($this->church) {
            $this->ensureUserMaySelect($request->user(), $public);
        }

        if ($required && ! $this->church) {
            throw new ChurchContextException('CHURCH_CONTEXT_REQUIRED', 'church.context.required', 422);
        }
    }

    public function resolve(Request $request, bool $failWhenUnknown = true): void
    {
        $this->resolveWeb($request, $failWhenUnknown);
    }

    public function church(): ?Church
    {
        return $this->church;
    }

    public function churchId(): ?string
    {
        return $this->church?->id;
    }

    public function source(): ?string
    {
        return $this->source;
    }

    public function isMainDomain(): bool
    {
        return $this->mainDomain;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function mainHost(): string
    {
        return self::normalizeDomain((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    public function churchUrl(Church $church, string $path = '/'): string
    {
        if (! $church->domain) {
            return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
        }

        $scheme = Str::contains($church->domain, ['localhost', '127.0.0.1']) ? 'http' : 'https';

        return $scheme.'://'.$church->domain.'/'.ltrim($path, '/');
    }

    public static function normalizeDomain(?string $domain): string
    {
        $value = Str::lower(trim((string) $domain));

        if (Str::contains($value, '://')) {
            $value = (string) parse_url($value, PHP_URL_HOST);
        } else {
            $value = Str::before(Str::before($value, '/'), ':');
        }

        return rtrim($value, '.');
    }

    private function automaticChurchFor(User $user): ?Church
    {
        $memberships = $user->churches()
            ->where('churches.status', ChurchStatus::ACTIVE)
            ->get();
        $preferredChurchId = $user->profile?->church_id;

        if ($preferredChurchId !== null) {
            $preferredChurch = $memberships->firstWhere('id', $preferredChurchId);

            if ($preferredChurch instanceof Church) {
                return $preferredChurch->loadMissing('community:id,owner_id,name,slug,bible_versions,default_bible_version');
            }
        }

        if ($memberships->count() === 1) {
            return $memberships->first()?->loadMissing('community:id,owner_id,name,slug,bible_versions,default_bible_version');
        }

        return null;
    }

    private function ensureUserMaySelect(?User $user, bool $public): void
    {
        if ($public || ! $user || in_array($user->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)) {
            return;
        }

        if (! $user->churches()->whereKey($this->church?->id)->exists()) {
            throw new ChurchContextException('FORBIDDEN', 'church.context.membership_required', 403);
        }
    }

    /** @return Builder<Church> */
    private function activeChurchQuery(): Builder
    {
        return Church::query()
            ->with('community:id,owner_id,name,slug,bible_versions,default_bible_version')
            ->where('status', ChurchStatus::ACTIVE);
    }

    private function reset(): void
    {
        $this->church = null;
        $this->mainDomain = false;
        $this->resolved = false;
        $this->source = null;
    }
}
