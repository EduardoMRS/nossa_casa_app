<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Event extends Model {
    use HasUlids;

    protected $fillable = [
        'title',
        'slug',
        'tags',
        'description',
        'start_time',
        'end_time',
        'cover_path',
        'church_id',
        'author_id',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'tags' => 'array',
    ];
    protected $appends = [
        'category',
        'translations',
    ];

    public function forms()
    {
        // Vincula este evento a qualquer formulário via FormRelation
        return $this->morphToMany(Form::class, 'formable', 'form_relations');
    }

    public function categories()
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    public function getCategoryAttribute()
    {
        return join(', ', $this->categories()->pluck('name')->toArray());
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

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getTranslationsAttribute()
    {
        return collect($this->translations()->get())->map(function ($translation) {
            return [
                'locale' => $translation['locale'],
                'content' => $translation['content'],
                'translatable_column' => $translation['translatable_column'],
            ];
        });
    }
}
