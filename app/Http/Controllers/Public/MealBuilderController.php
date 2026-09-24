<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DietaryTag;
use App\Services\SettingService;
use Illuminate\View\View;

class MealBuilderController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function show(): View
    {
        abort_unless($this->settings->getBool('ai_enabled', true), 404);

        return view('public.meal-builder', [
            'dietaryTags' => DietaryTag::where('is_active', true)
                ->whereHas('menuItems', fn ($q) => $q
                    ->where('is_active', true)
                    ->where('is_available', true))
                ->orderBy('tag_name')
                ->get(),
        ]);
    }
}
