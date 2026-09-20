<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\AiChatRequest;
use App\Http\Requests\Public\AiMealBuilderRequest;
use App\Services\AiMenuService;
use App\Services\AuditLogger;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * FR43, FR44, UC09, BR47–BR49. Public on purpose — BR49: "available to
 * everyone including visitors, with no app rate limit", so there is no
 * auth middleware and deliberately no throttle on these routes.
 */
class AiController extends Controller
{
    public function __construct(
        private readonly AiMenuService $ai,
        private readonly SettingService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function chat(AiChatRequest $request): JsonResponse
    {
        abort_unless($this->settings->getBool('ai_enabled', true), 404);

        $result = $this->ai->answerQuestion(
            $request->validated('message'),
            $request->validated('history', []),
        );

        $this->audit->logAi(
            Auth::guard('customer')->user(),
            'chat',
            $result['usage'],
            $request->ip(),
        );

        return response()->json([
            'answer' => $result['answer'],
            'items' => $result['items'],
            'disclaimer' => AiMenuService::ALLERGEN_DISCLAIMER,
        ]);
    }

    public function mealBuilder(AiMealBuilderRequest $request): JsonResponse
    {
        abort_unless($this->settings->getBool('ai_enabled', true), 404);

        $result = $this->ai->buildMeal($request->validated());

        $this->audit->logAi(
            Auth::guard('customer')->user(),
            'meal_builder',
            $result['usage'],
            $request->ip(),
        );

        return response()->json([
            'summary' => $result['summary'],
            'suggestions' => $result['suggestions'],
            'can_add_to_cart' => $request->session()->get('table_id') !== null,
            'disclaimer' => AiMenuService::ALLERGEN_DISCLAIMER,
        ]);
    }
}
