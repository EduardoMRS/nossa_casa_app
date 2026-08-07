<?php

namespace App\Models;

use App\Enums\UserRelationships;
use Illuminate\Database\Eloquent\Model;

class UserRelationship extends Model
{
    protected $fillable = [
        'user_id',
        'related_user_id',
        'relationship_type'
    ];
    
    public const FAMILY = [
        UserRelationships::SPOUSE,
        UserRelationships::PARENT,
        UserRelationships::CHILD,
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function relatedUser()
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function getRelationshipTypeAttribute()
    {
        return UserRelationships::from($this->attributes['relationship_type']) ?? null;
    }

    
}
