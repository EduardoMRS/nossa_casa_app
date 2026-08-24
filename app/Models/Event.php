<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasTranslations, HasUlids;

    protected $fillable = [
        'title',
        'slug',
        'tags',
        'description',
        'start_time',
        'end_time',
        'price',
        'cover_path',
        'church_id',
        'author_id',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'tags' => 'array',
        'price' => 'decimal:2',
    ];

    protected $appends = [
        'category',
        'cover_url',
        'translations',
    ];

    public function getCoverUrlAttribute(): ?string
    {
        return genUrl($this->getRawOriginal('cover_path'));
    }

    public function forms()
    {
        // Vincula este evento a qualquer formulário via FormRelation
        return $this->morphToMany(Form::class, 'formable', 'form_relations');
    }

    public function categories()
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    public function medias()
    {
        return $this->morphToMany(Media::class, 'mediable');
    }

    public function getCategoryAttribute()
    {
        $categories = $this->relationLoaded('categories')
            ? $this->getRelation('categories')
            : $this->categories()->get();

        return $categories->pluck('name')->join(', ');
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'event_users')
            ->using(EventUser::class);
    }

    /** @return HasMany<EventUser, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventUser::class);
    }

    /** @return BelongsToMany<Post, $this> */
    public function privatePosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'event_posts');
    }

    /** @return BelongsToMany<User, $this> */
    public function responsibleUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_responsibles');
    }

    /** @return HasMany<EventMaterial, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(EventMaterial::class);
    }

    public function confirmations()
    {
        return $this->hasMany(EventConfirmation::class);
    }

    public function userConfirm(User $user)
    {
        $this->confirmations()->updateOrCreate(
            ['user_id' => $user->id]
        );
    }

    public function userUnConfirm(User $user)
    {
        $this->confirmations()->where('user_id', $user->id)->delete();
    }

    public function userCheckIn(User $user)
    {
        $this->confirmations()->updateOrCreate(
            ['user_id' => $user->id],
            ['check_in_at' => now()]
        );
    }

    public function userCheckOut(User $user)
    {
        $this->confirmations()->updateOrCreate(
            ['user_id' => $user->id],
            ['check_out_at' => now()]
        );
    }

    public function checkinsIn()
    {
        return $this->confirmations()->whereNotNull('check_in_at');
    }

    public function checkinsOut()
    {
        return $this->confirmations()->whereNotNull('check_out_at');
    }

    public function noCheckinsIn()
    {
        return $this->confirmations()->whereNull('check_in_at');
    }

    public function noCheckinsOut()
    {
        return $this->confirmations()->whereNull('check_out_at');
    }

    public function address()
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function highlight()
    {
        return $this->morphOne(Highlight::class, 'highlightable');
    }
}
