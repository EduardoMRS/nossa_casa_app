<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Support\ChurchDomainContext;
use Inertia\Inertia;
use Inertia\Response;

class GroupPortalController extends Controller
{
    public function __construct(private readonly ChurchDomainContext $domainContext) {}

    public function index(): Response
    {
        $churchId = $this->domainContext->churchId();
        abort_if($churchId === null, 404);

        $groups = Group::query()
            ->where('church_id', $churchId)
            ->where('is_public', true)
            ->with(['categories:id,name,slug,type', 'address', 'responsibleUsers:id,first_name,last_name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Group $group): Group => $group->localize(relations: ['categories']))
            ->map(fn (Group $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => $group->description,
                'categories' => $group->categories->pluck('name')->all(),
                'responsibles' => $group->responsibleUsers->map(fn ($user): string => $user->name)->all(),
                'location' => $group->address?->to_string,
            ]);

        return Inertia::render('Groups/Index', [
            'groups' => $groups,
        ]);
    }
}
