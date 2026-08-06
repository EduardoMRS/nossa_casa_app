<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    use HasTranslations, HasUlids;

    protected $fillable = [
        'title',
        'description',
        'schema',
        'church_id',
    ];

    protected $casts = [
        'schema' => 'array', // Transforma o JSON automaticamente em Array/Collection
    ];

    protected $appends = [
        'translations',
    ];

    protected $table = 'forms';

    public function events()
    {
        return $this->morphedByMany(Event::class, 'formable', 'form_relations');
    }

    public function posts()
    {
        return $this->morphedByMany(Post::class, 'formable', 'form_relations');
    }

    public function responses()
    {
        return $this->hasMany(FormResponse::class);
    }

    public function categories()
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    public function getCategoryAttribute()
    {
        return implode(', ', $this->categories()->pluck('name')->toArray());
    }
}
