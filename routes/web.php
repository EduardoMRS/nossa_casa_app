<?php

use App\Http\Controllers\Admin\AdminWorkspaceController;
use App\Http\Controllers\Admin\LibraryVerseController;
use App\Enums\CategoryType;
use App\Http\Controllers\Settings\BrandingController;
use App\Models\Category;
use App\Models\Event;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

if (! function_exists('categoriesForChurchAndType')) {
    function categoriesForChurchAndType(Request $request, string $type)
    {
        $churchId = $request->user()?->church?->id;

        if (! $churchId) {
            return collect();
        }

        return Category::query()
            ->where('church_id', $churchId)
            ->where('type', $type)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type']);
    }
}

Route::get('/', function () {
    $featuredEvents = Event::query()
        ->with('church:id,name,slug')
        ->orderBy('start_time', 'asc')
        ->limit(3)
        ->get()
        ->map(function (Event $event) {
            return [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'excerpt' => Str::limit(strip_tags((string) $event->description), 120),
                'cover_path' => $event->cover_path,
                'start_time' => $event->start_time,
                'church' => $event->church,
            ];
        });

    $latestPosts = Post::query()
        ->orderByDesc('published_at')
        ->orderByDesc('created_at')
        ->limit(3)
        ->get()
        ->map(function (Post $post) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => Str::limit(strip_tags((string) $post->content), 120),
                'published_at' => $post->published_at,
            ];
        });

    return Inertia::render('Home', [
        'stats' => [
            'events' => Event::query()->count(),
            'gallery' => Media::visible()->count(),
            'posts' => Post::query()->count(),
        ],
        'featuredEvents' => $featuredEvents,
        'latestPosts' => $latestPosts,
    ]);
})->name('home');
Route::get('/events', function () {
    $events = Event::query()
        ->with('church:id,name,slug')
        ->orderBy('start_time', 'asc')
        ->paginate(18)
        ->through(function (Event $event) {
            return [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'description' => $event->description,
                'cover_path' => $event->cover_path,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'church' => $event->church,
            ];
        });

    return Inertia::render('Events/Index', ['events' => $events]);
})->name('events.index');

