<?php

use App\Http\Controllers\Api\DevicePushTokenController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\PublicContentController;
use App\Http\Controllers\Api\PushGatewayController;
use App\Http\Controllers\BibleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChurchController;
use App\Http\Controllers\ChurchProximityController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormResponseController;
use App\Http\Controllers\HighlightController;
use App\Http\Controllers\Internal\MediaAuthController;
use App\Http\Controllers\Internal\MediaServerWebhookController;
use App\Http\Controllers\Internal\RecordingSegmentController;
use App\Http\Controllers\Internal\StoredRecordingController;
use App\Http\Controllers\LiveStreamController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PrayerRequestController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserRelationshipController;
use Illuminate\Support\Facades\Route;

$isWayfinderGeneration = in_array('wayfinder:generate', $_SERVER['argv'] ?? [], true);

Route::prefix('auth')->group(function () {
    Route::post('login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:mobile-login')
        ->name('api.auth.login');
    Route::post('refresh', [MobileAuthController::class, 'refresh'])
        ->middleware('throttle:mobile-refresh')
        ->name('api.auth.refresh');

    Route::middleware(['auth:sanctum', 'church.context:optional', 'throttle:mobile-authenticated'])->group(function () {
        Route::get('me', [MobileAuthController::class, 'me'])->name('api.auth.me');
        Route::post('logout', [MobileAuthController::class, 'logout'])->name('api.auth.logout');
        Route::post('logout-all', [MobileAuthController::class, 'logoutAll'])->name('api.auth.logout_all');
        Route::get('sessions', [MobileAuthController::class, 'sessions'])->name('api.auth.sessions');
        Route::delete('sessions/{mobileSession}', [MobileAuthController::class, 'destroySession'])
            ->name('api.auth.sessions.destroy');
    });
});

Route::prefix('internal/media')
    ->middleware(['media.worker', 'throttle:120,1'])
    ->group(function () {
        Route::post('online', [MediaServerWebhookController::class, 'online']);
        Route::post('offline', [MediaServerWebhookController::class, 'offline']);
        Route::post('recording-segment-completed', RecordingSegmentController::class);
        Route::post('recording-stored', StoredRecordingController::class);
    });

Route::post('internal/media/auth', MediaAuthController::class)
    ->middleware('throttle:300,1');

Route::post('push/gateway', PushGatewayController::class)
    ->middleware(['push.gateway', 'throttle:push-gateway'])
    ->name('api.push.gateway');

/*
|--------------------------------------------------------------------------
| Rotas Públicas
|--------------------------------------------------------------------------
*/
Route::middleware('church.context:public')->group(function () {
    Route::get('portal', [PublicContentController::class, 'portal'])->name('api.portal');
    Route::get('content/posts', [PublicContentController::class, 'posts'])->name('api.content.posts');
    Route::get('content/posts/{slug}', [PublicContentController::class, 'post'])->name('api.content.posts.show');
    Route::get('content/events', [PublicContentController::class, 'events'])->name('api.content.events');
    Route::get('content/events/{slug}', [PublicContentController::class, 'event'])->name('api.content.events.show');
    Route::get('content/gallery', [PublicContentController::class, 'gallery'])->name('api.content.gallery');
    Route::get('content/library', [PublicContentController::class, 'library'])->name('api.content.library');
    Route::get('content/library/bible', [PublicContentController::class, 'bible'])->name('api.content.library.bible');
    Route::get('content/live-streams/{liveStream}', [PublicContentController::class, 'liveStream'])->name('api.content.live-streams.show');
    Route::get('bible/{version}/offline', [BibleController::class, 'offline'])->name('bible.offline');
    Route::get('bible/{version}/books', [BibleController::class, 'books'])->name('bible.books');
    Route::get('bible/{version}/books/{book}/chapters', [BibleController::class, 'chapters'])->name('bible.chapters');
    Route::get('bible/{version}/books/{book}/chapters/{chapter}', [BibleController::class, 'chapter'])
        ->whereNumber('chapter')
        ->name('bible.chapter');
    Route::get('communities', [CommunityController::class, 'index']);
    Route::get('communities/{slug}', [CommunityController::class, 'show']);
    Route::get('churches', [ChurchController::class, 'index']);
    Route::get('churches/{slug}', [ChurchController::class, 'show']);
    Route::get('posts', [PostController::class, 'index']);
    Route::get('posts/{post}', [PostController::class, 'show']);
    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{slug}', [EventController::class, 'show']);
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::apiResource('media', MediaController::class)
        ->parameters(['media' => 'media'])
        ->only(['index', 'show']);
    Route::get('comments', [CommentController::class, 'index']);

    Route::post('prayer-requests', [PrayerRequestController::class, 'store'])
        ->middleware('throttle:10,1');
});

