<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

class ChurchStorageSetting extends Model
{
    use HasUlids;

    protected $fillable = [
        'church_id',
        'enabled',
        'allow_branches',
        'driver',
        'config',
    ];

    protected $hidden = [
        'config',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'allow_branches' => 'boolean',
            'config' => 'encrypted:array',
        ];
    }

    /** @return BelongsTo<Church, $this> */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * Return public configuration safe to display on the frontend without exposing secrets.
     *
     * @return array<string, mixed>
     */
    public function publicConfig(): array
    {
        $rawConfig = (array) ($this->config ?? []);

        $secretKeys = [
            'secret',
            'password',
            'private_key',
            'client_secret',
            'refresh_token',
        ];

        $safe = Arr::except($rawConfig, $secretKeys);

        foreach ($secretKeys as $key) {
            $safe['has_'.$key] = filled($rawConfig[$key] ?? null);
        }

        return $safe;
    }
}
