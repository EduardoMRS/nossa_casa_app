<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PrayerRequest extends Model
{
    use HasUlids;

    protected $fillable = [
        'content',
        'user_id',
        'church_id',
    ];

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getIsAnonymousAttribute()
    {
        return $this->user ? false : true;
    }
}
