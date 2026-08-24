<?php

use App\Enums\CategoryType;
use App\Enums\UserRole;
use App\Http\Controllers\Admin\AdminWorkspaceController;
use App\Http\Controllers\Admin\LibraryVerseController;
use App\Http\Controllers\Admin\LiveStreamControlController;
use App\Http\Controllers\Admin\StopLiveStreamController;
use App\Http\Controllers\BrandingAssetController;
use App\Http\Controllers\ChurchOnboardingController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\PortalCommunityController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PublicGalleryController;
use App\Http\Controllers\PublicLibraryController;
use App\Http\Controllers\PublicLiveStreamController;
use App\Http\Controllers\PublicPostController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\SearchIndexController;
use App\Http\Controllers\Settings\BrandingController;
use App\Models\Category;
use App\Models\Church;
use App\Models\Event;
use App\Models\Form;
use App\Models\Media;
use App\Models\Post;
use App\Queries\EventQuery;
use App\Support\ChurchDomainContext;
use App\Support\S3TemporaryUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::get('/.well-known/nossa-casa.json', DiscoveryController::class)
    ->middleware('throttle:discovery')
    ->name('discovery');

if (! function_exists('categoriesForChurchAndType')) {
    function categoriesForChurchAndType(Request $request, string $type, bool $localized = false): Collection
    {
        $churchId = $request->user()?->church?->id;

        if (! $churchId) {
            return collect();
        }

        $categories = Category::query()
            ->where('church_id', $churchId)
            ->where('type', $type)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type']);

        return $localized
            ? $categories->each->localize()
            : $categories->each->makeHidden('translations');
    }
}

Route::get('/', [PortalController::class, 'index'])->name('home');
Route::get('/sitemap.xml', [SearchIndexController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SearchIndexController::class, 'robots'])->name('robots');
Route::get('/communities/{community:slug}', PortalCommunityController::class)->name('communities.show');
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');
Route::get('/branding/logo', [BrandingAssetController::class, 'logo'])->name('branding.logo');
Route::get('/branding/icon.svg', [BrandingAssetController::class, 'icon'])->name('branding.icon');
Route::get('/favicon.ico', [BrandingAssetController::class, 'icon'])->name('branding.favicon');
Route::get('/favicon.svg', [BrandingAssetController::class, 'icon'])->name('branding.favicon-svg');
Route::get('/apple-touch-icon.png', [BrandingAssetController::class, 'logo'])->name('branding.apple-touch-icon');

Route::get('/auth/handoff', [ChurchOnboardingController::class, 'handoff'])->name('church.auth.handoff');

