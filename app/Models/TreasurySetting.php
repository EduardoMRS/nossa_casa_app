<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Treasury configuration for a church (Pix key and/or bank account used to
 * receive tithes and offerings). Sensitive fields are encrypted at rest.
 */
class TreasurySetting extends Model
{
    use HasUlids;

    protected $fillable = [
        'church_id',
        'pix_key_type',
        'pix_key',
        'bank_name',
        'bank_account',
        'account_holder',
        'instructions',
        'accepts_contributions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pix_key' => 'encrypted',
            'bank_account' => 'encrypted',
            'accepts_contributions' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Church, $this>
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
