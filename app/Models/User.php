<?php
namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use App\Enums\UserRole;
use App\Enums\UserRelationships;

class User extends Authenticatable implements PasskeyUser
{
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable, HasUlids;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'birth_date',
        'role',
        'church_id',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
    
}
