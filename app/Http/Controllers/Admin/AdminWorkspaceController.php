<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Church;
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
use App\Models\User;
use App\Models\Vercicle;
use App\Traits\ManagesChurchCategories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AdminWorkspaceController extends Controller
{
    use ManagesChurchCategories;

    public function highlights(): Response
    {
        $highlights = Highlight::query()->latest()->limit(8)->get(['highlightable_type', 'highlightable_id', 'created_at']);

        return $this->module(
            translationNamespace: 'admin.modules.highlights',
            title: 'Destaques',
            subtitle: 'Comunicacao e conteudo',
            description: 'Gerencie os destaques visuais que aparecem para a comunidade nos canais principais.',
            stats: [
                ['label' => 'Destaques ativos', 'value' => Highlight::query()->count()],
                ['label' => 'Postagens publicadas', 'value' => Post::query()->count()],
                ['label' => 'Eventos publicados', 'value' => Event::query()->count()],
            ],
            actions: [
                ['label' => 'Gerenciar postagens', 'href' => route('posts.index')],
                ['label' => 'Abrir eventos', 'href' => route('events.index')],
                ['label' => 'Identidade visual', 'href' => route('admin.branding.edit')],
            ],
            items: $highlights->map(fn (Highlight $highlight) => [
                'label' => class_basename($highlight->highlightable_type),
                'value' => sprintf('%s - %s', $highlight->highlightable_id, $highlight->created_at?->format('d/m/Y H:i') ?? '--'),
            ])->all(),
        );
    }

    public function galleryModeration(): Response
    {
        $churchId = request()->user()?->church?->id;

        $media = Media::query()
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
                ['label' => 'Midias enviadas', 'value' => Media::query()->count()],
                ['label' => 'Pendentes', 'value' => Media::pending()->count()],
                ['label' => 'Aprovadas', 'value' => Media::visible()->count()],
                ['label' => 'Rejeitadas', 'value' => Media::rejected()->count()],
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
        $comments = Comment::query()->with('user:id,first_name,last_name')->latest()->paginate(20);

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
        $myRequests = $hasPrayerTable
            ? PrayerRequest::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate(20, ['id', 'content', 'created_at'])
            : ['data' => []];

        return Inertia::render('Admin/MyPrayers', [
            'requests' => $myRequests,
            'stats' => [
                ['label' => 'Pedidos enviados', 'value' => $hasPrayerTable ? PrayerRequest::query()->where('user_id', $request->user()->id)->count() : 0],
                ['label' => 'Pedidos da igreja', 'value' => $hasPrayerTable ? PrayerRequest::query()->count() : 0],
            ],
        ]);
    }

    public function userManagement(): Response
    {
        $users = User::query()->with('church:id,name')->latest()->paginate(20);
        $users->getCollection()->each(fn (User $user) => $user->church?->makeHidden('translations'));
        $churches = Church::query()->orderBy('name')->get(['id', 'name'])->each->makeHidden('translations');

        return Inertia::render('Admin/UserManagement', [
            'users' => $users,
            'churches' => $churches,
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => ['value' => $role->value, 'label' => ucfirst($role->value)]),
            'stats' => [
                ['label' => 'Usuários totais', 'value' => User::query()->count()],
                ['label' => 'Administradores', 'value' => User::query()->whereIn('role', ['admin', 'superadmin', 'system'])->count()],
                ['label' => 'Membros', 'value' => User::query()->where('role', 'member')->count()],
            ],
        ]);
    }

    public function multiCongregation(): Response
    {
        $churches = Church::query()->with('community:id,name')->withCount('members')->orderBy('name')->get();
        $churches->each(function (Church $church): void {
            $church->makeHidden('translations');
            $church->community?->makeHidden('translations');
        });
        $communities = Community::query()->orderBy('name')->get(['id', 'name'])->each->makeHidden('translations');
        $networks = Network::query()->with(['parentChurch:id,name', 'childChurch:id,name'])->get();
        $networks->each(function (Network $network): void {
            $network->parentChurch?->makeHidden('translations');
            $network->childChurch?->makeHidden('translations');
        });

        return Inertia::render('Admin/MultiCongregation', [
            'churches' => $churches,
            'communities' => $communities,
            'networks' => $networks,
            'stats' => [
                ['label' => 'Igrejas', 'value' => Church::query()->count()],
                ['label' => 'Comunidades', 'value' => Community::query()->count()],
                ['label' => 'Vínculos matriz/filial', 'value' => Network::query()->count()],
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
            ->with(['teacher:id,first_name,last_name', 'members:id,first_name,last_name'])
            ->withCount(['members', 'presences as active_presences_count' => fn ($query) => $query->whereNull('check_out')])
            ->latest()
            ->get()
            ->each->makeHidden('translations');

        return Inertia::render('Admin/Classrooms', [
            'classrooms' => $classrooms,
            'members' => User::query()->whereHas('profile', fn ($query) => $query->where('church_id', $churchId))->with('profile:user_id,gender')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'birth_date']),
            'kidsOnly' => $kidsOnly,
        ]);
    }

    public function logsMetrics(): Response
    {
        return Inertia::render('Admin/LogsMetrics', [
            'stats' => [
                ['label' => 'Usuários', 'value' => User::query()->count(), 'tone' => 'indigo'],
                ['label' => 'Eventos', 'value' => Event::query()->count(), 'tone' => 'emerald'],
                ['label' => 'Postagens', 'value' => Post::query()->count(), 'tone' => 'amber'],
                ['label' => 'Comentários', 'value' => Comment::query()->count(), 'tone' => 'rose'],
                ['label' => 'Mídias', 'value' => Media::query()->count(), 'tone' => 'sky'],
                ['label' => 'Formulários', 'value' => Form::query()->count(), 'tone' => 'violet'],
            ],
            'activity' => Comment::query()->with('user:id,first_name,last_name')->latest()->limit(12)->get(['id', 'user_id', 'content', 'created_at']),
        ]);
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
