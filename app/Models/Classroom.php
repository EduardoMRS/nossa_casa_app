<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Classroom extends Model
{
    use HasUlids;

    protected $table = 'classrooms';

    protected $fillable = [
        'name',
        'description',
        'min_age',
        'max_age',
        'max_members',
        'church_id',
        'teacher_id',
    ];
    
    protected $appends = [
        'teacher_details',
        'translations',
    ];

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

    public function assignTeacher(User $user)
    {
        $this->teacher()->associate($user);
        $this->save();
    }

    public function getTeacherDetailsAttribute()
    {
        return $this->teacher ? $this->teacher->only(['id', 'first_name', 'last_name', 'email']) : null;
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
        return $this->morphToMany(Post::class, 'postable');
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getTranslationsAttribute()
    {
        return $this->translations()->pluck('content', 'translatable_column');
    }
}
