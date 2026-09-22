<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ChurchRegistrationRequest;
use App\Models\Classroom;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Event;
use App\Models\Form;
use App\Models\Highlight;
use App\Models\Library;
use App\Models\Media;
use App\Models\Network;
use App\Models\Post;
use App\Models\PrayerRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\SystemBackupService;
use App\Support\ChurchDomainContext;
use App\Support\ChurchTerminology;
use App\Support\SystemMetricsSnapshot;
use App\Traits\ManagesChurchCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

class AdminWorkspaceController extends Controller
{
    use ManagesChurchCategories;

    public function __construct(
        private ChurchTerminology $terminology,
        private ChurchDomainContext $domainContext,
    ) {}

    public function highlights(): Response
    {
        $churchId = $this->currentChurchId();
        $highlights = Highlight::query()
            ->where('church_id', $churchId)
            ->with('highlightable')
            ->orderBy('order')
            ->get()
            ->map(fn (Highlight $highlight) => [
                'id' => $highlight->highlightable_id,
                'type' => Str::lower(class_basename($highlight->highlightable_type)),
                'order' => $highlight->order,
                'title' => $highlight->highlightable?->title ?? $highlight->highlightable?->name ?? $highlight->highlightable?->file_path,
            ]);

        return Inertia::render('Admin/Highlights', [
            'highlights' => $highlights,
            'churchId' => $churchId,
            'candidates' => [
                'post' => Post::query()->where('church_id', $churchId)->latest()->get(['id', 'title']),
                'event' => Event::query()->where('church_id', $churchId)->latest()->get(['id', 'title']),
                'media' => Media::query()->where('church_id', $churchId)->latest()->get(['id', 'file_path']),
            ],
        ]);
    }

    public function events(Request $request): Response
    {
        $churchId = $this->currentChurchId();
        $events = Event::query()
            ->where('church_id', $churchId)
            ->withCount(['registrations as users_count', 'forms'])
            ->orderByDesc('start_time')
            ->paginate(20)
            ->through(fn (Event $event): Event => $event->localize());

        return Inertia::render('Admin/Events', [
            'events' => $events,
            'categories' => $this->availableChurchCategories($churchId, CategoryType::EVENT->value),
        ]);
    }

    public function galleryModeration(): Response
    {
        $churchId = $this->currentChurchId();

        $media = Media::query()
            ->where('church_id', $churchId)
            ->with('uploader:id,first_name,last_name')
            ->latest()
            ->limit(24)
            ->get()
            ->map(fn (Media $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'file_path' => $item->file_path,
                'preview_url' => $item->url,
                'mimetype' => $item->mimetype,
                'size' => $item->size,
                'gallery' => $item->gallery,
                'status' => $item->status?->value ?? $item->status,
                'created_at' => $item->created_at,
                'uploader' => $item->uploader,
                'category_ids' => $item->categories()->pluck('categories.id')->all(),
            ])
            ->values();

        return Inertia::render('Admin/MediaModeration', [
            'title' => __('admin.media.title'),
            'subtitle' => __('admin.media.subtitle'),
            'description' => __('admin.media.description'),
            'stats' => [
                ['label' => __('admin.media.stats.0'), 'value' => Media::query()->where('church_id', $churchId)->count()],
                ['label' => __('admin.media.stats.1'), 'value' => Media::query()->where('church_id', $churchId)->pending()->count()],
                ['label' => __('admin.media.stats.2'), 'value' => Media::query()->where('church_id', $churchId)->visible()->count()],
                ['label' => __('admin.media.stats.3'), 'value' => Media::query()->where('church_id', $churchId)->rejected()->count()],
            ],
            'actions' => [
                ['label' => __('admin.media.actions.0'), 'href' => route('gallery.index')],
                ['label' => __('admin.media.actions.1'), 'href' => route('dashboard')],
            ],
            'media' => $media,
            'categories' => $this->availableChurchCategories($churchId, CategoryType::MEDIA->value),
        ]);
    }

