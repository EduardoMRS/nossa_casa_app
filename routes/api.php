<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChurchController;
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

/*
|--------------------------------------------------------------------------
| Rotas Públicas
|--------------------------------------------------------------------------
*/
Route::get('community', [CommunityController::class, 'index']);
Route::get('community/{slug}', [CommunityController::class, 'show']);
Route::get('church', [ChurchController::class, 'index']);
Route::get('church/{slug}', [ChurchController::class, 'show']);
Route::get('posts', [PostController::class, 'index']);
Route::get('posts/{post}', [PostController::class, 'show']);
Route::get('event', [EventController::class, 'index']);
Route::get('event/{slug}', [EventController::class, 'show']);
Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
Route::apiResource('media', MediaController::class)
    ->parameters(['media' => 'media'])
    ->only(['index', 'show']);
Route::get('comments', [CommentController::class, 'index']);

// Formulário de pedido de oração (Aberto ao público)
Route::post('prayer-requests', [PrayerRequestController::class, 'store'])
    ->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| Rotas Autenticadas
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Acesso Geral (Membro e superiores)
    Route::middleware('role:member|leader|media|admin|superadmin|system')->group(function () {
        Route::apiResource('comments', CommentController::class)->only(['store', 'update', 'destroy']);
        Route::post('reactions', [ReactionController::class, 'store']);
        Route::delete('reactions/{reaction}', [ReactionController::class, 'destroy']);

        Route::post('event/{event}/checkin', [EventController::class, 'checkin']);

        // Histórico de pedidos de oração do usuário logado
        Route::get('prayer-requests', [PrayerRequestController::class, 'index']);

        // Envio de formulário de inscrição para eventos
        Route::post('forms/{form}/responses', [FormResponseController::class, 'store']);
    });

    // Acesso de Liderança (Líder ou superior)
    Route::middleware('role:leader|admin|superadmin|system')->group(function () {
        // Relacionamentos Pessoais
        Route::post('users/{user}/family-relationship', [UserRelationshipController::class, 'store']);

        // Gestão de Aulas e Presenças
        Route::post('classrooms/{classroom}/check-in', [ClassroomController::class, 'checkIn']);
        Route::post('classrooms/{classroom}/check-out', [ClassroomController::class, 'checkOut']);

        // Categorização Genérica
        Route::post('items/{item_type}/{item}/categorize', [CategoryController::class, 'categorizeItem']);
    });

    Route::put('comments/{comment}/pin', [CommentController::class, 'pin'])
        ->middleware('role:leader|media|admin|superadmin|system');

    // Acesso Estrito de Liderança (Apenas Leader) - Mantendo sua estrutura original
    Route::middleware('role:leader|admin|superadmin|system')->group(function () {
        Route::apiResource('forms', FormController::class);
        Route::apiResource('classrooms', ClassroomController::class);
        Route::post('event', [EventController::class, 'store']);
        Route::put('event/{event}', [EventController::class, 'update']);
        Route::delete('event/{event}', [EventController::class, 'destroy']);
    });

    // Acesso de Mídia / Comunicação
    Route::middleware('role:media|leader|admin|superadmin|system')->group(function () {
        Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
        Route::apiResource('media', MediaController::class)
            ->parameters(['media' => 'media'])
            ->only(['store', 'update', 'destroy']);
        Route::post('post', [PostController::class, 'store']);
        Route::put('post/{post}', [PostController::class, 'update']);
        Route::delete('post/{post}', [PostController::class, 'destroy']);
    });

    Route::middleware('role:media|admin|superadmin|system')->group(function () {
        Route::apiResource('live-streams', LiveStreamController::class)
            ->parameters(['live-streams' => 'liveStream'])
            ->only(['index', 'store', 'show', 'destroy']);
        Route::post('live-streams/{liveStream}/rotate-token', [LiveStreamController::class, 'rotateToken']);
    });

    // Acesso Exclusivo para Moderação de Mídia e Destaques (Media e Superiores)
    Route::middleware('role:media|admin|superadmin|system')->group(function () {
        Route::get('admin/media/pending', [MediaController::class, 'pending']);
        Route::put('admin/media/{media}/status', [MediaController::class, 'updateStatus']);
        Route::put('church/{church}/highlights', [HighlightController::class, 'updateChurchHighlights']);
    });

    // Acesso Administrativo Global
    Route::middleware('role:admin|superadmin|system')->group(function () {
        Route::apiResource('church', ChurchController::class)->except(['index', 'show']);
        Route::apiResource('community', CommunityController::class)->except(['index', 'show']);
        Route::apiResource('user', UserController::class);
        Route::apiResource('settings', SettingController::class);
        Route::apiResource('networks', NetworkController::class);
    });
});
