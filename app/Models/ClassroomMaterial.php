<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassroomMaterial extends Model
{
    use HasUlids;

    protected $fillable = [
        'classroom_id', 'added_by_id', 'type', 'title', 'description', 'url',
        'file_path', 'disk', 'mimetype', 'size',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }
}
