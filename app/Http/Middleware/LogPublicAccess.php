<?php

namespace App\Http\Middleware;

use App\Services\PublicAccessLogger;
use App\Support\ChurchDomainContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records anonymous access logs for public pages only.
 *
 * Authenticated requests, non-GET verbs, non-HTML responses, redirects/errors
 * and known non-page paths (assets, dashboard, api, internal, well-known) are
 * ignored, so the visitor map reflects real public page views without ever
 * storing a user identifier.
 */
class LogPublicAccess
{
    /**
     * Paths that must never be treated as a public page view.
     *
     * @var array<int, string>
     */
    private const IGNORED_PATH_PATTERNS = [
        'dashboard',
        'dashboard/*',
        'api/*',
        'settings',
        'settings/*',
        'login',
        'logout',
        'register',
        'password/*',
        'email/*',
        'two-factor-challenge',
        'user/*',
        '.well-known/*',
        'sitemap*.xml',
        'robots.txt',
        'sw.js',
        'manifest.webmanifest',
        'branding/*',
        'favicon.*',
        'apple-touch-icon.png',
        'up',
    ];

    public function __construct(
        private PublicAccessLogger $logger,
        private ChurchDomainContext $churchContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Log the access after the response has been sent to the visitor.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! $this->shouldLog($request, $response)) {
            return;
        }

        $this->logger->record(
            $request,
            $this->churchContext->isResolved() ? $this->churchContext->churchId() : null,
        );
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        if ($request->user() !== null) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        if ($request->ajax() || $request->wantsJson() || $request->isXmlHttpRequest()) {
            return false;
        }

        if ($request->is(...self::IGNORED_PATH_PATTERNS)) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return $contentType === '' || str_contains($contentType, 'text/html');
    }
}