Route::middleware('auth')->group(function () {
    Route::post('/api/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/api/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    Route::post('/onboarding/communities', [ChurchOnboardingController::class, 'storeCommunity'])->name('onboarding.communities.store');
    Route::post('/onboarding/churches', [ChurchOnboardingController::class, 'storeChurchRequest'])->name('onboarding.churches.store');
    Route::post('/onboarding/churches/{registrationRequest}/approve', [ChurchOnboardingController::class, 'approve'])->name('onboarding.churches.approve');
    Route::post('/onboarding/churches/{registrationRequest}/reject', [ChurchOnboardingController::class, 'reject'])->name('onboarding.churches.reject');
    Route::get('/onboarding/churches/{registrationRequest}/proof', [ChurchOnboardingController::class, 'proofDocument'])->name('onboarding.churches.proof');
    Route::post('/church-membership/switch', [ChurchOnboardingController::class, 'switchMembership'])->name('church.membership.switch');
    Route::post('/church-membership/decline', [ChurchOnboardingController::class, 'declineMembership'])->name('church.membership.decline');
});

Route::get('church/{church_id}/library/{file_path}', function ($church_id, $file_path) {
    $domainChurchId = app(ChurchDomainContext::class)->churchId();
    abort_if($domainChurchId && $domainChurchId !== $church_id, 404);
    $filePathFull = "church/{$church_id}/library/{$file_path}";
    $library = Church::find($church_id)->library()->where('file_path', $filePathFull)
        ->firstOrFail();

    $library->localize();

    return redirect()->to(genUrl($library->getRawOriginal('file_path')));
})->name('library.show');

Route::get('/events', function (EventQuery $events) {
    return Inertia::render('Events/Index', $events->index(
        app(ChurchDomainContext::class)->churchId(),
    )->toArray());
})->name('events.index');

Route::get('/posts', [PublicPostController::class, 'index'])->name('posts.public.index');

Route::get('/posts/{slug}', [PublicPostController::class, 'show'])->name('posts.public.show');

Route::get('/biblioteca', [PublicLibraryController::class, 'index'])->name('library.index');
Route::get('/biblioteca/biblia', [PublicLibraryController::class, 'bible'])->name('library.bible');

Route::get('/events/{event:slug}/register', function (Event $event, Request $request) {
    abort_if(app(ChurchDomainContext::class)->churchId() && $event->church_id !== app(ChurchDomainContext::class)->churchId(), 404);
    $event->load('church:id,name,slug');
    $registrationForm = $event->forms()->select(['forms.id', 'forms.title', 'forms.description', 'forms.schema'])->first();

    abort_if($registrationForm === null, 404);

    $event->localize(relations: ['church']);
    $registrationForm->localize();

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
            'cover_path' => $event->cover_url,
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

Route::get('/events/{event:slug}', function (Event $event, Request $request, EventQuery $events) {
    return Inertia::render('Events/Show', $events->show(
        $event->slug,
        app(ChurchDomainContext::class)->churchId(),
        $request->user(),
    )->toArray());
})->name('events.show');

Route::get('/gallery', [PublicGalleryController::class, 'index'])->name('gallery.index');
Route::get('/gallery/{media}/download', [PublicGalleryController::class, 'download'])->name('gallery.download');
Route::get('/transmissoes/{liveStream}', [PublicLiveStreamController::class, 'show'])->name('live-streams.show');

Route::get('/d/{encryptedFile}', function (string $encryptedFile, S3TemporaryUrlGenerator $temporaryUrlGenerator) {
    try {
        $payload = json_decode(Crypt::decryptString($encryptedFile), true, flags: JSON_THROW_ON_ERROR);
        $filePath = $payload['path'] ?? null;
        $diskName = $payload['disk'] ?? null;
    } catch (Throwable) {
        abort(403, 'Invalid or corrupted file link.');
    }

    abort_if(
        ! is_string($filePath)
            || $filePath === ''
            || str_contains($filePath, '..')
            || str_starts_with($filePath, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $filePath) === 1
            || filter_var($filePath, FILTER_VALIDATE_URL),
        404,
    );

    abort_unless(in_array($diskName, config('filesystems.temporary_url_disks', []), true), 404);

    $disk = Storage::disk($diskName);
    abort_unless($disk->exists($filePath), 404);

    $headers = [
        'Content-Type' => $disk->mimeType($filePath) ?: 'application/octet-stream',
        'Content-Disposition' => 'inline; filename="'.basename($filePath).'"',
        'Cache-Control' => 'private, max-age=3600',
    ];

    if (config("filesystems.disks.{$diskName}.driver") === 's3') {
        return redirect()->away($temporaryUrlGenerator->generate($diskName, $filePath, now()->addMinutes(5), [
            'ResponseContentDisposition' => $headers['Content-Disposition'],
        ]));
    }

    if (config("filesystems.disks.{$diskName}.driver") === 'local') {
        return response()->file($disk->path($filePath), $headers);
    }

    return response()->stream(function () use ($disk, $filePath): void {
        $stream = $disk->readStream($filePath);

        if (is_resource($stream)) {
            fpassthru($stream);
            fclose($stream);
        }
    }, 200, $headers);
})->middleware('signed')->name('secure-file');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        $role = $user?->role?->value ?? (string) $user?->role;
        $domainChurch = app(ChurchDomainContext::class)->church();
        $isGlobalAdministrator = in_array($user?->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true);
        $dashboardChurch = $domainChurch ?? ($isGlobalAdministrator ? null : $user?->church);

        $modules = match ($role) {
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
            'church_leader', 'superadmin', 'system' => [
                ['title_key' => 'dashboard.module.platform_governance.title', 'description_key' => 'dashboard.module.platform_governance.description', 'href' => route('dashboard')],
                ['title_key' => 'dashboard.module.review_content.title', 'description_key' => 'dashboard.module.review_content.description', 'href' => route('admin.highlights.index')],
                ['title_key' => 'dashboard.module.monitor_events.title', 'description_key' => 'dashboard.module.monitor_events.description', 'href' => route('admin.events.index')],
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

        if ($dashboardChurch === null) {
            $modules = array_values(array_filter(
                $modules,
                fn (array $module): bool => $module['href'] !== route('admin.branding.edit'),
            ));
        }

        return Inertia::render('Dashboard', [
            'role' => $role,
            'kpis' => [
                'events' => Event::query()->when($dashboardChurch, fn ($query, Church $church) => $query->where('church_id', $church->id))->count(),
                'gallery' => Media::query()->when($dashboardChurch, fn ($query, Church $church) => $query->where('church_id', $church->id))->count(),
                'posts' => Post::query()->when($dashboardChurch, fn ($query, Church $church) => $query->where('church_id', $church->id))->count(),
            ],
            'modules' => $modules,
        ]);
    })->middleware('role:leader|media|church_leader|superadmin|system')->name('dashboard');

    Route::get('/minhas-oracoes', [AdminWorkspaceController::class, 'myPrayers'])
        ->middleware('role:member|leader|media|church_leader|superadmin|system')
        ->name('myPrayers.index');

    Route::get('/dashboard/transmissoes', [LiveStreamControlController::class, 'index'])
        ->middleware('role:media|church_leader|superadmin|system')
        ->name('admin.liveStreams.index');

    Route::prefix('dashboard')
        ->name('admin.')
        ->middleware('role:church_leader|superadmin|system')
        ->group(function () {
            Route::redirect('/branding', '/dashboard/configuracoes-church');
            Route::get('/configuracoes-church', [BrandingController::class, 'edit'])->name('branding.edit');
            Route::put('/configuracoes-church', [BrandingController::class, 'update'])->name('branding.update');
            Route::get('/categorias', [AdminWorkspaceController::class, 'categories'])->name('categories.index');

            Route::get('/destaques', [AdminWorkspaceController::class, 'highlights'])->name('highlights.index');
            Route::get('/eventos', [AdminWorkspaceController::class, 'events'])->name('events.index');
            Route::get('/moderar-galeria', [AdminWorkspaceController::class, 'galleryModeration'])->name('galleryModeration.index');
            Route::get('/moderar-mural', [AdminWorkspaceController::class, 'wallModeration'])->name('wallModeration.index');
            Route::get('/biblioteca-versiculo', [LibraryVerseController::class, 'index'])->name('libraryVerse.index');
            Route::post('/biblioteca-versiculo/library', [LibraryVerseController::class, 'storeLibrary'])->name('libraryVerse.library.store');
            Route::put('/biblioteca-versiculo/library/{library}', [LibraryVerseController::class, 'updateLibrary'])->name('libraryVerse.library.update');
            Route::delete('/biblioteca-versiculo/library/{library}', [LibraryVerseController::class, 'destroyLibrary'])->name('libraryVerse.library.destroy');
            Route::put('/biblioteca-versiculo/verse', [LibraryVerseController::class, 'updateVerse'])->name('libraryVerse.verse.update');
            Route::put('/biblioteca-versiculo/bible', [LibraryVerseController::class, 'updateBiblePreferences'])->name('libraryVerse.bible.update');
            Route::get('/formularios', [AdminWorkspaceController::class, 'forms'])->name('forms.index');
            Route::get('/formularios/criar', [AdminWorkspaceController::class, 'formCreate'])->name('forms.create');
            Route::get('/formularios/{form}/editar', [AdminWorkspaceController::class, 'formEdit'])->name('forms.edit');
            Route::redirect('/pedidos-intercessao', '/dashboard/minhas-oracoes')->name('prayerRequests.index');
            Route::get('/ministerio-kids', [AdminWorkspaceController::class, 'kidsMinistry'])->name('kidsMinistry.index');
            Route::redirect('/minhas-oracoes', '/minhas-oracoes')->name('myPrayers.index');
            Route::get('/gestao-usuarios', [AdminWorkspaceController::class, 'userManagement'])->name('userManagement.index');
            Route::get('/multicongregacoes', [AdminWorkspaceController::class, 'multiCongregation'])->name('multiCongregation.index');
            Route::get('/salas-aula', [AdminWorkspaceController::class, 'classrooms'])->name('classrooms.index');
            Route::put('/salas-aula/configuracoes', [AdminWorkspaceController::class, 'updateClassroomSettings'])->name('classrooms.settings.update');
            Route::post('/gestao-usuarios/{user}/redefinir-senha', [AdminWorkspaceController::class, 'sendPasswordReset'])->name('userManagement.passwordReset');
            Route::get('/logs-metricas', [AdminWorkspaceController::class, 'logsMetrics'])->middleware('role:superadmin|system')->name('logsMetrics.index');
            Route::get('/logs-metricas/backup/exportar', [AdminWorkspaceController::class, 'exportBackup'])
                ->middleware('role:superadmin|system')
                ->name('logsMetrics.backup.export');
            Route::post('/logs-metricas/backup/importar', [AdminWorkspaceController::class, 'importBackup'])
                ->middleware('role:superadmin|system')
                ->name('logsMetrics.backup.import');
            Route::post('/logs-metricas/transmissoes/{liveStream}/derrubar', StopLiveStreamController::class)
                ->middleware('role:system')
                ->name('logsMetrics.liveStreams.stop');
        });

    Route::get('/dashboard/salas-aula/presencas/{presence}/etiquetas', [ClassroomController::class, 'labels'])
        ->middleware('role:leader|church_leader|superadmin|system')
        ->name('admin.classrooms.labels');

    // // Churches (Igrejas)
    // Route::get('/churches', function () { return Inertia::render('Churches/Index'); })->name('churches.index');
    // Route::get('/churches/create', function () { return Inertia::render('Churches/Create'); })->name('churches.create');
    // Route::get('/churches/{id}/edit', function ($id) { return Inertia::render('Churches/Edit', ['id' => $id]); })->name('churches.edit');

    Route::get('/dashboard/eventos/criar', function () {
        return Inertia::render('Events/Form', [
            'categories' => categoriesForChurchAndType(request(), CategoryType::EVENT->value),
            'forms' => Form::query()->where('church_id', request()->user()?->church?->id)->orderBy('title')->get(['id', 'title', 'description']),
            'returnUrl' => request()->user()?->role?->value === 'leader' ? route('events.index') : route('admin.events.index'),
        ]);
    })->middleware('role:leader|church_leader|superadmin|system')->name('events.create');

    Route::get('/dashboard/eventos/{event}/editar', function (string $event) {
        $resource = Event::query()->findOrFail($event);
        abort_unless($resource->church_id === request()->user()?->profile?->church_id, 403);

        return Inertia::render('Events/Form', [
            'event' => [
                'id' => $resource->id,
                'title' => $resource->title,
                'slug' => $resource->slug,
                'description' => $resource->description,
                'start_time' => $resource->start_time,
                'end_time' => $resource->end_time,
                'cover_path' => $resource->cover_url,
                'category_ids' => $resource->categories()->pluck('categories.id')->all(),
                'form_id' => $resource->forms()->value('forms.id'),
            ],
            'categories' => categoriesForChurchAndType(request(), CategoryType::EVENT->value),
            'forms' => Form::query()->where('church_id', $resource->church_id)->orderBy('title')->get(['id', 'title', 'description']),
            'returnUrl' => request()->user()?->role?->value === 'leader' ? route('events.index') : route('admin.events.index'),
        ]);
    })->middleware('role:leader|church_leader|superadmin|system')->name('events.edit');

    Route::get('/dashboard/posts', function () {
        $churchId = request()->user()?->church?->id;
        $posts = Post::query()
            ->where('church_id', $churchId)
            ->with('author:id,first_name,last_name,email')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->through(function ($post) {
                $post->localize();

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

        return Inertia::render('Posts/Index', [
            'posts' => $posts,
            'categories' => categoriesForChurchAndType(request(), CategoryType::POST->value),
        ]);
    })->name('posts.index');
    Route::get('/dashboard/posts/create', function () {
        return Inertia::render('Posts/Form', [
            'categories' => categoriesForChurchAndType(request(), CategoryType::POST->value),
            'forms' => Form::query()->where('church_id', request()->user()?->church?->id)->orderBy('title')->get(['id', 'title', 'description']),
        ]);
    })->name('posts.create');
    Route::get('/dashboard/posts/{post}/edit', function ($post) {
        $post = Post::query()->with(['church', 'medias'])->findOrFail(request()->route('post'));
        abort_unless($post->church_id === request()->user()?->church?->id, 403);
        $church = $post->church;
        $props = [
            'available_categories' => $church->categories()->get(['id', 'name'])->each->makeHidden('translations'),
            'categories' => categoriesForChurchAndType(request(), CategoryType::POST->value),
            'forms' => Form::query()->where('church_id', $post->church_id)->orderBy('title')->get(['id', 'title', 'description']),
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'content' => $post->content,
                'published_at' => $post->published_at,
                'expires_at' => $post->expires_at,
                'category_ids' => $post->categories()->pluck('categories.id')->all(),
                'form_id' => $post->forms()->value('forms.id'),
            ],
        ];

        return Inertia::render('Posts/Form', $props);
    })->name('posts.edit');
    Route::get('/dashboard/posts/{post}', function () {
        $post = Post::query()->with(['church', 'medias', 'categories'])->findOrFail(request()->route('post'));
        abort_unless($post->church_id === request()->user()?->church?->id, 403);
        $post->localize(relations: ['church', 'categories']);
        $props = [
            'can' => [
                'edit' => auth()->user()->can('update', $post),
                'delete' => auth()->user()->can('delete', $post),
                'comment' => auth()->user()->can('comment', $post),
                'react' => auth()->user()->can('react', $post),
            ],
            'post' => $post,
            'contentHtml' => Str::markdown((string) $post->content, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
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
            'comments' => $post->comments()->with(['user:id,first_name,last_name', 'replies.user:id,first_name,last_name'])->orderBy('created_at', 'desc')->get(),
            'reactions' => $post->reactions()->with('user:id,first_name,last_name')->get(),
        ];

        return Inertia::render('Posts/Show', $props);
    })->name('posts.show');

    // // Categories (Categorias - Baseado no api.json)
    // Route::get('/categories', function () { return Inertia::render('Categories/Index'); })->name('categories.index');
});

require __DIR__.'/settings.php';
