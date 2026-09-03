<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChurchIdentity
{
    public function __construct(private readonly UniqueSlugger $slugs) {}

    public function slug(string $name, ?string $ignoreId = null): string
    {
        return $this->slugs->make($name, 'churches', $ignoreId, additionalTables: ['church_registration_requests']);
    }

    public function domain(string $name, ?string $city, string $mainDomain): string
    {
        $base = Str::lower($this->acronym($name).$this->cityCode($city));
        $candidate = $base;
        $suffix = 2;

        while (DB::table('churches')->where('domain', $candidate.'.'.$mainDomain)->exists()
            || DB::table('church_registration_requests')->where('domain', $candidate.'.'.$mainDomain)->where('status', 'pending')->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate.'.'.$mainDomain;
    }

    private function acronym(string $name): string
    {
        $words = preg_split('/\s+/', Str::upper(Str::ascii(trim($name)))) ?: [];
        $ignored = ['A', 'AS', 'DA', 'DAS', 'DE', 'DO', 'DOS', 'E', 'EM'];
        $letters = collect($words)
            ->reject(fn (string $word): bool => in_array($word, $ignored, true))
            ->map(fn (string $word): string => Str::substr($word, 0, 1))
            ->implode('');

        return $letters !== '' ? $letters : 'CH';
    }

    private function cityCode(?string $city): string
    {
        $words = preg_split('/\s+/', Str::upper(Str::ascii(trim((string) $city)))) ?: [];
        $ignored = ['A', 'AS', 'DA', 'DAS', 'DE', 'DO', 'DOS', 'E', 'EM'];
        $words = array_values(array_filter($words, fn (string $word): bool => $word !== '' && ! in_array($word, $ignored, true)));

        if ($words === []) {
            return 'BR';
        }

        return count($words) === 1
            ? Str::substr($words[0], 0, 3)
            : collect($words)->map(fn (string $word): string => Str::substr($word, 0, 1))->implode('');
    }
}