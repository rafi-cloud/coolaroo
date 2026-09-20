<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DietaryTag;
use App\Services\SettingService;
use Illuminate\View\View;

/**
 * FR44, S16, UC09. The page only renders the brief form — suggestions come
 * from POST /ai/meal-builder (T128) once the guest submits it.
 */
class MealBuilderController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function show(): View
    {
        abort_unless($this->settings->getBool('ai_enabled', true), 404);

        return view('public.meal-builder', [
            'dietaryTags' => DietaryTag::where('is_active', true)->orderBy('tag_name')->get(),
        ]);
    }
}
