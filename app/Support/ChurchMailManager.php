<?php

namespace App\Support;

use App\Models\Church;
use App\Models\ChurchMailSetting;
use App\Models\Network;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class ChurchMailManager
{
    public function configureFor(Church $church): ?ChurchMailSetting
    {
        $setting = $this->resolveFor($church);

        if (! $setting) {
            $this->reset();

            return null;
        }

        Config::set('mail.mailers.church', [
            'transport' => 'smtp',
            'scheme' => $setting->scheme === 'ssl' ? 'smtps' : 'smtp',
            'host' => $setting->host,
            'port' => (int) $setting->port,
            'username' => $setting->username,
            'password' => $setting->password,
            'timeout' => null,
        ]);
        Config::set('mail.from.address', $setting->from_address);
        Config::set('mail.from.name', $setting->from_name ?: $church->name);
        Config::set('mail.default', 'church');
        Mail::purge('church');

        return $setting;
    }

    public function reset(): void
    {
        Config::set('mail.default', config('mail.application_default'));
        Config::set('mail.from', config('mail.application_from'));
        Mail::purge('church');
    }

    public function resolveFor(Church $church): ?ChurchMailSetting
    {
        $own = $church->mailSetting()->where('enabled', true)->first();

        if ($own) {
            return $own;
        }

        $parentIds = Network::query()
            ->where('child_church_id', $church->id)
            ->pluck('parent_church_id');

        return ChurchMailSetting::query()
            ->whereIn('church_id', $parentIds)
            ->where('enabled', true)
            ->where('allow_branches', true)
            ->first();
    }
}
