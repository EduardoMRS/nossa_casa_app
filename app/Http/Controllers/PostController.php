<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Models\Post;
use App\Traits\ManagesChurchCategories;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    use ManagesChurchCategories;

    public function index()
    {
        // Utilizando o local scope "visible" criado no seu model
        $posts = Post::visible()->paginate(15)->map(function ($post) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'content' => $post->content,
                'published_at' => $post->published_at,
                'expires_at' => $post->expires_at,
                'author_id' => $post->author_id,
                'church_id' => $post->church_id,
                'category' => $post->category,
                'metrics' => $post->metrics,
                'author' => $post->author_details, // Incluindo os detalhes do autor
                'church' => $post->church,
            ];
        });

        return response()->json($posts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:posts',
            'content' => 'required|string',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
            'author_id' => 'nullable|string|exists:users,id',
            'church_id' => 'nullable|string|exists:churches,id',
        ]);

        $validated['author_id'] = $request->user()->id;
        $validated['church_id'] = $request->user()->church?->id;
        abort_unless($validated['church_id'], 422, __('church.membership_post_create_required'));

        $post = Post::create($validated);
        $post->categories()->sync($this->syncChurchCategories($request, CategoryType::POST->value, $validated['church_id']));

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
        $this->ensureChurchAccess($request, $post->church_id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('posts')->ignore($post->id)],
            'content' => 'sometimes|required|string',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
        ]);

        $post->update($validated);
        if ($request->has('category_ids')) {
            $post->categories()->sync($this->syncChurchCategories($request, CategoryType::POST->value, $post->church_id));
        }

        return response()->json($post);
    }

    public function destroy(string $id)
    {
        $post = Post::findOrFail($id);
        $this->ensureChurchAccess(request(), $post->church_id);
        $post->delete();

        return response()->json(null, 204);
    }
}
