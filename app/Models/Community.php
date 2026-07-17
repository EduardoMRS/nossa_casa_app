<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Community extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'found_date',
    ];

    protected $table = 'communities';

    public function churches()
    {
        return $this->hasMany(Church::class, 'community_id');
    }

    public function members()
    {
        return $this->hasOneThrough(
            User::class,
            Church::class,
            'community_id', // Foreign key on the churches table...
            'church_id',    // Foreign key on the users table...
            'id',           // Local key on the communities table...
            'id'            // Local key on the churches table...
        );
    }
}
