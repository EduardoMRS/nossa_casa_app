<?php

namespace App\Http\Middleware;

use App\Support\ChurchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiChurchContext
{
    public function __construct(private readonly ChurchContext $context) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $this->context->resolveApi(
            $request,
            required: $mode === 'required',
            public: $mode === 'public',
        );

        return $next($request);
    }
}
