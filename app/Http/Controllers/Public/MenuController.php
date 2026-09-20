<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Allergen;
use App\Models\DietaryTag;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use App\Services\SettingService;
use App\Services\SpecialsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * S02, FR32, FR33, FR34, BR59, BR61: Full menu browsing and filtering.
 */
class MenuController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly SpecialsService $specialsService,
    ) {}

    public function index(Request $request): View
    {
        $selectedCategory = $request->query('category', 'all');
        $selectedDietary = (array) $request->query('dietary', []);
        $selectedAllergens = (array) $request->query('exclude_allergen', []);
        $search = trim((string) $request->query('q', ''));

        $query = MenuItem::where('is_active', true)
            ->with([
                'category',
                'sizes' => fn ($q) => $q->where('is_active', true)->orderBy('price'),
                'addOnGroups.options' => fn ($q) => $q->where('is_active', true)->orderBy('price_delta'),
                'dietaryTags' => fn ($q) => $q->where('is_active', true),
                'allergens' => fn ($q) => $q->where('is_active', true),
            ]);

        // Category filter (including Specials per BR59)
        if ($selectedCategory === 'specials') {
            $query->whereHas('sizes', function ($q) {
                $q->whereNotNull('sale_price')
                    ->where('is_active', true)
                    ->where(fn ($sq) => $sq->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', now()))
                    ->where(fn ($sq) => $sq->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', now()));
            });
        } elseif ($selectedCategory !== 'all' && is_numeric($selectedCategory)) {
            $query->where('category_id', (int) $selectedCategory);
        }

        // Dietary tags filter
        foreach ($selectedDietary as $dietaryId) {
            if (is_numeric($dietaryId)) {
                $query->whereHas('dietaryTags', fn ($q) => $q->where('dietary_tag.dietary_tag_id', (int) $dietaryId));
            }
        }

        // Allergen filter (exclude items that contain any of the selected allergens, BR61)
        $allergenIds = array_filter($selectedAllergens, 'is_numeric');
        if (! empty($allergenIds)) {
            $query->whereDoesntHave('allergens', fn ($q) => $q->whereIn('allergen.allergen_id', $allergenIds));
        }

        // Search query
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('category_id')->orderBy('item_name')->get();

        $categories = MenuCategory::where('is_active', true)
            ->whereNull('parent_category_id')
            ->orderBy('display_order')
            ->get();

        $hasSpecials = $this->specialsService->itemsOnSpecial()->isNotEmpty();
        $dietaryTags = DietaryTag::where('is_active', true)->orderBy('tag_name')->get();
        $allergens = Allergen::where('is_active', true)->orderBy('allergen_name')->get();

        // Table context check
        $tableId = $request->session()->get('table_id');
        $table = $tableId ? RestaurantTable::find($tableId) : null;
        $tableLabel = $table ? ('Table ' . $table->table_number) : null;

        return view('public.menu', [
            'venue' => $this->settingService->venue(),
            'items' => $items,
            'categories' => $categories,
            'hasSpecials' => $hasSpecials,
            'dietaryTags' => $dietaryTags,
            'allergens' => $allergens,
            'selectedCategory' => $selectedCategory,
            'selectedDietary' => array_map('intval', $selectedDietary),
            'selectedAllergens' => array_map('intval', $selectedAllergens),
            'search' => $search,
            'table' => $table,
            'tableLabel' => $tableLabel,
            'qrOrderingEnabled' => $this->settingService->getBool('qr_ordering_enabled', true),
        ]);
    }
}
