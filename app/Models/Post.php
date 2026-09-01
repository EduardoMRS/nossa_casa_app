<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasTranslations, HasUlids;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'published_at',
        'expires_at',
        'author_id',
        'church_id',
        'views_count',
        'is_event_private',
        'visibility',
        'comments_enabled',
        'reactions_enabled',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'views_count' => 'integer',
        'is_event_private' => 'boolean',
        'comments_enabled' => 'boolean',
        'reactions_enabled' => 'boolean',
    ];

    protected $attributes = [
        'views_count' => 0,
        'visibility' => 'public',
        'comments_enabled' => true,
        'reactions_enabled' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (Post $post): void {
            if ($post->is_event_private && $post->visibility === 'public') {
                $post->visibility = 'event_private';
            }
        });
    }

    protected $appends = [
        'translations',
        'author_details',
        'category',
        'metrics',
    ];

    protected $hidden = ['author'];

    public function author() { return $this->belongsTo(User::class, 'author_id'); }
    public function getAuthorDetailsAttribute() { return $this->author?->details ?? null; }
    public function isAuthor(User|int $user) { return $this->author_id === ($user instanceof User ? $user->id : $user); }
    public function church() { return $this->belongsTo(Church::class); }
    public function categories() { return $this->morphToMany(Category::class, 'categorizable'); }

    public function getCategoryAttribute()
    {
        $categories = $this->relationLoaded('categories') ? $this->getRelation('categories') : $this->categories()->get();
        return $categories->pluck('name')->join(', ');
    }

    public function medias() { return $this->morphToMany(Media::class, 'mediable'); }
    public function forms() { return $this->morphToMany(Form::class, 'formable', 'form_relations'); }
    public function comments() { return $this->morphMany(Comment::class, 'commentable'); }
    public function reactions() { return $this->morphMany(Reaction::class, 'reactionable'); }

    public function scopeVisible($query)
    {
        $user = auth()->user();
        $role = $user?->role?->value ?? (string) $user?->role;

        if ($user && in_array($role, ['leader', 'media', 'church_leader', 'superadmin', 'system'], true)) {
            if ($role === 'system') return $query;
            if ($churchId = $user->profile?->church_id) return $query->where('church_id', $churchId);
        }

        $query->published();
        if ($churchId = $user?->profile?->church_id) $query->where('church_id', $churchId);
        return $query;
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopePublic($query) { return $query->where('visibility', 'public'); }
    public function scopeExpired($query) { return $query->whereNotNull('expires_at')->where('expires_at', '<=', now()); }

    public function getMetricsAttribute()
    {
        return [
            'comments_count' => $this->comments()->count(),
            'reactions_count' => $this->reactions()->count(),
        ];
    }

    public function classrooms() { return $this->morphedByMany(Classroom::class, 'postable'); }
    public function privateEvents() { return $this->belongsToMany(Event::class, 'event_posts'); }
    public function highlight() { return $this->morphOne(Highlight::class, 'highlightable'); }
}
