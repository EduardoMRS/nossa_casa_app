<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Classroom;
use App\Models\Event;
use App\Models\Form;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $churchId = $request->user()?->church?->id;

        $categories = Category::query()
            ->when($churchId, fn ($query) => $query->where('church_id', $churchId))
            ->when($request->church_id, fn ($query, $churchId) => $query->where('church_id', $churchId))
            ->when($request->type, fn ($query, $type) => $query->where('type', $type))
            ->paginate(15)
            ->through(fn (Category $category): Category => $category->localize());

        return response()->json($categories);
    }

    public function show(Category $category)
    {
        return response()->json($category->localize());
    }

    public function store(Request $request)
    {
        $churchId = $request->user()?->church?->id;
        abort_unless($churchId !== null, 422, __('church.membership_category_manage_required'));

        $category = Category::create([
            'church_id' => $churchId,
            ...$request->validate([
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255'],
                'type' => ['required', Rule::enum(CategoryType::class)],
            ]),
        ]);

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.category_created')]);

            return back();
        }

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        $this->ensureChurchAccess($request, $category->church_id);

        $category->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(CategoryType::class)],
        ]));

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.category_updated')]);

            return back();
        }

        return response()->json($category);
    }

    public function destroy(Request $request, Category $category)
    {
        $this->ensureChurchAccess($request, $category->church_id);
        $category->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.category_deleted')]);

            return back();
        }

        return response()->noContent();
    }

    public function categorizeItem(Request $request, string $item_type, string $item_id)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'string', 'exists:categories,id'],
        ]);

        $category = Category::query()->findOrFail($validated['category_id']);
        abort_unless($category->church_id, 422, __('category.church_required'));
        $this->ensureChurchAccess($request, $category->church_id);
        $modelClass = match ($item_type) {
            'post' => Post::class,
            'event' => Event::class,
            'media' => Media::class,
            'classroom' => Classroom::class,
            'form' => Form::class,
            default => abort(404, __('category.invalid_type')),
        };

        $model = $modelClass::findOrFail($item_id);
        $this->ensureChurchAccess($request, $model->church_id);

        $requiredCategoryType = match ($item_type) {
            'post' => CategoryType::POST,
            'event' => CategoryType::EVENT,
            'media' => CategoryType::MEDIA,
            'classroom' => CategoryType::CLASSROOM,
            'form' => CategoryType::FORM,
        };

        abort_unless($category->type === $requiredCategoryType, 422, __('category.type_mismatch'));
        // Sincroniza a categoria sem remover as anteriores usando o relacionamento polimórfico
        $model->categories()->syncWithoutDetaching([$validated['category_id']]);

        return response()->json(['message' => __('category.assignment_success')], 200);
    }
}
