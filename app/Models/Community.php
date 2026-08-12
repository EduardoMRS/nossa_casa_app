<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class Community extends Model
{
    use HasRelationships, HasTranslations, HasUlids;

    protected $table = 'communities';

    protected $fillable = [
        'owner_id',
        'slug',
        'name',
        'description',
        'found_date',
        'logo_path',
    ];

    protected $appends = [
        'translations',
    ];

    public function churches()
    {
        return $this->hasMany(Church::class, 'community_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function churchRegistrationRequests()
    {
        return $this->hasMany(ChurchRegistrationRequest::class);
    }

    public function assignChurch(Church $church): void
    {
        $church->update(['community_id' => $this->id]);
    }

    public function members()
    {
        return $this->hasManyDeep(
            User::class,
            [Church::class, UserProfile::class],
            ['community_id', 'church_id', 'id'],
            ['id', 'id', 'user_id']
        );
    }

    public function categories()
    {
        return $this->hasManyThrough(Category::class, Church::class, 'community_id', 'church_id', 'id', 'id');
    }
}
