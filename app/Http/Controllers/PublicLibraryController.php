<?php

namespace App\Http\Controllers;

use App\Models\Church;
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
        $church = $this->context->church();
        abort_unless($church instanceof Church, 404);

        return Inertia::render('Library/Bible', $this->library->bible($church)->toArray());
    }
}
