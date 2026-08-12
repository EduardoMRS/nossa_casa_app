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
        'status',
        'review_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'found_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function community()
    {
        return $this->belongsTo(Community::class);
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
