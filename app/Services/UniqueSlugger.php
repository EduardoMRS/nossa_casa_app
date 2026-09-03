<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UniqueSlugger
{
    /**
     * @param  array<string, scalar|null>  $scope
     * @param  array<string>  $additionalTables
     */
    public function make(
        string $value,
        string $table,
        ?string $ignoreId = null,
        array $scope = [],
        array $additionalTables = [],
    ): string {
        $base = Str::slug($value) ?: 'item';
        $candidate = $base;
        $suffix = 2;

        while (! $this->available($candidate, $table, $ignoreId, $scope, $additionalTables)) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }

    /** @param  array<string, scalar|null>  $scope */
    private function available(string $candidate, string $table, ?string $ignoreId, array $scope, array $additionalTables): bool
    {
        foreach ([$table, ...$additionalTables] as $candidateTable) {
            $query = DB::table($candidateTable)->where('slug', $candidate);

            if ($candidateTable === $table && $ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }

            foreach ($scope as $column => $value) {
                $query->where($column, $value);
            }

            if ($candidateTable === 'church_registration_requests') {
                $query->where('status', 'pending');
            }

            if ($query->exists()) {
                return false;
            }
        }

        return true;
    }
}