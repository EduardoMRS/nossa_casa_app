<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasUlids;

    protected $fillable = [
        'addressable_type',
        'addressable_id',
        'country',
        'state',
        'city',
        'neighborhood',
        'street',
        'number',
        'complement',
        'zipcode',
        'latitude',
        'longitude',
    ];

    protected $table = 'addresses';

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function addressable()
    {
        return $this->morphTo();
    }
}
