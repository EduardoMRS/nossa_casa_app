<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = [
        'country',
        'state',
        'city',
        'neighborhood',
        'street',
        'number',
        'complement',
        'zipcode',
        'addressable_type',
        'addressable_id'
    ];

    protected $table = 'addresses';

    public function user()
    {
        return $this->morphTo(User::class, 'addressable');
    }

    public function church()
    {
        return $this->morphTo(Church::class, 'addressable');
    }

    public function event()
    {
        return $this->morphTo(Event::class, 'addressable');
    }
}
