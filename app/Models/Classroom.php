<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Classroom extends Model
{
    use HasTranslations, HasUlids;

    protected $table = 'classrooms';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover_path',
        'accent_color',
        'portal_enabled',
        'portal_settings',
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
            'portal_enabled' => 'boolean',
            'portal_settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Classroom $classroom): void {
            if ($classroom->slug) {
                return;
            }

            $base = Str::slug($classroom->name) ?: 'classroom';
            $slug = $base;
            $suffix = 2;

            while (static::query()
                ->where('church_id', $classroom->church_id)
                ->where('slug', $slug)
                ->exists()) {
                $slug = $base.'-'.$suffix++;
            }

            $classroom->slug = $slug;
        });
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

    public function activities(): HasMany
    {
        return $this->hasMany(ClassroomActivity::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ClassroomMaterial::class);
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(ClassroomDiscussion::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->teacher_id === $user->id
            || $this->members()->whereKey($user->id)->exists();
    }

    public function canBeManagedBy(User $user): bool
    {
        if ($user->role === UserRole::SYSTEM) {
            return true;
        }

        if ($this->teacher_id === $user->id) {
            return true;
        }

        return $user->church?->id === $this->church_id
            && in_array($user->role, [
                UserRole::LEADER,
                UserRole::CHURCH_LEADER,
                UserRole::SUPERADMIN,
            ], true);
    }
}
