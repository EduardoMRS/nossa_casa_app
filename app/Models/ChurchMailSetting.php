<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchMailSetting extends Model
{
    use HasUlids;

    protected $fillable = [
        'church_id',
        'enabled',
        'allow_branches',
        'host',
        'port',
        'scheme',
        'username',
        'password',
        'from_address',
        'from_name',
    ];

    protected $hidden = [
        'host',
        'port',
        'scheme',
        'username',
        'password',
        'from_address',
        'from_name',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'allow_branches' => 'boolean',
            'host' => 'encrypted',
            'port' => 'encrypted',
            'scheme' => 'encrypted',
            'username' => 'encrypted',
            'password' => 'encrypted',
            'from_address' => 'encrypted',
            'from_name' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Church, $this> */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
