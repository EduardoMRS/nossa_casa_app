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

    protected $table = 'categories';
    
    public function classrooms()
    {
        return $this->morphMany(Classroom::class, 'categorizable');
    }
    
    public function events()
    {
        return $this->morphMany(Event::class, 'categorizable');
    }

    public function forms()
    {
        return $this->morphMany(Form::class, 'categorizable');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'categorizable');
    }

    public function posts()
    {
        return $this->morphMany(Post::class, 'categorizable');
    }

    public function users()
    {
        return $this->morphMany(User::class, 'categorizable');
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }
}
