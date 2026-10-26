<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Group;
use App\Models\User;
use App\Services\UniqueSlugger;
use App\Support\ChurchDomainContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    /**
     * Roles allowed to manage groups within their church.
     *
     * @var array<int, UserRole>
     */
    private const MANAGER_ROLES = [
        UserRole::LEADER, UserRole::CHURCH_LEADER, UserRole::SUPERADMIN,
    ];

    public function __construct(private readonly UniqueSlugger $slugs) {}

    public function index(Request $request): Response
    {
        $churchId = $this->currentChurchId($request);

        $groups = Group::query()
            ->where('church_id', $churchId)
            ->with(['categories:id,name,slug,type', 'address', 'responsibleUsers:id,first_name,last_name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Group $group): Group => $group->localize())
            ->map(fn (Group $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => $group->description,
                'is_public' => $group->is_public,
                'category_ids' => $group->categories->pluck('id')->all(),
                'responsible_ids' => $group->responsibleUsers->pluck('id')->all(),
                'address' => $group->address?->only([
                    'country', 'state', 'city', 'neighborhood', 'street', 'number', 'complement', 'zipcode',
                ]),
            ]);

        return Inertia::render('Admin/Groups', [
            'groups' => $groups,
            'categories' => Category::query()
                ->where('church_id', $churchId)
                ->where('type', CategoryType::GROUP->value)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'type'])
                ->each->localize(),
            'responsibleOptions' => $this->responsibleOptions($churchId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $churchId = $this->currentChurchId($request);
        abort_unless($churchId !== null, 422, __('church.context.required'));
        $this->ensureCanCreate($request);

        $validated = $this->validateGroup($request, $churchId);

        $group = Group::create([
            'church_id' => $churchId,
            'name' => $validated['name'],
            'slug' => $this->slugs->make($validated['name'], 'groups', scope: ['church_id' => $churchId]),
            'description' => $validated['description'] ?? null,
            'is_public' => $validated['is_public'] ?? true,
        ]);

        $this->syncRelations($group, $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('group.notifications.created')]);

        return back();
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $this->ensureCanManage($request, $group);
        $validated = $this->validateGroup($request, $group->church_id);

        $group->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_public' => $validated['is_public'] ?? true,
        ]);

        $this->syncRelations($group, $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('group.notifications.updated')]);

        return back();
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        $this->ensureCanManage($request, $group);
        $group->address()->delete();
        $group->responsibleUsers()->detach();
        $group->categories()->detach();
        $group->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('group.notifications.deleted')]);

        return back();
    }

    private function ensureCanCreate(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, self::MANAGER_ROLES, true)
            || $request->user()?->role === UserRole::SYSTEM, 403);
    }

    private function ensureCanManage(Request $request, Group $group): void
    {
        $user = $request->user();

        if ($user?->role === UserRole::SYSTEM) {
            return;
        }

        abort_unless(
            $user?->church?->id === $group->church_id
                && in_array($user->role, self::MANAGER_ROLES, true),
            403
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateGroup(Request $request, string $churchId): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['boolean'],
            'category_ids' => ['array'],
            'category_ids.*' => [
                'string',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('church_id', $churchId)
                    ->where('type', CategoryType::GROUP->value)),
            ],
            'responsible_ids' => ['array'],
            'responsible_ids.*' => [
                'string',
                Rule::exists('user_profiles', 'user_id')->where(fn ($query) => $query->where('church_id', $churchId)),
            ],
            'address' => ['nullable', 'array'],
            'address.country' => ['nullable', 'string', 'max:255'],
            'address.state' => ['nullable', 'string', 'max:2'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.neighborhood' => ['nullable', 'string', 'max:255'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.number' => ['nullable', 'string', 'max:50'],
            'address.complement' => ['nullable', 'string', 'max:255'],
            'address.zipcode' => ['nullable', 'string', 'max:20'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncRelations(Group $group, array $validated): void
    {
        $group->categories()->sync($validated['category_ids'] ?? []);
        $group->responsibleUsers()->sync($validated['responsible_ids'] ?? []);

        $address = array_filter($validated['address'] ?? [], fn ($value): bool => filled($value));

        // The addresses table requires state, city, street and zipcode, so the
        // meeting place is stored only when the full required set is provided.
        $required = ['state', 'city', 'street', 'zipcode'];
        $hasRequired = count(array_intersect_key($address, array_flip($required))) === count($required);

        if (! $hasRequired) {
            $group->address()->delete();

            return;
        }

        $group->address()->updateOrCreate([], $address);
    }

    private function currentChurchId(Request $request): ?string
    {
        return app(ChurchDomainContext::class)->churchId() ?? $request->user()?->church?->id;
    }

    /**
     * @return Collection<int, array{id: string, name: string}>
     */
    private function responsibleOptions(?string $churchId)
    {
        return User::query()
            ->whereHas('profile', fn ($query) => $query->where('church_id', $churchId))
            ->whereIn('role', [UserRole::LEADER, UserRole::MEDIA, UserRole::CHURCH_LEADER, UserRole::SUPERADMIN])
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name]);
    }
}
