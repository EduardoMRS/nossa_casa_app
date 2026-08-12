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
use App\Models\Vercicle;
use App\Traits\ManagesChurchCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminWorkspaceController extends Controller
{
    use ManagesChurchCategories;

    public function highlights(): Response
    {
        $churchId = request()->user()?->church?->id;
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
        $churchId = $request->user()?->profile?->church_id;
        $events = Event::query()
            ->where('church_id', $churchId)
            ->withCount(['users', 'forms'])
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
        $churchId = request()->user()?->church?->id;

        $media = Media::query()
            ->where('church_id', $churchId)
            ->with('uploader:id,first_name,last_name')
            ->latest()
            ->limit(24)
            ->get()
            ->map(fn (Media $item) => [
                'id' => $item->id,
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
            'title' => 'Moderar Galeria',
            'subtitle' => 'Comunicacao e conteudo',
            'description' => 'Acompanhe o fluxo de aprovacao, edite midias e gerencie publicacoes da comunidade.',
            'stats' => [
                ['label' => 'Midias enviadas', 'value' => Media::query()->where('church_id', $churchId)->count()],
                ['label' => 'Pendentes', 'value' => Media::query()->where('church_id', $churchId)->pending()->count()],
                ['label' => 'Aprovadas', 'value' => Media::query()->where('church_id', $churchId)->visible()->count()],
                ['label' => 'Rejeitadas', 'value' => Media::query()->where('church_id', $churchId)->rejected()->count()],
            ],
            'actions' => [
                ['label' => 'Abrir galeria publica', 'href' => route('gallery.index')],
                ['label' => 'Ir para dashboard', 'href' => route('dashboard')],
            ],
            'media' => $media,
            'categories' => $this->availableChurchCategories($churchId, CategoryType::MEDIA->value),
        ]);
    }

    public function categories(): Response
    {
        $churchId = request()->user()?->church?->id;

        return Inertia::render('Admin/Categories', [
            'title' => 'Categorias por igreja',
            'subtitle' => 'Administracao e configuracao',
            'description' => 'Crie e edite as categorias usadas por eventos, postagens, midias, formularios, biblioteca e salas.',
            'types' => [
                ['value' => CategoryType::POST->value, 'label' => 'Postagens'],
                ['value' => CategoryType::EVENT->value, 'label' => 'Eventos'],
                ['value' => CategoryType::MEDIA->value, 'label' => 'Midias'],
                ['value' => CategoryType::FORM->value, 'label' => 'Formularios'],
                ['value' => CategoryType::LIBRARY->value, 'label' => 'Biblioteca'],
                ['value' => CategoryType::CLASSROOM->value, 'label' => 'Salas'],
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
        $churchId = request()->user()?->church?->id;
        $comments = Comment::query()
            ->whereHasMorph('commentable', [Post::class, Event::class, Media::class], fn ($query) => $query->where('church_id', $churchId))
            ->with(['user:id,first_name,last_name', 'commentable'])
            ->latest()
            ->paginate(20)
            ->through(fn (Comment $comment) => [
                'id' => $comment->id,
                'content' => $comment->content,
                'commentable_type' => Str::lower(class_basename($comment->commentable_type)),
                'commentable_title' => $comment->commentable?->title ?? $comment->commentable?->file_path ?? __('Media'),
                'created_at' => $comment->created_at,
                'user' => $comment->user,
            ]);

        return Inertia::render('Admin/WallModeration', [
            'comments' => $comments,
            'stats' => [
                ['label' => 'Comentários totais', 'value' => Comment::query()->count()],
                ['label' => 'Posts com comentários', 'value' => Post::has('comments')->count()],
                ['label' => 'Eventos comentados', 'value' => Comment::query()->where('commentable_type', Event::class)->distinct('commentable_id')->count('commentable_id')],
            ],
        ]);
    }

    public function libraryVerse(): Response
    {
        $items = Library::query()->latest()->limit(8)->get(['title', 'type']);

        return $this->module(
            translationNamespace: 'admin.modules.library',
            title: 'Biblioteca e Versiculo',
            subtitle: 'Comunicacao e conteudo',
            description: 'Controle materiais devocionais, acervo digital e versiculos em destaque.',
            stats: [
                ['label' => 'Materiais na biblioteca', 'value' => Library::query()->count()],
                ['label' => 'Versiculos cadastrados', 'value' => Vercicle::query()->count()],
                ['label' => 'Categorias de conteudo', 'value' => Form::query()->count()],
            ],
            actions: [
                ['label' => 'Abrir home publica', 'href' => route('home')],
                ['label' => 'Revisar destaques', 'href' => route('admin.highlights.index')],
            ],
            items: $items->map(fn (Library $library) => [
                'label' => $library->title,
                'value' => $library->type,
            ])->all(),
        );
    }

    public function forms(): Response
    {
        $churchId = request()->user()?->church?->id;
        $forms = Form::query()
            ->where('church_id', $churchId)
            ->with(['categories:id,name', 'events:id,title', 'posts:id,title'])
            ->withCount('responses')
            ->latest()
            ->get()
            ->each(function (Form $form): void {
                $form->makeHidden('translations');
                $form->categories->each->makeHidden('translations');
                $form->events->each->makeHidden('translations');
                $form->posts->each->makeHidden('translations');
            });
        $events = Event::query()->where('church_id', $churchId)->orderBy('title')->get(['id', 'title'])->each->makeHidden('translations');
        $posts = Post::query()->where('church_id', $churchId)->orderBy('title')->get(['id', 'title'])->each->makeHidden('translations');

        return Inertia::render('Admin/Forms', [
            'forms' => $forms,
            'events' => $events,
            'posts' => $posts,
            'categories' => $this->availableChurchCategories($churchId, CategoryType::FORM->value),
        ]);
    }

    public function prayerRequests(): Response
    {
        $hasPrayerTable = Schema::hasTable('prayer_requests');
        $requests = $hasPrayerTable
            ? PrayerRequest::query()->latest()->limit(10)->get(['id', 'content', 'created_at'])
            : collect();

        return $this->module(
            translationNamespace: 'admin.modules.prayer_requests',
            title: 'Pedidos de Intercessao',
            subtitle: 'Ministerios e membros',
            description: 'Central para visualizar pedidos recebidos e organizar acompanhamento pastoral.',
            stats: [
                ['label' => 'Pedidos recebidos', 'value' => $hasPrayerTable ? PrayerRequest::query()->count() : 0],
                ['label' => 'Pedidos anonimos', 'value' => $hasPrayerTable ? PrayerRequest::query()->whereNull('user_id')->count() : 0],
                ['label' => 'Pedidos identificados', 'value' => $hasPrayerTable ? PrayerRequest::query()->whereNotNull('user_id')->count() : 0],
            ],
            actions: [
                ['label' => 'Abrir minhas oracoes', 'href' => route('admin.myPrayers.index')],
                ['label' => 'Ir para dashboard', 'href' => route('dashboard')],
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
        $churchId = $request->user()->church?->id;
        $canSeeAnonymous = in_array($role, ['admin', 'superadmin', 'system'], true);
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
                ['label' => 'Pedidos enviados', 'value' => $hasPrayerTable ? PrayerRequest::query()->where('user_id', $request->user()->id)->count() : 0],
                ['label' => 'Pedidos da igreja', 'value' => $hasPrayerTable ? PrayerRequest::query()->where('church_id', $churchId)->count() : 0],
            ],
        ]);
    }

    public function userManagement(): Response
    {
        $actor = request()->user();
        $role = $actor?->role?->value ?? (string) $actor?->role;
        $churchId = $actor?->church?->id;
        $users = User::query()
            ->when($role === 'admin', fn ($query) => $query->whereHas('profile', fn ($profile) => $profile->where('church_id', $churchId)))
            ->with(['profile', 'church' => fn ($query) => $query->select(['churches.id', 'churches.name'])])
            ->latest()
            ->paginate(20);
        $users->getCollection()->each(fn (User $user) => $user->church?->makeHidden('translations'));
        $churches = Church::query()->orderBy('name')->get(['id', 'name'])->each->makeHidden('translations');

        return Inertia::render('Admin/UserManagement', [
            'users' => $users,
            'churches' => $churches,
            'roles' => collect(UserRole::cases())
                ->reject(fn (UserRole $availableRole) => $role !== 'system' && $availableRole === UserRole::SYSTEM)
                ->map(fn (UserRole $availableRole) => ['value' => $availableRole->value, 'label' => ucfirst($availableRole->value)])
                ->values(),
            'stats' => [
                ['label' => 'Usuários totais', 'value' => User::query()->count()],
                ['label' => 'Administradores', 'value' => User::query()->whereIn('role', ['admin', 'superadmin', 'system'])->count()],
                ['label' => 'Membros', 'value' => User::query()->where('role', 'member')->count()],
            ],
        ]);
    }

    public function multiCongregation(Request $request): Response
    {
        $user = $request->user();
        $isSystem = $user?->role === UserRole::SYSTEM;
        $communityId = $user?->profile?->community_id ?? $user?->church?->community_id;

        if (! $isSystem) {
            abort_unless($communityId, 403);
        }

        $churchQuery = Church::query()
            ->when(! $isSystem, fn ($query) => $query->where('community_id', $communityId));
        $communityQuery = Community::query()
            ->when(! $isSystem, fn ($query) => $query->whereKey($communityId));
        $networkQuery = Network::query()
            ->when(! $isSystem, function ($query) use ($communityId): void {
                $query->where(function ($networkQuery) use ($communityId): void {
                    $networkQuery->where('community_id', $communityId)
                        ->orWhere(function ($legacyNetworkQuery) use ($communityId): void {
                            $legacyNetworkQuery->whereNull('community_id')
                                ->whereHas('parentChurch', fn ($churchQuery) => $churchQuery->where('community_id', $communityId))
                                ->whereHas('childChurch', fn ($churchQuery) => $churchQuery->where('community_id', $communityId));
                        });
                });
            });

        $churches = (clone $churchQuery)->with('community:id,name')->withCount('members')->orderBy('name')->get();
        $churches->each(function (Church $church): void {
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
            ->when(! $isSystem, fn ($query) => $query->where('community_id', $communityId))
            ->with(['community:id,name', 'requester:id,first_name,last_name,email'])
            ->latest()
            ->get();

        return Inertia::render('Admin/MultiCongregation', [
            'churches' => $churches,
            'communities' => $communities,
            'networks' => $networks,
            'registrationRequests' => $registrationRequests,
            'canManageCommunities' => $isSystem,
            'stats' => [
                ['label' => 'Igrejas', 'value' => (clone $churchQuery)->count()],
                ['label' => 'Comunidades', 'value' => (clone $communityQuery)->count()],
                ['label' => 'Vínculos matriz/filial', 'value' => (clone $networkQuery)->count()],
            ],
        ]);
    }

    public function classrooms(): Response
    {
        return $this->classroomWorkspace(false);
    }

    private function classroomWorkspace(bool $kidsOnly): Response
    {
        $churchId = request()->user()?->church?->id;
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
        $churchId = $request->user()?->church?->id;
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

    public function logsMetrics(): Response
    {
        abort_unless(request()->user()?->role === UserRole::SYSTEM, 403);

        $logPath = storage_path('logs/laravel.log');
        $logLines = File::exists($logPath)
            ? collect(preg_split('/\R/', File::get($logPath)) ?: [])->filter()->take(-100)->values()
            : collect();

        return Inertia::render('Admin/LogsMetrics', [
            'stats' => [
                ['label' => 'Usuários', 'value' => User::query()->count(), 'tone' => 'indigo'],
                ['label' => 'Eventos', 'value' => Event::query()->count(), 'tone' => 'emerald'],
                ['label' => 'Postagens', 'value' => Post::query()->count(), 'tone' => 'amber'],
                ['label' => 'Comentários', 'value' => Comment::query()->count(), 'tone' => 'rose'],
                ['label' => 'Mídias', 'value' => Media::query()->count(), 'tone' => 'sky'],
                ['label' => 'Formulários', 'value' => Form::query()->count(), 'tone' => 'violet'],
            ],
            'queue' => [
                'pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
                'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            ],
            'system' => [
                'environment' => app()->environment(),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'queue_connection' => config('queue.default'),
            ],
            'logs' => $logLines,
        ]);
    }

    private function ensureManagedUser(Request $request, User $user): void
    {
        $role = $request->user()?->role?->value ?? (string) $request->user()?->role;

        if ($role === 'admin') {
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
