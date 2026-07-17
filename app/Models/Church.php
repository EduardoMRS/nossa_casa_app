<?php

namespace App\Models;

use App\Enums\ChurchStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Church extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'address_id',
        'status',
        'found_date',
        'community_id',
        'founder_id',
    ];

    protected $casts = [
        'status' => ChurchStatus::class,
        'found_date' => 'date',
    ];

    protected $table = 'churches';

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function members()
    {
        return $this->hasMany(User::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function classrooms()
    {
        return $this->hasMany(Classroom::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function media()
    {
        return $this->hasMany(Media::class);
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function settings()
    {
        return $this->hasOne(ChurchSetting::class);
    }

    public function parentChurch()
    {
        return $this->hasOne(Church::class, 'networks', 'child_church_id', 'parent_church_id');
    }

    public function childrenChurches()
    {
        return $this->belongsToMany(Church::class, 'networks', 'parent_church_id', 'child_church_id');
    }

    public function community()
    {
        return $this->hasOne(Community::class);
    }
}
