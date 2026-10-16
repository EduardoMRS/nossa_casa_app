<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasUlids;

    protected $fillable = [
        'church_id',
        'options',
    ];

    protected $table = 'settings';

    protected $casts = [
        'options' => 'array',
    ];

    public function church()
    {
        return $this->belongsTo(Church::class);
    }
}
