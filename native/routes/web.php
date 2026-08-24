<?php

use App\NativeComponents\ChurchSelector;
use App\NativeComponents\ContentDetail;
use App\NativeComponents\Login;
use App\NativeComponents\Offline;
use App\NativeComponents\Portal;
use App\NativeComponents\Server;
use Illuminate\Support\Facades\Route;

Route::native('/', Server::class);
Route::native('/server', Server::class);
Route::native('/login', Login::class);
Route::native('/portal', Portal::class);
Route::native('/church-selector', ChurchSelector::class);
Route::native('/offline', Offline::class);
Route::native('/posts/{slug}', ContentDetail::class)->defaults('contentType', 'post');
Route::native('/events/{slug}', ContentDetail::class)->defaults('contentType', 'event');
Route::native('/live-streams/{id}', ContentDetail::class)->defaults('contentType', 'live_stream');
