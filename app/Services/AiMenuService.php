<?php

namespace App\Services;

use App\Exceptions\AiUnavailableException;
use App\Models\AddOnGroup;
use App\Models\AddOnOption;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 07.4, 07.9. GitHub Models' OpenAI-compatible chat completions endpoint.
 * chat() is the client (T120); buildContext() is the live menu/venue
 * context (T121, BR46). The prompt and structured output schema (T122) and
 * the /ai/chat and meal-builder endpoints that call chat() (T127, T128) are
 * separate tasks.
 */
class AiMenuService
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly SpecialsService $specials,
    ) {
    }

    /**
     * BR49: a missing key, a timeout, a 429, a 5xx, or any other failure all
     * surface to the caller as the same AiUnavailableException — the real
     * cause is logged to the `integrations` channel (07.11) here, once, so
     * nothing downstream needs to know GitHub Models' error shape.
     *
     * @param array<int, array{role:string, content:string}> $messages
     * @param array<string, mixed>|null $responseFormat Passed through as
     *     `response_format` (OpenAI-compatible structured output) — T122
     *     fills this in; optional here.
     * @return array<string, mixed> Decoded chat completion response body.
     */
    public function chat(array $messages, ?array $responseFormat = null): array
    {
        $apiKey = config('services.ai.api_key');

        if (empty($apiKey)) {
            Log::channel('integrations')->error('AI request skipped: AI_API_KEY not configured');

            throw new AiUnavailableException();
        }

        $payload = array_filter([
            'model' => config('services.ai.model'),
            'messages' => $messages,
            'response_format' => $responseFormat,
        ], fn ($value) => $value !== null);

        try {
            $response = Http::withToken($apiKey)
                ->baseUrl(config('services.ai.base_url'))
                ->timeout(config('services.ai.timeout'))
                ->post('/chat/completions', $payload);
        } catch (ConnectionException $e) {
            Log::channel('integrations')->error('AI request timed out', ['message' => $e->getMessage()]);

            throw new AiUnavailableException();
        }

        if ($response->failed()) {
            Log::channel('integrations')->error('AI request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new AiUnavailableException();
        }

        return $response->json();
    }

    /**
     * BR46: currently available items, sizes, options, prices, allergens,
     * nutrition, plus venue name, address and hours. No customer personal
     * data. `is_active && is_available` is the same "show this" signal the
     * public menu (T033) already uses — not StockService's stricter,
     * buffered QR-checkout check, a different question.
     *
     * @return array<string, mixed>
     */
    public function buildContext(): array
    {
        $venue = $this->settings->venue();

        $items = MenuItem::where('is_active', true)
            ->where('is_available', true)
            ->with([
                'category:category_id,category_name',
                'sizes' => fn ($q) => $q->where('is_active', true)->orderBy('display_order'),
                'addOnGroups' => fn ($q) => $q->orderBy('display_order'),
                'addOnGroups.options' => fn ($q) => $q->where('is_active', true)->where('is_available', true)->orderBy('display_order'),
                'allergens' => fn ($q) => $q->where('is_active', true),
                'dietaryTags' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderBy('category_id')->orderBy('item_name')
            ->get()
            ->map(fn (MenuItem $item) => $this->itemContext($item))
            ->values()
            ->all();

        return [
            'venue' => [
                'name' => $venue['name'],
                'address' => $venue['address'],
                'hours' => $venue['formatted_hours'],
            ],
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private function itemContext(MenuItem $item): array
    {
        $sizes = $item->sizes->map(fn (MenuItemSize $size) => [
            'name' => $size->size_name,
            'price' => (float) ($this->specials->isSaleActive($size) ? $size->sale_price : $size->price),
        ]);

        $context = [
            'id' => $item->item_id,
            'name' => $item->item_name,
            'cat' => $item->category->category_name,
        ];

        if ($sizes->count() === 1) {
            $context['price'] = $sizes->first()['price'];
        } else {
            $context['sizes'] = $sizes->values()->all();
        }

        if ($item->addOnGroups->isNotEmpty()) {
            $context['addons'] = $item->addOnGroups->map(fn (AddOnGroup $group) => [
                'group' => $group->group_name,
                'required' => $group->is_required,
                'min' => $group->min_select,
                'max' => $group->max_select,
                'opts' => $group->options->map(fn (AddOnOption $opt) => [
                    'name' => $opt->option_name,
                    'delta' => (float) $opt->price_delta,
                ])->values()->all(),
            ])->values()->all();
        }

        if ($item->allergens->isNotEmpty()) {
            $context['allergens'] = $item->allergens->pluck('allergen_name')->values()->all();
        }

        if ($item->dietaryTags->isNotEmpty()) {
            $context['diet'] = $item->dietaryTags->pluck('tag_name')->values()->all();
        }

        $nutrition = array_filter([
            'kcal' => $item->calories_kcal !== null ? (float) $item->calories_kcal : null,
            'protein' => $item->protein_g !== null ? (float) $item->protein_g : null,
            'carbs' => $item->carbohydrates_g !== null ? (float) $item->carbohydrates_g : null,
            'fat' => $item->fat_g !== null ? (float) $item->fat_g : null,
        ], fn ($value) => $value !== null);

        if (! empty($nutrition)) {
            $context['nutrition'] = $nutrition;
        }

        return $context;
    }
}
