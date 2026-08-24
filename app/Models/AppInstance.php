<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AppInstance extends Model
{
    use HasUlids;

    protected $fillable = [
        'key',
        'push_gateway_public_key',
        'push_gateway_enabled',
    ];

    protected function casts(): array
    {
        return [
            'push_gateway_enabled' => 'boolean',
        ];
    }
}
