<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Services\SettingService;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
    ) {}

    public function index(): View
    {
        $nonHidden = Feedback::where('is_hidden', false);

        $reviewCount = (clone $nonHidden)->count();
        $minReviews = $this->settingService->getInt('public_rating_min_count', 10);
        $showRatingCard = $reviewCount >= $minReviews;

        $ratingStats = null;

        if ($showRatingCard) {
            $foodAvg = round((float) (clone $nonHidden)->avg('food_rating'), 1);
            $serviceAvg = round((float) (clone $nonHidden)->avg('service_rating'), 1);

            $ratingStats = [
                'count' => $reviewCount,
                'food_avg' => number_format($foodAvg, 1),
                'food_pct' => round(($foodAvg / 5) * 100),
                'service_avg' => number_format($serviceAvg, 1),
                'service_pct' => round(($serviceAvg / 5) * 100),
                'overall_avg' => number_format(round(($foodAvg + $serviceAvg) / 2, 1), 1),
            ];
        }

        return view('public.reviews', [
            'venue' => $this->settingService->venue(),
            'reviews' => (clone $nonHidden)
                ->with('customer')
                ->latest('submitted_at')
                ->paginate(12),
            'ratingStats' => $ratingStats,
            'showRatingCard' => $showRatingCard,
            'reviewCount' => $reviewCount,
        ]);
    }
}
