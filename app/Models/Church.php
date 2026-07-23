<?php

namespace App\Models;

use App\Enums\ChurchStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Church extends Model
{
    use HasUlids;

    protected $table = 'churches';

    protected $fillable = [
        'name',
        'slug',
        'community_id',
        'status',
        'found_date',
    ];

    protected $casts = [
        'status' => ChurchStatus::class,
        'found_date' => 'date',
    ];

    protected $appends = [
        'translations',
    ];

    public function address()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function members()
    {
        return $this->hasManyThrough(User::class, UserProfile::class, 'church_id', 'id', 'id', 'user_id');
    }

    public function assignMember(User $user): void
    {
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['church_id' => $this->id]
        );
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function assignCategory(Category $category): void
    {
        $this->categories()->updateOrCreate(
            ['categorizable_id' => $this->id, 'categorizable_type' => self::class, 'category_id' => $category->id],
            ['categorizable_id' => $this->id, 'categorizable_type' => self::class, 'category_id' => $category->id]
        );
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

    public function forms()
    {
        return $this->hasMany(Form::class);
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
        return $this->hasOne(Setting::class);
    }

    public function parentChurch()
    {
        return $this->hasOne(Church::class, 'id', 'parent_church_id');
    }

    public function childrenChurches()
    {
        return $this->belongsToMany(Church::class, 'networks', 'parent_church_id', 'child_church_id');
    }

    public function community()
    {
        return $this->belongsTo(Community::class);
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
