<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function index()
    {
        // Utilizando o local scope "visible" criado no seu model
        $posts = Post::visible()->with(['author', 'categories'])->paginate(15);
        return response()->json($posts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'slug'         => 'required|string|max:255|unique:posts',
            'content'      => 'required|string',
            'published_at' => 'nullable|date',
            'expires_at'   => 'nullable|date|after:published_at',
            'author_id'    => 'required|string|exists:users,id',
            'church_id'    => 'nullable|string|exists:churches,id',
        ]);

        $post = Post::create($validated);

        return response()->json($post, 201);
    }

    public function show(string $id)
    {
        $post = Post::with(['author', 'church', 'categories', 'medias', 'comments'])->findOrFail($id);
        return response()->json($post);
    }

    public function update(Request $request, string $id)
    {
        $post = Post::findOrFail($id);

        $validated = $request->validate([
            'title'        => 'sometimes|required|string|max:255',
            'slug'         => ['sometimes', 'required', 'string', 'max:255', Rule::unique('posts')->ignore($post->id)],
            'content'      => 'sometimes|required|string',
            'published_at' => 'nullable|date',
            'expires_at'   => 'nullable|date|after:published_at',
            'author_id'    => 'sometimes|required|string|exists:users,id',
            'church_id'    => 'nullable|string|exists:churches,id',
        ]);

        $post->update($validated);

        return response()->json($post);
    }

    public function destroy(string $id)
    {
        $post = Post::findOrFail($id);
        $post->delete();

        return response()->json(null, 204);
    }
}