    public function categories(): Response
    {
        $churchId = $this->currentChurchId();

        return Inertia::render('Admin/Categories', [
            'title' => __('admin.categories.title'),
            'subtitle' => __('admin.categories.subtitle'),
            'description' => __('admin.categories.description'),
            'types' => [
                ['value' => CategoryType::POST->value, 'label' => __('admin.categories.types.posts')],
                ['value' => CategoryType::EVENT->value, 'label' => __('admin.categories.types.events')],
                ['value' => CategoryType::MEDIA->value, 'label' => __('admin.categories.types.media')],
                ['value' => CategoryType::FORM->value, 'label' => __('admin.categories.types.forms')],
                ['value' => CategoryType::LIBRARY->value, 'label' => __('admin.categories.types.library')],
                ['value' => CategoryType::CLASSROOM->value, 'label' => __('admin.categories.types.classrooms')],
            ],
            'categories' => $this->availableChurchCategories($churchId, CategoryType::POST->value)
                ->merge($this->availableChurchCategories($churchId, CategoryType::EVENT->value))
                ->merge($this->availableChurchCategories($churchId, CategoryType::MEDIA->value))
                ->merge($this->availableChurchCategories($churchId, CategoryType::FORM->value))
                ->merge($this->availableChurchCategories($churchId, CategoryType::LIBRARY->value))
                ->merge($this->availableChurchCategories($churchId, CategoryType::CLASSROOM->value))
                ->values(),
        ]);
    }

    public function wallModeration(): Response
    {
        $churchId = $this->currentChurchId();
        $comments = Comment::query()
            ->whereHasMorph('commentable', [Post::class, Event::class, Media::class], fn ($query) => $query->where('church_id', $churchId))
            ->with(['user:id,first_name,last_name', 'commentable'])
            ->latest()
            ->paginate(20)
            ->through(fn (Comment $comment) => [
                'id' => $comment->id,
                'content' => $comment->content,
                'commentable_type' => Str::lower(class_basename($comment->commentable_type)),
                'commentable_title' => $comment->commentable?->title ?? $comment->commentable?->file_path ?? __('common.media'),
                'created_at' => $comment->created_at,
                'user' => $comment->user,
            ]);

        return Inertia::render('Admin/WallModeration', [
            'comments' => $comments,
            'stats' => [
                ['label' => __('admin.wall.stats.0'), 'value' => Comment::query()->count()],
                ['label' => __('admin.wall.stats.1'), 'value' => Post::has('comments')->count()],
                ['label' => __('admin.wall.stats.2'), 'value' => Comment::query()->where('commentable_type', Event::class)->distinct('commentable_id')->count('commentable_id')],
            ],
        ]);
    }

    public function libraryVerse(): Response
    {
        $items = Library::query()->latest()->limit(8)->get(['title', 'type']);

        return $this->module(
            translationNamespace: 'admin.modules.library',
            title: __('admin.modules.library.title'),
            subtitle: __('admin.modules.library.subtitle'),
            description: __('admin.modules.library.description'),
            stats: [
                ['label' => __('admin.modules.library.stats.0'), 'value' => Library::query()->count()],
                ['label' => __('admin.modules.library.stats.1'), 'value' => Setting::query()->get(['options'])->filter(fn (Setting $setting): bool => is_array(data_get($setting->options, 'bible.daily_verse')))->count()],
                ['label' => __('admin.modules.library.stats.2'), 'value' => Form::query()->count()],
            ],
            actions: [
                ['label' => __('admin.modules.library.actions.0'), 'href' => route('home')],
                ['label' => __('admin.modules.library.actions.1'), 'href' => route('admin.highlights.index')],
            ],
            items: $items->map(fn (Library $library) => [
                'label' => $library->title,
                'value' => $library->type,
            ])->all(),
        );
    }

