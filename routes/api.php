<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\ChurchController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ClassroomController;

/*
|--------------------------------------------------------------------------
| Rotas Públicas (Leitura)
|--------------------------------------------------------------------------
| Estas rotas permitem que visitantes não autenticados e usuários autenticados de qualquer nível vejam os dados
| principais (ex: listar eventos em um app mobile ou landing page).
*/
Route::group(['prefix' => 'communities'], function () {
    Route::get('/', [CommunityController::class, 'index']);
    Route::get('/{id}', [CommunityController::class, 'show']);
});

Route::group(['prefix' => 'churches'], function () {
    Route::get('/', [ChurchController::class, 'index']);
    Route::get('/{id}', [ChurchController::class, 'show']);
});

Route::group(['prefix'=> 'posts'], function () {
    Route::get('/', [PostController::class, 'index']);
    Route::get('/{id}', [PostController::class, 'show']);
});

Route::group(['prefix'=> 'events'], function () {
    Route::get('/', [EventController::class, 'index']);
    Route::get('/{id}', [EventController::class, 'show']);
});


/*
|--------------------------------------------------------------------------
| Rotas Protegidas (Requer Autenticação)
|--------------------------------------------------------------------------
| Acesso restrito a usuários logados. Utiliza o Sanctum para validar o token.
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('classrooms', ClassroomController::class);
    Route::apiResource('communities', CommunityController::class)->except(['index', 'show']);
    Route::apiResource('churches', ChurchController::class)->except(['index', 'show']);
    Route::apiResource('posts', PostController::class)->except(['index', 'show']);
    Route::apiResource('events', EventController::class)->except(['index', 'show']);

    Route::middleware('role:leader')->group(function () {
        // Rotas de administração (apenas para usuários com papel de leader)
    });
    Route::middleware('role:media')->group(function () {
        // Rotas de administração (apenas para usuários com papel de media)
    });
    Route::middleware('role:media|leader')->group(function () {
        // Rotas de administração (apenas para usuários com papel de media ou leader)
    });
    Route::middleware('role:admin|superadmin|system')->group(function () {
        // Rotas de administração (apenas para usuários com papel de admin ou superior)
    });
});
