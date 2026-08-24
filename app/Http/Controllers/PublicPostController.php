<?php

namespace App\Http\Controllers;

use App\Queries\PostQuery;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PublicPostController extends Controller
{
    public function __construct(
        private readonly ChurchDomainContext $context,
        private readonly PostQuery $posts,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:26'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', Rule::when($request->filled('date_from'), ['after_or_equal:date_from'])],
            'sort' => ['nullable', 'in:latest,popular'],
        ]);

        return Inertia::render('Posts/PublicIndex', $this->posts->index(
            $filters,
            $this->context->churchId(),
        )->toArray());
    }

    public function show(Request $request, string $slug): Response
    {
        return Inertia::render('Posts/PublicShow', $this->posts->show(
            $slug,
            $this->context->churchId(),
            $request->user(),
        )->toArray());
    }
}