/*
|--------------------------------------------------------------------------
| Rotas Autenticadas
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'church.context:public'])->group(function () {
    Route::middleware('role:member|leader|media|church_leader|superadmin|system')->group(function () {
        Route::apiResource('comments', CommentController::class)->only(['store', 'update', 'destroy']);
        Route::post('reactions', [ReactionController::class, 'store']);
        Route::delete('reactions/{reaction}', [ReactionController::class, 'destroy']);
        Route::post('events/{event}/checkin', [EventController::class, 'checkin']);
        Route::post('forms/{form}/responses', [FormResponseController::class, 'store']);
    });

    Route::middleware('role:leader|church_leader|superadmin|system')->group(function () {
        Route::post('classrooms/{classroom}/check-in', [ClassroomController::class, 'checkIn']);
        Route::post('classrooms/{classroom}/check-out', [ClassroomController::class, 'checkOut']);
    });
});

Route::middleware(['auth:sanctum', 'church.context:optional'])->group(function () {
    Route::get('church-proximity', ChurchProximityController::class)
        ->middleware('throttle:30,1')
        ->name('api.church-proximity');

    Route::post('push/devices', [DevicePushTokenController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('api.push.devices.store');
    Route::delete('push/devices/{deviceId}', [DevicePushTokenController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('api.push.devices.destroy');

    // Acesso Geral (Membro e superiores)
    Route::middleware('role:member|leader|media|church_leader|superadmin|system')->group(function () {
        // Histórico de pedidos de oração do usuário logado
        Route::get('prayer-requests', [PrayerRequestController::class, 'index']);
    });

    // Acesso de Liderança (Líder ou superior)
    Route::middleware('role:leader|church_leader|superadmin|system')->group(function () {
        // Relacionamentos Pessoais
        Route::post('users/{user}/family-relationship', [UserRelationshipController::class, 'store']);

        // Categorização Genérica
        Route::post('items/{item_type}/{item}/categorize', [CategoryController::class, 'categorizeItem']);
    });

    Route::put('comments/{comment}/pin', [CommentController::class, 'pin'])
        ->middleware('role:leader|media|church_leader|superadmin|system');

    // Acesso Estrito de Liderança (Apenas Leader) - Mantendo sua estrutura original
    Route::middleware('role:leader|church_leader|superadmin|system')->group(function () {
        Route::apiResource('forms', FormController::class);
        Route::apiResource('classrooms', ClassroomController::class);
        Route::post('events', [EventController::class, 'store']);
        Route::put('events/{event}', [EventController::class, 'update']);
        Route::delete('events/{event}', [EventController::class, 'destroy']);
    });

    // Acesso de Mídia / Comunicação
    Route::middleware('role:media|leader|church_leader|superadmin|system')->group(function () {
        Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
        Route::apiResource('media', MediaController::class)
            ->parameters(['media' => 'media'])
            ->only(['store', 'update', 'destroy']);
        Route::post('posts', [PostController::class, 'store']);
        Route::put('posts/{post}', [PostController::class, 'update']);
        Route::delete('posts/{post}', [PostController::class, 'destroy']);
    });

    Route::middleware('role:media|church_leader|superadmin|system')->group(function () {
        Route::apiResource('live-streams', LiveStreamController::class)
            ->parameters(['live-streams' => 'liveStream'])
            ->only(['index', 'store', 'show', 'destroy']);
        Route::post('live-streams/{liveStream}/stop', [LiveStreamController::class, 'stop']);
        Route::post('live-streams/{liveStream}/rotate-token', [LiveStreamController::class, 'rotateToken']);
    });

    // Acesso Exclusivo para Moderação de Mídia e Destaques (Media e Superiores)
    Route::middleware('role:media|church_leader|superadmin|system')->group(function () {
        Route::get('admin/media/pending', [MediaController::class, 'pending']);
        Route::put('admin/media/{media}/status', [MediaController::class, 'updateStatus']);
        Route::put('church/{church}/highlights', [HighlightController::class, 'updateChurchHighlights']);
    });

    // Acesso Administrativo Global
    Route::middleware('role:church_leader|superadmin|system')->group(function () {
        Route::apiResource('churches', ChurchController::class)->except(['index', 'show']);
        Route::apiResource('communities', CommunityController::class)->except(['index', 'show']);
        Route::apiResource('users', UserController::class);
        Route::apiResource('settings', SettingController::class);
        Route::post('networks/requests/{networkRequest}/accept', [NetworkController::class, 'accept']);
        Route::post('networks/requests/{networkRequest}/reject', [NetworkController::class, 'reject']);
        Route::apiResource('networks', NetworkController::class);
    });
});

/*
|--------------------------------------------------------------------------
| Legacy URI aliases
|--------------------------------------------------------------------------
|
| These aliases keep existing clients working while all generated links and
| new integrations use the plural English resource names above.
|--------------------------------------------------------------------------
*/
if (! $isWayfinderGeneration) {
    Route::middleware('church.context:public')->group(function () {
        Route::get('community', [CommunityController::class, 'index']);
        Route::get('community/{slug}', [CommunityController::class, 'show']);
        Route::get('church', [ChurchController::class, 'index']);
        Route::get('church/{slug}', [ChurchController::class, 'show']);
        Route::get('event', [EventController::class, 'index']);
        Route::get('event/{slug}', [EventController::class, 'show']);
    });

    Route::middleware(['auth:sanctum', 'church.context:optional'])->group(function () {
        Route::middleware('role:member|leader|media|church_leader|superadmin|system')
            ->post('event/{event}/checkin', [EventController::class, 'checkin']);

        Route::middleware('role:leader|church_leader|superadmin|system')->group(function () {
            Route::post('event', [EventController::class, 'store']);
            Route::put('event/{event}', [EventController::class, 'update']);
            Route::delete('event/{event}', [EventController::class, 'destroy']);
        });

        Route::middleware('role:media|leader|church_leader|superadmin|system')->group(function () {
            Route::post('post', [PostController::class, 'store']);
            Route::put('post/{post}', [PostController::class, 'update']);
            Route::delete('post/{post}', [PostController::class, 'destroy']);
        });

        Route::middleware('role:church_leader|superadmin|system')->group(function () {
            Route::apiResource('church', ChurchController::class)->except(['index', 'show']);
            Route::apiResource('community', CommunityController::class)->except(['index', 'show']);
            Route::apiResource('user', UserController::class);
        });
    });
}
