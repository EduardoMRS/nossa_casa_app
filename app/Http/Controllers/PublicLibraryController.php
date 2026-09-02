<?php

namespace App\Http\Controllers;

use App\Queries\LibraryQuery;
use App\Support\ChurchDomainContext;
use Inertia\Inertia;
use Inertia\Response;

final class PublicLibraryController extends Controller
{
    public function __construct(
        private readonly ChurchDomainContext $context,
        private readonly LibraryQuery $library,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Library/Index', $this->library->index($this->context->church())->toArray());
    }

    public function bible(): Response
    {
        $user = request()->user();
        $church = $user !== null && $user->church === null
            ? null
            : $this->context->church();

        return Inertia::render(
            'Library/Bible',
            $this->library->bible($church)->toArray(),
        );
    }
}
