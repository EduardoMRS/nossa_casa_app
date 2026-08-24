<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHttpsInProduction
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            app()->isProduction()
            && ! $request->isSecure()
            && ! $request->is('api/internal/media/*')
        ) {
            return response()->json([
                'message' => __('api.errors.https_required'),
                'code' => 'HTTPS_REQUIRED',
            ], 426);
        }

        return $next($request);
    }
}
