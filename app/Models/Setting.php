<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'options'
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