    public function forms(): Response
    {
        $churchId = $this->currentChurchId();
        $forms = Form::query()
            ->where('church_id', $churchId)
            ->with(['categories:id,name', 'events:id,title', 'posts:id,title'])
            ->withCount('responses')
            ->latest()
            ->paginate(15)
            ->through(function (Form $form): Form {
                $form->makeHidden('translations');
                $form->categories->each->makeHidden('translations');
                $form->events->each->makeHidden('translations');
                $form->posts->each->makeHidden('translations');

                return $form;
            });

        return Inertia::render('Admin/Forms', [
            'forms' => $forms,
            'categories' => $this->availableChurchCategories($churchId, CategoryType::FORM->value),
        ]);
    }

    public function formCreate(Request $request): Response
    {
        $churchId = $this->currentChurchId();

        return Inertia::render('Admin/Form', $this->formEditorProps($churchId));
    }

    public function formEdit(Request $request, Form $form): Response
    {
        $this->ensureChurchAccess($request, $form->church_id);
        $form->load(['categories:id,name', 'events:id,title', 'posts:id,title']);

        return Inertia::render('Admin/Form', [
            ...$this->formEditorProps($form->church_id),
            'form' => $form->makeHidden('translations'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formEditorProps(?string $churchId): array
    {
        return [
            'events' => Event::query()->where('church_id', $churchId)->orderBy('title')->get(['id', 'title'])->each->makeHidden('translations'),
            'posts' => Post::query()->where('church_id', $churchId)->orderBy('title')->get(['id', 'title'])->each->makeHidden('translations'),
            'categories' => $this->availableChurchCategories($churchId, CategoryType::FORM->value),
        ];
    }

    public function prayerRequests(): Response
    {
        $hasPrayerTable = Schema::hasTable('prayer_requests');
        $requests = $hasPrayerTable
            ? PrayerRequest::query()->latest()->limit(10)->get(['id', 'content', 'created_at'])
            : collect();

        return $this->module(
            translationNamespace: 'admin.modules.prayer_requests',
            title: __('admin.modules.prayer_requests.title'),
            subtitle: __('admin.modules.prayer_requests.subtitle'),
            description: __('admin.modules.prayer_requests.description'),
            stats: [
                ['label' => __('admin.modules.prayer_requests.stats.0'), 'value' => $hasPrayerTable ? PrayerRequest::query()->count() : 0],
                ['label' => __('admin.modules.prayer_requests.stats.1'), 'value' => $hasPrayerTable ? PrayerRequest::query()->whereNull('user_id')->count() : 0],
                ['label' => __('admin.modules.prayer_requests.stats.2'), 'value' => $hasPrayerTable ? PrayerRequest::query()->whereNotNull('user_id')->count() : 0],
            ],
            actions: [
                ['label' => __('admin.modules.prayer_requests.actions.0'), 'href' => route('admin.myPrayers.index')],
                ['label' => __('admin.modules.prayer_requests.actions.1'), 'href' => route('dashboard')],
            ],
            items: $requests->map(fn (PrayerRequest $request) => [
                'label' => $request->created_at?->format('d/m/Y H:i') ?? '--',
                'value' => str($request->content)->limit(90)->toString(),
            ])->all(),
        );
    }

    public function kidsMinistry(): Response
    {
        return $this->classroomWorkspace(true);
    }

    public function myPrayers(Request $request): Response
    {
        $hasPrayerTable = Schema::hasTable('prayer_requests');
        $role = $request->user()->role?->value ?? (string) $request->user()->role;
        $churchId = $this->currentChurchId();
        $canSeeAnonymous = in_array($role, ['church_leader', 'superadmin', 'system'], true);
        $myRequests = $hasPrayerTable
            ? PrayerRequest::query()
                ->where('church_id', $churchId)
                ->where(function ($query) use ($request, $canSeeAnonymous): void {
                    $query->where('user_id', $request->user()->id)
                        ->orWhereNotNull('user_id');

                    if ($canSeeAnonymous) {
                        $query->orWhereNull('user_id');
                    }
                })
                ->with('user:id,first_name,last_name')
                ->latest()
                ->paginate(20)
                ->through(fn (PrayerRequest $prayer) => [
                    'id' => $prayer->id,
                    'content' => $prayer->content,
                    'created_at' => $prayer->created_at,
                    'is_anonymous' => $prayer->user_id === null,
                    'is_mine' => $prayer->user_id === $request->user()->id,
                    'user' => $prayer->user,
                ])
            : ['data' => []];

        return Inertia::render('Admin/MyPrayers', [
            'requests' => $myRequests,
            'stats' => [
                ['label' => __('admin.prayers.stats.0'), 'value' => $hasPrayerTable ? PrayerRequest::query()->where('user_id', $request->user()->id)->count() : 0],
                ['label' => __('admin.prayers.stats.1'), 'value' => $hasPrayerTable ? PrayerRequest::query()->where('church_id', $churchId)->count() : 0],
            ],
        ]);
    }

    public function userManagement(): Response
    {
        $actor = request()->user();
        $role = $actor?->role?->value ?? (string) $actor?->role;
        $churchId = $this->currentChurchId();
        $users = User::query()
            ->when($role === 'church_leader', fn ($query) => $query->whereHas('profile', fn ($profile) => $profile->where('church_id', $churchId)))
            ->with(['profile', 'church' => fn ($query) => $query->select(['churches.id', 'churches.name'])])
            ->latest()
            ->paginate(20);
        $users->getCollection()->each(fn (User $user) => $user->church?->makeHidden('translations'));
        $churches = Church::query()->orderBy('name')->get(['id', 'name'])->each->makeHidden('translations');
        $setting = $churchId ? Setting::query()->where('church_id', $churchId)->first() : null;
        $savedTerminology = is_array($setting?->options['terminology'] ?? null)
            ? $setting->options['terminology']
            : [];
        $terminologyChurch = $churchId ? Church::query()->find($churchId) : null;
        $resolvedTerminology = $terminologyChurch ? $this->terminology->resolvedForChurch($terminologyChurch) : $this->terminology->resolved();

        return Inertia::render('Admin/UserManagement', [
            'users' => $users,
            'churches' => $churches,
            'roles' => collect(UserRole::cases())
                ->reject(fn (UserRole $availableRole) => $role !== 'system' && $availableRole === UserRole::SYSTEM)
                ->map(fn (UserRole $availableRole) => [
                    'value' => $availableRole->value,
                    'label' => $resolvedTerminology['roles'][$availableRole->value]['label'],
                ])
                ->values(),
            'stats' => [
                ['label' => __('admin.users.stats.0'), 'value' => User::query()->count()],
                ['label' => __('admin.users.stats.1'), 'value' => User::query()->whereIn('role', ['church_leader', 'superadmin', 'system'])->count()],
                ['label' => __('admin.users.stats.2'), 'value' => User::query()->where('role', 'member')->count()],
            ],
        ]);
    }

    public function multiCongregation(Request $request): Response
    {
        $user = $request->user();
        $isSystem = $user?->role === UserRole::SYSTEM;
        $canChangeChurchCommunity = in_array($user?->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true);
        $hasPlatformScope = $canChangeChurchCommunity && app(ChurchDomainContext::class)->isMainDomain();
        $communityId = $user?->profile?->community_id ?? $user?->church?->community_id;

        if (! $hasPlatformScope) {
            abort_unless($communityId, 403);
        }

        $churchQuery = Church::query()
            ->when(! $hasPlatformScope, fn ($query) => $query->where('community_id', $communityId));
        $communityQuery = Community::query()
            ->when(! $canChangeChurchCommunity, fn ($query) => $query->whereKey($communityId));
        $networkQuery = Network::query()
            ->when(! $hasPlatformScope, function ($query) use ($communityId): void {
                $query->where(function ($networkQuery) use ($communityId): void {
                    $networkQuery->where('community_id', $communityId)
                        ->orWhere(function ($legacyNetworkQuery) use ($communityId): void {
                            $legacyNetworkQuery->whereNull('community_id')
                                ->whereHas('parentChurch', fn ($churchQuery) => $churchQuery->where('community_id', $communityId))
                                ->whereHas('childChurch', fn ($churchQuery) => $churchQuery->where('community_id', $communityId));
                        });
                });
            });

        $churches = (clone $churchQuery)->with(['community:id,name', 'settings', 'address'])->withCount('members')->orderBy('name')->get();
        $churches->each(function (Church $church): void {
            $branding = $church->settings?->options['branding'] ?? [];
            $church->setAttribute('logo_url', is_array($branding) && filled($branding['logo_path'] ?? null)
                ? genUrl($branding['logo_path'])
                : null);
            $church->setAttribute('icon_url', is_array($branding) && filled($branding['icon_path'] ?? null)
                ? genUrl($branding['icon_path'])
                : null);
            $church->makeHidden('settings');
            $church->makeHidden('translations');
            $church->community?->makeHidden('translations');
        });
        $communities = (clone $communityQuery)
            ->withCount('churches')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description'])
            ->each->makeHidden('translations');
        $networks = (clone $networkQuery)->with(['parentChurch:id,name', 'childChurch:id,name'])->get();
        $networks->each(function (Network $network): void {
            $network->parentChurch?->makeHidden('translations');
            $network->childChurch?->makeHidden('translations');
        });
        $registrationRequests = ChurchRegistrationRequest::query()
            ->when(! $hasPlatformScope, fn ($query) => $query->where('community_id', $communityId))
            ->with(['community:id,name', 'requester:id,first_name,last_name,email'])
            ->latest()
            ->get();

        return Inertia::render('Admin/MultiCongregation', [
            'churches' => $churches,
            'communities' => $communities,
            'networks' => $networks,
            'registrationRequests' => $registrationRequests,
            'canManageCommunities' => $isSystem,
            'canChangeChurchCommunity' => $canChangeChurchCommunity,
            'stats' => [
                ['label' => __('admin.multicongregation.stats.0'), 'value' => (clone $churchQuery)->count()],
                ['label' => __('admin.multicongregation.stats.1'), 'value' => (clone $communityQuery)->count()],
                ['label' => __('admin.multicongregation.stats.2'), 'value' => (clone $networkQuery)->count()],
            ],
        ]);
    }

    public function classrooms(): Response
    {
        return $this->classroomWorkspace(false);
    }

    private function classroomWorkspace(bool $kidsOnly): Response
    {
        $churchId = $this->currentChurchId();
        $classrooms = Classroom::query()
            ->where('church_id', $churchId)
            ->when($kidsOnly, fn ($query) => $query->where('is_kids', true))
            ->with([
                'teacher:id,first_name,last_name',
                'members.profile:user_id,phone,avatar_path,gender',
                'members.relationships.relatedUser:id,first_name,last_name,birth_date',
                'members.relatedRelationships.user:id,first_name,last_name,birth_date',
            ])
            ->withCount(['members', 'presences as active_presences_count' => fn ($query) => $query->whereNull('check_out')])
            ->latest()
            ->get()
            ->each(function (Classroom $classroom): void {
                $classroom->makeHidden('translations');
                $classroom->setAttribute('active_member_ids', $classroom->presences()->whereNull('check_out')->pluck('user_id'));
            });

        $separateKidsMinistry = (bool) data_get(
            Setting::query()->where('church_id', $churchId)->value('options'),
            'classrooms.separate_kids_ministry',
            true,
        );

        return Inertia::render('Admin/Classrooms', [
            'classrooms' => $classrooms,
            'members' => User::query()
                ->whereHas('profile', fn ($query) => $query->where('church_id', $churchId))
                ->with([
                    'profile:user_id,phone,avatar_path,gender',
                    'relationships.relatedUser:id,first_name,last_name,birth_date',
                    'relatedRelationships.user:id,first_name,last_name,birth_date',
                ])
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'birth_date']),
            'kidsOnly' => $kidsOnly,
            'separateKidsMinistry' => $separateKidsMinistry,
            'categories' => $this->availableChurchCategories($churchId, CategoryType::CLASSROOM->value),
        ]);
    }

    public function updateClassroomSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate(['separate_kids_ministry' => ['required', 'boolean']]);
        $churchId = $this->currentChurchId();
        abort_unless($churchId, 422);
        $setting = Setting::query()->firstOrNew(['church_id' => $churchId]);
        $options = $setting->options ?? [];
        $options['classrooms']['separate_kids_ministry'] = $validated['separate_kids_ministry'];
        $setting->options = $options;
        $setting->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.classrooms_updated')]);

        return back();
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $this->ensureManagedUser($request, $user);
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['email' => __($status)]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __($status)]);

