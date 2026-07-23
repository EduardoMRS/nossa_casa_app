<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Network extends Model
{
    use HasUlids;

    protected $fillable = [
        'parent_church_id',
        'child_church_id',
        'community_id',
    ];

    public function parentChurch()
    {
        return $this->belongsTo(Church::class, 'parent_church_id');
    }

    public function childChurch()
    {
        return $this->belongsTo(Church::class, 'child_church_id');
    }

    public function community()
    {
        return $this->belongsTo(Community::class);
    }
}
