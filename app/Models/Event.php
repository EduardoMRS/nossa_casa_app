<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Event extends Model {
    use HasUlids;

    protected $fillable = [
        'church_id',
        'author_id',
        'title',
        'slug',
        'tags',
        'description',
        'start_time',
        'end_time',
        'cover_path',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'tags' => 'array',
    ];

    public function forms() {
        // Vincula este evento a qualquer formulário via FormRelation
        return $this->morphToMany(Form::class, 'formable', 'form_relations');
    }

    public function categories() {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    public function church() {
        return $this->belongsTo(Church::class);
    }

    public function author() {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function users() {
        return $this->hasMany(EventUser::class);
    }

    public function confirmations() {
        return $this->belongsToMany(User::class, 'event_confirmations', 'event_id', 'user_id', 'id', 'id')->withPivot('status');
    }

    public function address() {
        return $this->morphOne(Address::class, 'addressable');
    }
}
