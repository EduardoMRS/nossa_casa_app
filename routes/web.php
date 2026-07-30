<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

Route::inertia('/', 'Welcome')->name('home');
Route::get('/d/{encryptedFile}', function($encryptedFile){
    try {
        // Decrypt the file path
        $filePath = Crypt::decryptString($encryptedFile);
        $file = getFileMetadata($filePath);
        
        // Check if file exists
        if (!$file['exists']) {
            abort(404);
        }

        // Return the file
        return response()->file($file['path']);
        
    } catch (\Exception $e) {
        abort(403, 'Invalid or corrupted file link.');
    }
})->name('secure-file');   

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // // Churches (Igrejas)
    // Route::get('/churches', function () { return Inertia::render('Churches/Index'); })->name('churches.index');
    // Route::get('/churches/create', function () { return Inertia::render('Churches/Create'); })->name('churches.create');
    // Route::get('/churches/{id}/edit', function ($id) { return Inertia::render('Churches/Edit', ['id' => $id]); })->name('churches.edit');

    // // Events (Eventos)
    // Route::get('/events', function () { return Inertia::render('Events/Index'); })->name('events.index');
    // Route::get('/events/create', function () { return Inertia::render('Events/Create'); })->name('events.create');
    
    // // Posts (Postagens)
    Route::get('/posts', function () { 
        $posts = \App\Models\Post::visible()->orderBy('created_at', 'desc')->paginate(10)->through(function ($post) {
           return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $post->content,
            'published_at' => $post->published_at,
            'expires_at' => $post->expires_at,
            'author' => $post->author_details,
            'metrics' => $post->metrics,
            'translations' => $post->translations,
            'media' => $post->medias()->get()->map(function ($media) {
                return [
                    'id' => $media->id,
                    'name' => $media->name,
                    'url' => $media->url,
                    'type' => $media->type,
                ];
            }),
           ];
        });
        return Inertia::render('Posts/Index', ['posts' => $posts]);
    })->name('posts.index');
    Route::get('/posts/create', function () { return Inertia::render('Posts/Form'); })->name('posts.create');
    Route::get('/posts/{post}/edit', function ($post) { 
        $post = \App\Models\Post::visible()->with(['church', 'medias'])->findOrFail(request()->route('post'));
        $church = $post->church;
        $props = [
            'post' => $post,
            'available_categories' => $church->categories()->get(['id', 'name']),
        ];
        return Inertia::render('Posts/Form', $props);
    })->name('posts.edit');
    Route::get('/posts/{post}', function () { 
        $post = \App\Models\Post::visible()->with(['church', 'medias'])->findOrFail(request()->route('post'));
        $props = [
            'can' => [
                'edit' => auth()->user()->can('update', $post),
                'delete' => auth()->user()->can('delete', $post),
                'comment' => auth()->user()->can('comment', $post),
                'react' => auth()->user()->can('react', $post),
            ],
            'post' => $post,
            'author' => $post->author_details,
            'metrics' => $post->metrics,
            'translations' => $post->translations,
            'media' => $post->medias()->get()->map(function ($media) {
                return [
                    'id' => $media->id,
                    'name' => $media->name,
                    'url' => $media->url,
                    'type' => $media->type,
                ];
            }),
            'comments' => $post->comments()->with('replies')->orderBy('created_at', 'desc')->get(),
            'reactions' => $post->reactions()->get(),
        ];
        return Inertia::render('Posts/Show', $props);
    })->name('posts.show');
    
    // // Categories (Categorias - Baseado no api.json)
    // Route::get('/categories', function () { return Inertia::render('Categories/Index'); })->name('categories.index');
});

require __DIR__.'/settings.php';
Route::group(['prefix' => 'api'], function () {
    require __DIR__.'/api.php';
});