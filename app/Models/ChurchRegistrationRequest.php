<?php

namespace App\Models;

use Database\Factories\ChurchRegistrationRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChurchRegistrationRequest extends Model
{
    /** @use HasFactory<ChurchRegistrationRequestFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'requester_id',
        'community_id',
        'requested_parent_church_id',
        'reviewed_by',
        'approved_church_id',
        'name',
        'slug',
        'domain',
        'description',
        'found_date',
        'contact_email',
        'contact_phone',
        'address',
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
        'locale',
        'status',
        'review_notes',
        'reviewed_at',
        'proof_document_path',
        'proof_document_name',
        'proof_document_mime',
    ];

    protected $casts = [
        'found_date' => 'date',
        'reviewed_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function requestedParentChurch()
    {
        return $this->belongsTo(Church::class, 'requested_parent_church_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedChurch()
    {
        return $this->belongsTo(Church::class, 'approved_church_id');
    }
}
