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
Route::group(['prefix' => 'community'], function ()
{
    Route::get('/', [CommunityController::class, 'index']);
    Route::get('/{slug}', [CommunityController::class, 'show']);
});

Route::group(['prefix' => 'church'], function ()
{
    Route::get('/', [ChurchController::class, 'index']);
    Route::get('/{slug}', [ChurchController::class, 'show']);
});

Route::group(['prefix'=> 'posts'], function ()
{
    Route::get('/', [PostController::class, 'index']);
    Route::get('/{slug}', [PostController::class, 'show']);
});

Route::group(['prefix'=> 'event'], function ()
{
    Route::get('/', [EventController::class, 'index']);
    Route::get('/{slug}', [EventController::class, 'show']);
});


/*
|--------------------------------------------------------------------------
| Rotas Protegidas (Requer Autenticação)
|--------------------------------------------------------------------------
| Acesso restrito a usuários logados. Utiliza o Sanctum para validar o token.
*/
Route::middleware('auth:sanctum')->group(function ()
{
    Route::group(['prefix'=> 'event'], function ()
    {
        Route::post('{slug}/checkin', [EventController::class, 'checkin']);
        Route::post('{slug}/confirm', [EventController::class, 'confirm']);
    });
    Route::group(['prefix' => 'classroom'], function ()
    {
        Route::get('/{slug}', [ClassroomController::class, 'memberShow']);
    });

    Route::middleware('role:leader')->group(function ()
    {
        // Rotas de administração (apenas para usuários com papel de leader)        
        Route::group(['prefix' => 'classroom'], function ()
        {
            Route::post('/', [ClassroomController::class, 'store']);
            Route::put('/{id}', [ClassroomController::class, 'update']);
            Route::delete('/{id}', [ClassroomController::class, 'destroy']);
        });
    });
    
    Route::middleware('role:media')->group(function ()
    {
        // Rotas de administração (apenas para usuários com papel de media)
    });

    Route::middleware('role:media|leader')->group(function ()
    {
        // Rotas de administração (apenas para usuários com papel de media ou leader)        
        Route::group(['prefix' => 'event'], function ()
        {
            Route::post('/', [EventController::class, 'store']);
            Route::put('/{id}', [EventController::class, 'update']);
            Route::delete('/{id}', [EventController::class, 'destroy']);
        });
        Route::group(['prefix' => 'post'], function ()
        {
            Route::post('/', [PostController::class, 'store']);
            Route::put('/{id}', [PostController::class, 'update']);
            Route::delete('/{id}', [PostController::class, 'destroy']);
        });
    });

    Route::middleware('role:admin|superadmin|system')->group(function ()
    {
        // Rotas de administração (apenas para usuários com papel de admin ou superior)
        Route::group(['prefix' => 'church'], function ()
        {
            Route::post('/', [ChurchController::class, 'store']);
            Route::put('/{id}', [ChurchController::class, 'update']);
            Route::delete('/{id}', [ChurchController::class, 'destroy']);
        });
        Route::group(['prefix' => 'community'], function ()
        {
            Route::post('/', [CommunityController::class, 'store']);
            Route::put('/{id}', [CommunityController::class, 'update']);
            Route::delete('/{id}', [CommunityController::class, 'destroy']);
        });
        Route::group(['prefix' => 'user'], function ()
        {
            Route::post('/', [UserController::class, 'store']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
        });
    });
});
