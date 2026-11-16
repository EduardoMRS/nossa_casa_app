<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchStorageBackup extends Model
{
    use HasUlids;

    protected $fillable = [
        'church_id',
        'source_driver',
        'target_driver',
        'backup_disk',
        'backup_directory',
        'files_count',
        'total_bytes',
        'status',
        'cleanup_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'cleanup_at' => 'datetime',
            'files_count' => 'integer',
            'total_bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<Church, $this> */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
