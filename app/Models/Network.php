<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Network extends Model
{
    protected $fillable = [
        'name',
        'parent_church_id',
        'child_church_id',
        'community_id',
    ];

    public function communities()
    {
        return $this->hasMany(Community::class, 'network_id');
    }

    public function members()
    {
        return $this->hasOneThrough(
            User::class,
            Community::class,
            'network_id', // Foreign key on the communities table...
            'community_id', // Foreign key on the users table...
            'id', // Local key on the networks table...
            'id' // Local key on the communities table...
        );
    }
}
