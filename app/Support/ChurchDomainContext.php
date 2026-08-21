<?php

namespace App\Support;

use App\Enums\ChurchStatus;
use App\Models\Church;
use Illuminate\Http\Request;

class ChurchDomainContext
{
    private ?Church $church = null;

    private bool $mainDomain = false;

    private bool $resolved = false;

    public function resolve(Request $request, bool $failWhenUnknown = true): void
    {
        $host = self::normalizeDomain($request->getHost());
        $this->mainDomain = $host === $this->mainHost();
        $this->resolved = true;

        if ($this->mainDomain) {
            $this->church = null;

            return;
        }

        $query = Church::query()
            ->with('community:id,owner_id,name,slug,bible_versions,default_bible_version')
            ->where('domain', $host)
            ->where('status', ChurchStatus::ACTIVE);

        $this->church = $failWhenUnknown ? $query->firstOrFail() : $query->first();
    }

    public function church(): ?Church
    {
        return $this->church;
    }

    public function churchId(): ?string
    {
        return $this->church?->id;
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
        $devPrefix = ['localhost', '127.0.0.1'];
        $scheme = 'https';

        /* TODO: ajustar para usar localhost e 127.0.0.1 como prefix, posteriormente verificar se há um certificado relacionado ao dominio em execução se não houver considere http
            $scheme = in_array($church->domain, $devPrefix, true)
                ? (string) parse_url((string) config('app.url'), PHP_URL_SCHEME)
                : 'https';
            ajuste temporario
        */
        foreach ($devPrefix as $prefix) {
            if (str_contains($church->domain, $prefix)) {
                $scheme = 'http';
                break;
            }
        }

        return $scheme.'://'.$church->domain.'/'.ltrim($path, '/');
    }

    public static function normalizeDomain(?string $domain): string
    {
        $value = mb_strtolower(trim((string) $domain));

        if (str_contains($value, '://')) {
            $value = (string) parse_url($value, PHP_URL_HOST);
        } else {
            $value = explode('/', $value, 2)[0];
            $value = explode(':', $value, 2)[0];
        }

        return rtrim($value, '.');
    }
}
