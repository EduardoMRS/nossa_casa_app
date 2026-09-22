<?php

namespace App\Http\Middleware;

use App\Support\ChurchDomainContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectWwwHost
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(ChurchDomainContext::class);
        $normalizedHost = ChurchDomainContext::normalizeDomain($request->getHost());
        $path = preg_replace('#/{2,}#', '/', $request->getPathInfo()) ?: '/';
        $isWwwHost = $normalizedHost === 'www.'.$context->mainHost();

        if (! $isWwwHost && $path === $request->getPathInfo()) {
            return $next($request);
        }

        $baseUrl = $isWwwHost
            ? rtrim((string) config('app.url'), '/')
            : rtrim($request->getSchemeAndHttpHost(), '/');
        $target = $baseUrl.$path;

        if ($request->getQueryString()) {
            $target .= '?'.$request->getQueryString();
        }

        return redirect()->to($target, 301);
    }
}
