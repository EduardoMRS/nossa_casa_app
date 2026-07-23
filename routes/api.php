<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChurchController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth')->group(function () {
    Route::middleware('role:member|leader|media|admin|superadmin|system')->group(function () {
        Route::apiResource('comments', CommentController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('media', MediaController::class)
            ->parameters(['media' => 'media'])
            ->only(['store']);
        Route::post('event/{event}/checkin', [EventController::class, 'checkin']);
    });

    Route::get('classroom/{classroom}', [ClassroomController::class, 'show']);

    Route::middleware('role:leader')->group(function () {
        Route::apiResource('forms', FormController::class);
        Route::post('classroom', [ClassroomController::class, 'store']);
        Route::put('classroom/{classroom}', [ClassroomController::class, 'update']);
        Route::delete('classroom/{classroom}', [ClassroomController::class, 'destroy']);
        Route::post('event', [EventController::class, 'store']);
        Route::put('event/{event}', [EventController::class, 'update']);
        Route::delete('event/{event}', [EventController::class, 'destroy']);
    });

    Route::middleware('role:media|leader')->group(function () {
        Route::apiResource('media', MediaController::class)
            ->parameters(['media' => 'media'])
            ->only(['update', 'destroy']);
        Route::post('post', [PostController::class, 'store']);
        Route::put('post/{post}', [PostController::class, 'update']);
        Route::delete('post/{post}', [PostController::class, 'destroy']);
    });

    Route::middleware('role:admin|superadmin|system')->group(function () {
        Route::apiResource('church', ChurchController::class)->except(['index', 'show']);
        Route::apiResource('community', CommunityController::class)->except(['index', 'show']);
        Route::apiResource('user', UserController::class);
        Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
        Route::apiResource('settings', SettingController::class);
        Route::apiResource('networks', NetworkController::class);
    });
});
