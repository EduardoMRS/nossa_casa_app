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

    public function getToStringAttribute()
    {
        $address = $this->address?->first();

        if (!$address) {
            return null;
        }

        if (
            blank($address->street) ||
            blank($address->city) ||
            blank($address->state) ||
            blank($address->zip_code) ||
            blank($address->country)
        ) {
            return null;
        }

        $street = collect([
            $address->street,
            $address->number,
        ])->filter(fn ($value) => filled($value))->implode(' ');

        return collect([
            $street,
            $address->complement,
            $address->neighborhood,
            $address->city,
            $address->state,
            $address->zip_code,
            $address->country,
        ])->filter(fn ($value) => filled($value))->implode(', ');
    }
}
