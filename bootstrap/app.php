<?php

use App\Http\Middleware\EnsureHttpsInProduction;
use App\Http\Middleware\EnsureLocaleInUrl;
use App\Http\Middleware\EnsureMediaWorkerToken;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectWwwHost;
use App\Http\Middleware\ResolveApiChurchContext;
use App\Http\Middleware\ResolveChurchDomain;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\UserRole;
use App\Http\Middleware\ValidatePushGatewaySignature;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'ncapp_locale']);
        $middleware->validateCsrfTokens(except: ['api/internal/media/*']);
        $middleware->preventRequestsDuringMaintenance([
            'dashboard/logs-metricas*',
        ]);

        $middleware->web(prepend: [
            RedirectWwwHost::class,
        ], append: [
            SetLocale::class,
            ResolveChurchDomain::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureHttpsInProduction::class,
        ]);

        $middleware->api(append: [
            SetLocale::class,
            EnsureHttpsInProduction::class,
        ]);

        $middleware->alias([
            'media.worker' => EnsureMediaWorkerToken::class,
            'church.context' => ResolveApiChurchContext::class,
            'role' => UserRole::class,
            'push.gateway' => ValidatePushGatewaySignature::class,
            'ensure.locale' => EnsureLocaleInUrl::class,
        ]);

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => __('api.errors.validation'),
                'code' => 'VALIDATION_ERROR',
                'errors' => $exception->errors(),
            ], $exception->status);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => __('api.errors.unauthenticated'),
                'code' => 'UNAUTHENTICATED',
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => __('api.errors.forbidden'),
                'code' => 'FORBIDDEN',
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => __('api.errors.not_found'),
                'code' => 'RESOURCE_NOT_FOUND',
            ], 404);
        });
    })->create();
