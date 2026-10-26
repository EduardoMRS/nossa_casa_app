<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * A church group such as a cell or Bible study, organized by category, with an
 * optional meeting place and one or more responsible users.
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, HasTranslations, HasUlids;

    protected $fillable = [
        'church_id',
        'name',
        'slug',
        'description',
        'is_public',
    ];

    protected $appends = [
        'translations',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Church, $this>
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * @return MorphToMany<Category, $this>
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    /**
     * @return MorphOne<Address, $this>
     */
    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function responsibleUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user')->withTimestamps();
    }
}
