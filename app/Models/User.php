<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRelationships;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    use HasFactory, HasUlids, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'birth_date',
        'role',
    ];

    protected $appends = [
        'name',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'updated_at',
        'created_at',
        'email_verified_at',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
        'birth_date' => 'date',
        'role' => UserRole::class, // Cast automático para o Enum
    ];

    public function church()
    {
        return $this->hasOneThrough(Church::class, UserProfile::class, 'user_id', 'id', 'id', 'church_id');
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    public function medias()
    {
        return $this->hasMany(Media::class, 'uploader_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions()
    {
        return $this->hasMany(Reaction::class, 'user_id');
    }

    public function relationships()
    {
        return $this->hasMany(UserRelationship::class, 'user_id');
    }

    public function relatedRelationships()
    {
        return $this->hasMany(UserRelationship::class, 'related_user_id');
    }

    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class, 'classroom_users');
    }

    public function registeredEvents()
    {
        return $this->belongsToMany(Event::class, 'event_users')->withPivot('status');
    }

    public function scopeFamily($query)
    {
        return $query->whereHas(UserRelationship::class, function ($q) {
            $q->whereIn('relationship_type', UserRelationship::FAMILY);
        });
    }

    public function scopeBlocked($query)
    {
        return $query->whereHas(UserRelationship::class, function ($q) {
            $q->where('relationship_type', UserRelationships::BLOCKED);
        });
    }

    public function assignRole(string $role): void
    {
        $this->role = UserRole::from($role);
        $this->save();
    }

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getAvatarAttribute(): ?string
    {
        $avatarPath = $this->profile?->avatar_path;

        return $avatarPath ? genUrl($avatarPath) : null;
    }

    /**
     * Check if the user has a specific role or any of the roles in an array.
     *
     * @param  string|array|UserRole  $role  role or array of roles to check against
     */
    public function hasRole($role): bool
    {
        if (is_array($role)) {
            return in_array($this->role, array_map(fn ($r) => UserRole::from($r), $role));
        }

        return $this->role === UserRole::from($role);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole([UserRole::ADMIN, UserRole::SUPERADMIN]);
    }

    public function isModerator(): bool
    {
        return $this->hasRole([UserRole::LEADER, UserRole::MEDIA]);
    }

    public function isMember(): bool
    {
        return $this->hasRole(UserRole::MEMBER);
    }

    public function getDetailsAttribute()
    {
        return $this->only(
            [
                'id',
                'first_name',
                'last_name',
                'email',
            ]);
    }
}
