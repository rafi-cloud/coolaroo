<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMenuItemRequest;
use App\Http\Requests\Admin\UpdateMenuItemRequest;
use App\Models\Allergen;
use App\Models\DietaryTag;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\MenuImageService;
use App\Services\MenuItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuItemController extends Controller
{
    public function __construct(
        private MenuItemService $menuItems,
        private MenuImageService $images,
    ) {
    }

    public function index(): View
    {
        return view('admin.menu-items.index', [
            'items' => MenuItem::with('category')->orderBy('item_name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.menu-items.create', $this->formOptions());
    }

    public function store(StoreMenuItemRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'allergens', 'dietary_tags']);
        $data['image_path'] = $this->images->store($request->file('image'));

        $item = $this->menuItems->create(
            $data,
            $request->input('allergens', []),
            $request->input('dietary_tags', []),
        );

        return redirect()->route('admin.menu-items.edit', $item)->with('status', 'item-created');
    }

    public function edit(MenuItem $menuItem): View
    {
        return view('admin.menu-items.edit', $this->formOptions() + [
            'item' => $menuItem,
            'sizes' => $menuItem->sizes()->orderBy('display_order')->get(),
            'selectedAllergens' => $menuItem->allergens()->pluck('allergen_id')->all(),
            'selectedDietaryTags' => $menuItem->dietaryTags()->pluck('dietary_tag_id')->all(),
        ]);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'allergens', 'dietary_tags']);

        if ($request->hasFile('image')) {
            $this->images->delete($menuItem->image_path);
            $data['image_path'] = $this->images->store($request->file('image'));
        }

        $this->menuItems->update(
            $menuItem,
            $data,
            $request->input('allergens', []),
            $request->input('dietary_tags', []),
        );

        return redirect()->route('admin.menu-items.edit', $menuItem)->with('status', 'item-updated');
    }

    public function archive(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $this->menuItems->archive($menuItem, $request->user('staff'), $request->string('reason')->value() ?: null);

        return redirect()->route('admin.menu-items.index')->with('status', 'item-archived');
    }

    public function unarchive(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $this->menuItems->unarchive($menuItem, $request->user('staff'));

        return redirect()->route('admin.menu-items.index')->with('status', 'item-unarchived');
    }

    public function toggleFeatured(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $this->menuItems->setFeatured($menuItem, ! $menuItem->is_featured);

        return redirect()->route('admin.menu-items.index')->with('status', 'item-updated');
    }

    private function formOptions(): array
    {
        return [
            'categories' => MenuCategory::orderBy('category_name')->get(),
            'allergens' => Allergen::where('is_active', true)->orderBy('allergen_name')->get(),
            'dietaryTags' => DietaryTag::where('is_active', true)->orderBy('tag_name')->get(),
        ];
    }
}
