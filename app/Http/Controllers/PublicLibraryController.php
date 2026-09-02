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
        return Inertia::render(
            'Library/Bible',
            $this->library->bible($this->context->church())->toArray(),
        );
    }
}
