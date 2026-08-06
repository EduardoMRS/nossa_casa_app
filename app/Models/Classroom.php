<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    use HasTranslations, HasUlids;

    protected $table = 'classrooms';

    protected $fillable = [
        'name',
        'description',
        'min_age',
        'max_age',
        'gender_restriction',
        'is_kids',
        'max_members',
        'church_id',
        'teacher_id',
    ];

    protected $appends = [
        'teacher_details',
        'translations',
    ];

    protected function casts(): array
    {
        return [
            'is_kids' => 'boolean',
        ];
    }

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

    public function presences(): HasMany
    {
        return $this->hasMany(ClassroomPresence::class);
    }

    public function posts()
    {
        return $this->morphToMany(Post::class, 'postable');
    }
}
