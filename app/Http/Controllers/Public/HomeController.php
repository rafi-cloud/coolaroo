<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\SettingService;
use App\Services\SpecialsService;
use Illuminate\View\View;

/**
 * Public homepage.
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

        // Public ratings section
        $minReviews = $this->settingService->getInt('public_rating_min_count', 10);
        $nonHidden = Feedback::where('is_hidden', false);
        $reviewCount = (clone $nonHidden)->count();

        $showRatingCard = $reviewCount >= $minReviews;
        $ratingStats = null;

        if ($showRatingCard) {
            $foodAvg = round((float) (clone $nonHidden)->avg('food_rating'), 1);
            $serviceAvg = round((float) (clone $nonHidden)->avg('service_rating'), 1);
            $overallAvg = round(($foodAvg + $serviceAvg) / 2, 1);

            $ratingStats = [
                'count' => $reviewCount,
                'food_avg' => number_format($foodAvg, 1),
                'food_pct' => round(($foodAvg / 5) * 100),
                'service_avg' => number_format($serviceAvg, 1),
                'service_pct' => round(($serviceAvg / 5) * 100),
                'overall_avg' => number_format($overallAvg, 1),
            ];
        }

        $featuredReviews = (clone $nonHidden)
            ->where('is_featured', true)
            ->with('customer')
            ->latest('submitted_at')
            ->take(3)
            ->get();

        return view('public.home', [
            'venue' => $this->settingService->venue(),
            'featuredItems' => $featuredItems,
            'topSpecial' => $topSpecial,
            'hasSpecials' => $hasSpecials,
            'categories' => $categories,
            'ratingStats' => $ratingStats,
            'featuredReviews' => $featuredReviews,
            'showRatingCard' => $showRatingCard,
        ]);
    }
}
