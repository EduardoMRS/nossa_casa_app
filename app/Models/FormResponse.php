<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class FormResponse extends Model
{
    use HasUlids;

    protected $fillable = [
        'form_id',
        'user_id',
        'answers'
    ];
    protected $casts = [
        'answers' => 'array', // Transforma o JSON automaticamente em Array/Collection
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