Route::get('/events/{event:slug}/register', function (Event $event, Request $request) {
    $event->load('church:id,name,slug');
    $registrationForm = $event->forms()->select(['forms.id', 'forms.title', 'forms.description', 'forms.schema'])->first();

    abort_if($registrationForm === null, 404);

    $existingResponse = null;

    if ($request->user()) {
        $existingResponse = $registrationForm->responses()
            ->where('user_id', $request->user()->id)
            ->first();
    }

    return Inertia::render('Events/Register', [
        'event' => [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description,
            'description_html' => Str::markdown($event->description ?? '', [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'cover_path' => $event->cover_path,
            'church' => $event->church,
        ],
        'form' => [
            'id' => $registrationForm->id,
            'title' => $registrationForm->title,
            'description' => $registrationForm->description,
            'schema' => $registrationForm->schema,
        ],
        'existingAnswers' => $existingResponse?->answers,
        'alreadyRegistered' => $request->user()
            ? $event->users()->where('users.id', $request->user()->id)->exists()
            : false,
    ]);
})->name('events.register');

Route::get('/events/{event:slug}', function (Event $event, Request $request) {
    $event->load('church:id,name,slug', 'categories:id,name');
    $registrationForm = $event->forms()->select(['forms.id', 'forms.title', 'forms.description'])->first();

    return Inertia::render('Events/Show', [
        'event' => [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description,
            'description_html' => Str::markdown($event->description ?? '', [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'cover_path' => $event->cover_path,
            'church' => $event->church,
            'categories' => $event->categories,
        ],
        'registration' => [
            'has_form' => (bool) $registrationForm,
            'form_id' => $registrationForm?->id,
            'form_title' => $registrationForm?->title,
            'already_registered' => $request->user()
                ? $event->users()->where('users.id', $request->user()->id)->exists()
                : false,
        ],
    ]);
})->name('events.show');

Route::get('/gallery', function (Request $request) {
    $media = Media::visible()
        ->with('uploader:id,first_name,last_name')
        ->orderByDesc('created_at')
        ->paginate(24)
        ->through(function (Media $item) {
            return [
                'id' => $item->id,
                'url' => $item->url,
                'mimetype' => $item->mimetype,
                'size' => $item->size,
                'uploader' => $item->uploader,
                'created_at' => $item->created_at,
            ];
        });

    return Inertia::render('Gallery/Index', [
        'media' => $media,
            'categories' => categoriesForChurchAndType($request, CategoryType::MEDIA->value),
    ]);
})->name('gallery.index');

Route::get('/d/{encryptedFile}', function ($encryptedFile) {
    try {
        // Decrypt the file path
        $filePath = Crypt::decryptString($encryptedFile);
        $file = getFileMetadata($filePath);

        // Check if file exists
        if (! $file['exists']) {
            abort(404);
        }

        // Return the file
        return response()->file($file['path']);

    } catch (Exception $e) {
        abort(403, 'Invalid or corrupted file link.');
    }
})->name('secure-file');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        $role = $user?->role?->value ?? (string) $user?->role;

        $modules = match ($role) {
            'member' => [
                ['title_key' => 'dashboard.module.my_events.title', 'description_key' => 'dashboard.module.my_events.description', 'href' => route('events.index')],
                ['title_key' => 'dashboard.module.community_gallery.title', 'description_key' => 'dashboard.module.community_gallery.description', 'href' => route('gallery.index')],
                ['title_key' => 'dashboard.module.recent_updates.title', 'description_key' => 'dashboard.module.recent_updates.description', 'href' => route('posts.index')],
                ['title_key' => 'dashboard.module.account_settings.title', 'description_key' => 'dashboard.module.account_settings.description', 'href' => route('profile.edit')],
                ['title_key' => 'dashboard.module.security_settings.title', 'description_key' => 'dashboard.module.security_settings.description', 'href' => route('security.edit')],
            ],
            'leader' => [
                ['title_key' => 'dashboard.module.organize_events.title', 'description_key' => 'dashboard.module.organize_events.description', 'href' => route('events.create')],
                ['title_key' => 'dashboard.module.track_registrations.title', 'description_key' => 'dashboard.module.track_registrations.description', 'href' => route('events.index')],
                ['title_key' => 'dashboard.module.publish_updates.title', 'description_key' => 'dashboard.module.publish_updates.description', 'href' => route('posts.index')],
                ['title_key' => 'dashboard.module.account_settings.title', 'description_key' => 'dashboard.module.account_settings.description', 'href' => route('profile.edit')],
                ['title_key' => 'dashboard.module.security_settings.title', 'description_key' => 'dashboard.module.security_settings.description', 'href' => route('security.edit')],
            ],
            'media' => [
                ['title_key' => 'dashboard.module.gallery_curation.title', 'description_key' => 'dashboard.module.gallery_curation.description', 'href' => route('gallery.index')],
                ['title_key' => 'dashboard.module.visual_posts.title', 'description_key' => 'dashboard.module.visual_posts.description', 'href' => route('posts.create')],
                ['title_key' => 'dashboard.module.coverage_schedule.title', 'description_key' => 'dashboard.module.coverage_schedule.description', 'href' => route('events.index')],
                ['title_key' => 'dashboard.module.review_content.title', 'description_key' => 'dashboard.module.review_content.description', 'href' => route('posts.index')],
                ['title_key' => 'dashboard.module.account_settings.title', 'description_key' => 'dashboard.module.account_settings.description', 'href' => route('profile.edit')],
            ],
            'admin', 'superadmin', 'system' => [
                ['title_key' => 'dashboard.module.platform_governance.title', 'description_key' => 'dashboard.module.platform_governance.description', 'href' => route('dashboard')],
                ['title_key' => 'dashboard.module.review_content.title', 'description_key' => 'dashboard.module.review_content.description', 'href' => route('admin.highlights.index')],
                ['title_key' => 'dashboard.module.monitor_events.title', 'description_key' => 'dashboard.module.monitor_events.description', 'href' => route('events.index')],
                ['title_key' => 'dashboard.module.visual_identity.title', 'description_key' => 'dashboard.module.visual_identity.description', 'href' => route('admin.branding.edit')],
                ['title_key' => 'dashboard.module.user_management.title', 'description_key' => 'dashboard.module.user_management.description', 'href' => route('admin.userManagement.index')],
                ['title_key' => 'dashboard.module.multicongregation.title', 'description_key' => 'dashboard.module.multicongregation.description', 'href' => route('admin.multiCongregation.index')],
                ['title_key' => 'dashboard.module.logs_metrics.title', 'description_key' => 'dashboard.module.logs_metrics.description', 'href' => route('admin.logsMetrics.index')],
            ],
            default => [
                ['title_key' => 'dashboard.module.my_events.title', 'description_key' => 'dashboard.module.my_events.description', 'href' => route('events.index')],
                ['title_key' => 'dashboard.module.community_gallery.title', 'description_key' => 'dashboard.module.community_gallery.description', 'href' => route('gallery.index')],
                ['title_key' => 'dashboard.module.recent_updates.title', 'description_key' => 'dashboard.module.recent_updates.description', 'href' => route('posts.index')],
            ],
        };

        return Inertia::render('Dashboard', [
            'role' => $role,
            'kpis' => [
                'events' => Event::query()->count(),
                'gallery' => Media::query()->count(),
                'posts' => Post::query()->count(),
            ],
            'modules' => $modules,
        ]);
    })->name('dashboard');

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:admin|superadmin|system')
        ->group(function () {
            Route::get('/branding', [BrandingController::class, 'edit'])->name('branding.edit');
            Route::put('/branding', [BrandingController::class, 'update'])->name('branding.update');
            Route::get('/categorias', [AdminWorkspaceController::class, 'categories'])->name('categories.index');

            Route::get('/destaques', [AdminWorkspaceController::class, 'highlights'])->name('highlights.index');
            Route::get('/moderar-galeria', [AdminWorkspaceController::class, 'galleryModeration'])->name('galleryModeration.index');
            Route::get('/moderar-mural', [AdminWorkspaceController::class, 'wallModeration'])->name('wallModeration.index');
            Route::get('/biblioteca-versiculo', [LibraryVerseController::class, 'index'])->name('libraryVerse.index');
            Route::post('/biblioteca-versiculo/library', [LibraryVerseController::class, 'storeLibrary'])->name('libraryVerse.library.store');
            Route::put('/biblioteca-versiculo/library/{library}', [LibraryVerseController::class, 'updateLibrary'])->name('libraryVerse.library.update');
            Route::delete('/biblioteca-versiculo/library/{library}', [LibraryVerseController::class, 'destroyLibrary'])->name('libraryVerse.library.destroy');
            Route::put('/biblioteca-versiculo/verse', [LibraryVerseController::class, 'updateVerse'])->name('libraryVerse.verse.update');
            Route::get('/formularios', [AdminWorkspaceController::class, 'forms'])->name('forms.index');
            Route::get('/pedidos-intercessao', [AdminWorkspaceController::class, 'prayerRequests'])->name('prayerRequests.index');
            Route::get('/ministerio-kids', [AdminWorkspaceController::class, 'kidsMinistry'])->name('kidsMinistry.index');
            Route::get('/minhas-oracoes', [AdminWorkspaceController::class, 'myPrayers'])->name('myPrayers.index');
            Route::get('/gestao-usuarios', [AdminWorkspaceController::class, 'userManagement'])->name('userManagement.index');
            Route::get('/multicongregacoes', [AdminWorkspaceController::class, 'multiCongregation'])->name('multiCongregation.index');
            Route::get('/salas-aula', [AdminWorkspaceController::class, 'classrooms'])->name('classrooms.index');
            Route::get('/logs-metricas', [AdminWorkspaceController::class, 'logsMetrics'])->name('logsMetrics.index');
        });

    // // Churches (Igrejas)
    // Route::get('/churches', function () { return Inertia::render('Churches/Index'); })->name('churches.index');
    // Route::get('/churches/create', function () { return Inertia::render('Churches/Create'); })->name('churches.create');
    // Route::get('/churches/{id}/edit', function ($id) { return Inertia::render('Churches/Edit', ['id' => $id]); })->name('churches.edit');

    Route::get('/events/create', function () {
        return Inertia::render('Events/Form', [
            'categories' => categoriesForChurchAndType(request(), CategoryType::EVENT->value),
        ]);
    })->name('events.create');

    Route::get('/events/{event}/edit', function (string $event) {
        $resource = Event::query()->findOrFail($event);

        return Inertia::render('Events/Form', [
            'event' => [
                'id' => $resource->id,
                'title' => $resource->title,
                'slug' => $resource->slug,
                'description' => $resource->description,
                'start_time' => $resource->start_time,
                'end_time' => $resource->end_time,
                'cover_path' => $resource->cover_path,
                'category_ids' => $resource->categories()->pluck('categories.id')->all(),
            ],
            'categories' => categoriesForChurchAndType(request(), CategoryType::EVENT->value),
        ]);
    })->name('events.edit');

    Route::get('/posts', function () {
        $posts = Post::visible()->orderBy('created_at', 'desc')->paginate(10)->through(function ($post) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'content' => $post->content,
                'published_at' => $post->published_at,
                'expires_at' => $post->expires_at,
                'author' => $post->author_details,
                'metrics' => $post->metrics,
                'translations' => $post->translations,
                'media' => $post->medias()->get()->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'name' => $media->name,
                        'url' => $media->url,
                        'type' => $media->type,
                    ];
                }),
            ];
        });

        return Inertia::render('Posts/Index', ['posts' => $posts]);
    })->name('posts.index');
    Route::get('/posts/create', function () {
        return Inertia::render('Posts/Form', [
            'categories' => categoriesForChurchAndType(request(), CategoryType::POST->value),
        ]);
    })->name('posts.create');
    Route::get('/posts/{post}/edit', function ($post) {
        $post = Post::visible()->with(['church', 'medias'])->findOrFail(request()->route('post'));
        $church = $post->church;
        $props = [
            'available_categories' => $church->categories()->get(['id', 'name']),
            'categories' => categoriesForChurchAndType(request(), CategoryType::POST->value),
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'content' => $post->content,
                'published_at' => $post->published_at,
                'expires_at' => $post->expires_at,
                'category_ids' => $post->categories()->pluck('categories.id')->all(),
            ],
        ];

        return Inertia::render('Posts/Form', $props);
    })->name('posts.edit');
    Route::get('/posts/{post}', function () {
        $post = Post::visible()->with(['church', 'medias'])->findOrFail(request()->route('post'));
        $props = [
            'can' => [
                'edit' => auth()->user()->can('update', $post),
                'delete' => auth()->user()->can('delete', $post),
                'comment' => auth()->user()->can('comment', $post),
                'react' => auth()->user()->can('react', $post),
            ],
            'post' => $post,
            'author' => $post->author_details,
            'metrics' => $post->metrics,
            'translations' => $post->translations,
            'media' => $post->medias()->get()->map(function ($media) {
                return [
                    'id' => $media->id,
                    'name' => $media->name,
                    'url' => $media->url,
                    'type' => $media->type,
                ];
            }),
            'comments' => $post->comments()->with('replies')->orderBy('created_at', 'desc')->get(),
            'reactions' => $post->reactions()->get(),
        ];

        return Inertia::render('Posts/Show', $props);
    })->name('posts.show');

    // // Categories (Categorias - Baseado no api.json)
    // Route::get('/categories', function () { return Inertia::render('Categories/Index'); })->name('categories.index');
});

require __DIR__.'/settings.php';
Route::group(['prefix' => 'api'], function () {
    require __DIR__.'/api.php';
});
