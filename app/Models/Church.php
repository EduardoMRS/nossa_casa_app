<?php

namespace App\Models;

use App\Enums\ChurchStatus;
use App\Observers\ChurchObserver;
use App\Traits\HasTranslations;
use Database\Factories\ChurchFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy(ChurchObserver::class)]
class Church extends Model
{
    /** @use HasFactory<ChurchFactory> */
    use HasFactory, HasTranslations, HasUlids;

    protected $table = 'churches';

    protected $fillable = [
        'name',
        'slug',
        'domain',
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

    public function liveStreams()
    {
        return $this->hasMany(LiveStream::class);
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /** @return HasOne<Setting, $this> */
    public function settings(): HasOne
    {
        return $this->hasOne(Setting::class);
    }

    /** @return HasOne<ChurchMailSetting, $this> */
    public function mailSetting(): HasOne
    {
        return $this->hasOne(ChurchMailSetting::class);
    }

    public function parentChurch()
    {
        return $this->hasOne(Church::class, 'id', 'parent_church_id');
    }

    public function childrenChurches()
    {
        return $this->belongsToMany(Church::class, 'networks', 'parent_church_id', 'child_church_id');
    }

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function registrationRequests()
    {
        return $this->hasMany(ChurchRegistrationRequest::class, 'approved_church_id');
    }

    public function library()
    {
        return $this->hasMany(Library::class);
    }

    public function getRtmpUrlAttribute(): ?string
    {
        $configuredUrl = rtrim((string) config('media.mediamtx.public_rtmp_url'), '/');

        if (! $this->domain) {
            return $configuredUrl !== '' ? $configuredUrl : null;
        }

        $rtmpPort = parse_url($configuredUrl, PHP_URL_PORT);

        return "rtmp://{$this->domain}".($rtmpPort ? ":{$rtmpPort}" : '');
    }
}