        return back();
    }

    private function currentChurchId(): ?string
    {
        return $this->domainContext->churchId() ?? request()->user()?->church?->id;
    }

    public function logsMetrics(SystemMetricsSnapshot $snapshot): Response
    {
        $this->ensureBackupAccess(request());

        return Inertia::render('Admin/LogsMetrics', [
            ...$snapshot->make(),
            'system' => [
                'environment' => app()->environment(),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'queue_connection' => config('queue.default'),
            ],
            'maintenance' => app()->isDownForMaintenance(),
        ]);
    }

    public function exportBackup(SystemBackupService $backup): BinaryFileResponse|JsonResponse
    {
        $this->ensureBackupAccess(request());
        $wasInMaintenance = app()->isDownForMaintenance();

        if (! $wasInMaintenance) {
            app()->maintenanceMode()->activate(['status' => 503]);
        }

        try {
            $path = $backup->export();

            return response()
                ->download($path, 'nossa-casa-backup-'.now()->format('Y-m-d-His').'.zip', ['Content-Type' => 'application/zip'])
                ->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => __('backup.operation_failed')], 422);
        } finally {
            if (! $wasInMaintenance) {
                app()->maintenanceMode()->deactivate();
            }
        }
    }

    public function importBackup(Request $request, SystemBackupService $backup): RedirectResponse
    {
        $this->ensureBackupAccess($request);
        $validated = $request->validate([
            'backup' => ['required', 'file', 'max:5242880'],
        ]);
        $wasInMaintenance = app()->isDownForMaintenance();

        if (! $wasInMaintenance) {
            app()->maintenanceMode()->activate(['status' => 503]);
        }

        try {
            $backup->import($validated['backup']);

            return back()->with('backup_success', __('backup.imported'));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['backup' => __('backup.operation_failed')]);
        } finally {
            if (! $wasInMaintenance) {
                app()->maintenanceMode()->deactivate();
            }
        }
    }

    private function ensureBackupAccess(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true), 403);
    }

    private function ensureManagedUser(Request $request, User $user): void
    {
        $role = $request->user()?->role?->value ?? (string) $request->user()?->role;

        if ($role === 'church_leader') {
            abort_unless($user->church?->id === $request->user()?->church?->id, 403);
        }
    }

    /**
     * @param  array<int, array{label: string, value: int|string}>  $stats
     * @param  array<int, array{label: string, href: string}>  $actions
     * @param  array<int, array{label: string, value: string}>  $items
     */
    private function module(string $translationNamespace, string $title, string $subtitle, string $description, array $stats, array $actions, array $items): Response
    {
        return Inertia::render('Admin/Module', [
            'translationNamespace' => $translationNamespace,
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'stats' => $stats,
            'actions' => $actions,
            'items' => $items,
        ]);
    }
}
