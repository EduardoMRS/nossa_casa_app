<?php

namespace App\Support;

use App\Models\Church;
use App\Models\Network;

final class ChurchTerminology
{
    /**
     * @return array{units: array<string, string>, roles: array<string, string>}
     */
    public function defaults(): array
    {
        return [
            'units' => collect((array) config('terminology.units'))
                ->mapWithKeys(fn (array $definition, string $scope): array => [
                    $scope => (string) $definition['default'],
                ])
                ->all(),
            'roles' => collect((array) config('terminology.roles'))
                ->mapWithKeys(fn (array $definition, string $role): array => [
                    $role => (string) $definition['default'],
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $saved
     * @return array{units: array<string, string>, roles: array<string, string>}
     */
    public function selections(?array $saved = null): array
    {
        $defaults = $this->defaults();

        return [
            'units' => $this->validSelections(
                (array) config('terminology.units'),
                is_array($saved['units'] ?? null) ? $saved['units'] : [],
                $defaults['units'],
            ),
            'roles' => $this->validSelections(
                (array) config('terminology.roles'),
                is_array($saved['roles'] ?? null) ? $saved['roles'] : [],
                $defaults['roles'],
            ),
        ];
    }

    /**
     * @return array{units: array<string, array<int, array{value: string, singular: string, plural: string}>>, roles: array<string, array{technical_label: string, options: array<int, array{value: string, label: string}>}>}
     */
    public function options(): array
    {
        $units = collect((array) config('terminology.units'))
            ->map(fn (array $definition): array => collect($definition['options'])
                ->map(fn (string $term): array => [
                    'value' => $term,
                    'singular' => __("terminology.units.{$term}.singular"),
                    'plural' => __("terminology.units.{$term}.plural"),
                ])
                ->values()
                ->all())
            ->all();

        $roles = collect((array) config('terminology.roles'))
            ->map(fn (array $definition, string $role): array => [
                'technical_label' => __("terminology.technical_roles.{$role}"),
                'options' => collect($definition['options'])
                    ->map(fn (string $term): array => [
                        'value' => $term,
                        'label' => __("terminology.roles.{$term}"),
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();

        return ['units' => $units, 'roles' => $roles];
    }

    /**
     * @param  array<string, mixed>|null  $saved
     * @return array{units: array<string, array{key: string, singular: string, plural: string}>, roles: array<string, array{key: string, label: string}>}
     */
    public function resolved(?array $saved = null): array
    {
        $selections = $this->selections($saved);

        return [
            'units' => collect($selections['units'])
                ->map(fn (string $term): array => [
                    'key' => $term,
                    'singular' => __("terminology.units.{$term}.singular"),
                    'plural' => __("terminology.units.{$term}.plural"),
                ])
                ->all(),
            'roles' => collect($selections['roles'])
                ->map(fn (string $term): array => [
                    'key' => $term,
                    'label' => __("terminology.roles.{$term}"),
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, array{default: string, options: array<int, string>}>  $definitions
     * @param  array<string, mixed>  $saved
     * @param  array<string, string>  $defaults
     * @return array<string, string>
     */
    private function validSelections(array $definitions, array $saved, array $defaults): array
    {
        return collect($definitions)
            ->mapWithKeys(function (array $definition, string $scope) use ($saved, $defaults): array {
                $selected = $saved[$scope] ?? $defaults[$scope];

                return [
                    $scope => is_string($selected) && in_array($selected, $definition['options'], true)
                        ? $selected
                        : $defaults[$scope],
                ];
            })
            ->all();
    }

    /** Resolve terminology through the church network before application defaults. */
    public function resolvedForChurch(Church $church): array
    {
        $current = $church;
        $visited = [];

        while ($current instanceof Church && ! isset($visited[$current->id])) {
            $visited[$current->id] = true;
            $options = $current->settings?->options ?? [];
            $saved = is_array($options['terminology'] ?? null) ? $options['terminology'] : [];
            $source = $options['terminology_source'] ?? null;

            if ($saved !== [] && $source !== 'inherited') {
                return $this->resolved($saved);
            }

            $current = Network::query()->where('child_church_id', $current->id)->with('parentChurch.settings')->first()?->parentChurch;
        }

        return $this->resolved();
    }
}
