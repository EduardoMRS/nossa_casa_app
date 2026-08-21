<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Database\Factories\CommunityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class Community extends Model
{
    /** @use HasFactory<CommunityFactory> */
    use HasFactory, HasRelationships, HasTranslations, HasUlids;

    protected $table = 'communities';

    protected $fillable = [
        'owner_id',
        'slug',
        'name',
        'description',
        'found_date',
        'logo_path',
        'bible_versions',
        'default_bible_version',
    ];

    protected $casts = [
        'found_date' => 'date',
        'bible_versions' => 'array',
    ];

    protected $appends = [
        'translations',
    ];

    /** @return HasMany<Church, $this> */
    public function churches(): HasMany
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
