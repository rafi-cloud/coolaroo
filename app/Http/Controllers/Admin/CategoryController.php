<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\MenuCategory;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categories)
    {
    }

    public function index(): View
    {
        return view('admin.catalogue.categories.index', [
            'categories' => MenuCategory::with('parent')->orderBy('display_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.catalogue.categories.create', [
            'topLevelCategories' => MenuCategory::whereNull('parent_category_id')->orderBy('category_name')->get(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->categories->create($request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'category-created');
    }

    public function edit(MenuCategory $category): View
    {
        return view('admin.catalogue.categories.edit', [
            'category' => $category,
            'topLevelCategories' => MenuCategory::whereNull('parent_category_id')
                ->where('category_id', '!=', $category->category_id)
                ->orderBy('category_name')
                ->get(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, MenuCategory $category): RedirectResponse
    {
        $this->categories->update($category, $request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'category-updated');
    }

    public function deactivate(Request $request, MenuCategory $category): RedirectResponse
    {
        $this->categories->deactivate($category, $request->user('staff'), $request->string('reason')->value() ?: null);

        return redirect()->route('admin.categories.index')->with('status', 'category-deactivated');
    }

    public function reactivate(Request $request, MenuCategory $category): RedirectResponse
    {
        $this->categories->reactivate($category, $request->user('staff'));

        return redirect()->route('admin.categories.index')->with('status', 'category-reactivated');
    }

    public function destroy(MenuCategory $category): RedirectResponse
    {
        $this->categories->delete($category);

        return redirect()->route('admin.categories.index')->with('status', 'category-deleted');
    }
}
