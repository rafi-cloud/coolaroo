<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\SettingService;
use App\Services\SpecialsService;
use Illuminate\View\View;

/**
 * S01, FR32, FR80, FR91, BR59: Public homepage.
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly SpecialsService $specialsService,
    ) {}

    public function index(): View
    {
        $featuredItems = MenuItem::where('is_active', true)
            ->where('is_featured', true)
            ->with([
                'sizes' => fn ($q) => $q->where('is_active', true)->orderBy('price'),
                'dietaryTags' => fn ($q) => $q->where('is_active', true),
            ])
            ->take(12)
            ->get();

        $topSpecial = $this->specialsService->topSpecial();
        $hasSpecials = $this->specialsService->itemsOnSpecial()->isNotEmpty();

        $categories = MenuCategory::where('is_active', true)
            ->whereNull('parent_category_id')
            ->orderBy('display_order')
            ->get();

        return view('public.home', [
            'venue' => $this->settingService->venue(),
            'featuredItems' => $featuredItems,
            'topSpecial' => $topSpecial,
            'hasSpecials' => $hasSpecials,
            'categories' => $categories,
        ]);
    }
}
