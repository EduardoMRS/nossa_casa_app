<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Form extends Model
{
    use HasUlids;
    
    protected $fillable = ['title', 'schema'];

    protected $casts = [
        'schema' => 'array', // Transforma o JSON automaticamente em Array/Collection
    ];

    protected $table = 'forms';

    public function events() {
        return $this->morphedByMany(Event::class, 'formable', 'form_relations');
    }

    public function posts() {
        return $this->morphedByMany(Post::class, 'formable', 'form_relations');
    }

    public function responses() {
        return $this->hasMany(FormResponse::class);
    }
}
