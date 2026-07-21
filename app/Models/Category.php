<?php

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasUlids;

    protected $fillable = [
        'church_id',
        'name',
        'slug',
        'type',
    ];

    protected $casts = [
        'type' => CategoryType::class,
    ];

    protected $appends = [
        'translations',
    ];

    protected $table = 'categories';
    
    public function classrooms()
    {
        return $this->morphedByMany(Classroom::class, 'categorizable');
    }
    
    public function events()
    {
        return $this->morphedByMany(Event::class, 'categorizable');
    }

    public function forms()
    {
        return $this->morphedByMany(Form::class, 'categorizable');
    }

    public function media()
    {
        return $this->morphedByMany(Media::class, 'categorizable');
    }

    public function posts()
    {
        return $this->morphedByMany(Post::class, 'categorizable');
    }

    public function users()
    {
        return $this->morphedByMany(User::class, 'categorizable');
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
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
