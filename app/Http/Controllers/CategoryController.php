<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(Category::query()
            ->when($request->church_id, fn ($query, $churchId) => $query->where('church_id', $churchId))
            ->when($request->type, fn ($query, $type) => $query->where('type', $type))
            ->paginate(15));
    }

    public function show(Category $category)
    {
        return response()->json($category);
    }

    public function store(Request $request)
    {
        $category = Category::create($request->validate([
            'church_id' => ['required', 'exists:churches,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CategoryType::class)],
        ]));

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        $category->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(CategoryType::class)],
        ]));

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return response()->noContent();
    }
}
