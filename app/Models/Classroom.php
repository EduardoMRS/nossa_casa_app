<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = [
        'church_id',
        'name',
        'description',
        'teacher_id',
    ];

    protected $table = 'classrooms';

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function categories()
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'classroom_users');
    }

    public function presences()
    {
        return $this->belongsToMany(User::class, 'classroom_presences')->withPivot('check_in', 'check_out');
    }

    public function posts()
    {
        return $this->morphMany(Post::class, 'postable');
    }
}
