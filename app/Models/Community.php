<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;
class Community extends Model
{
    use HasRelationships, HasUlids;
    
    protected $fillable = [
        'slug',
        'name',
        'description',
        'found_date',
        'logo_path',
    ];

    protected $table = 'communities';

    public function churches()
    {
        return $this->hasMany(Church::class, 'community_id');
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
