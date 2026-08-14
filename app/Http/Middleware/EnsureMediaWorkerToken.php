<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMediaWorkerToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('media.worker_token');
        $providedToken = $request->header('X-Media-Worker-Token');

        abort_unless(
            is_string($expectedToken)
                && $expectedToken !== ''
                && is_string($providedToken)
                && hash_equals($expectedToken, $providedToken),
            403,
        );

        return $next($request);
    }
}
