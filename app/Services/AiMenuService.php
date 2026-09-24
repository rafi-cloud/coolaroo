<?php

namespace App\Services;

use App\Exceptions\AiUnavailableException;
use App\Models\AddOnGroup;
use App\Models\AddOnOption;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiMenuService
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly SpecialsService $specials,
    ) {}

    /**
     * @param  array<int, array{role:string, content:string}>  $messages
     * @param  array<string, mixed>|null  $responseFormat  Passed through as
     * @return array<string, mixed> Decoded chat completion response body.
     */
    public function chat(array $messages, ?array $responseFormat = null): array
    {
        $apiKey = config('services.ai.api_key');

        if (empty($apiKey)) {
            Log::channel('integrations')->error('AI request skipped: AI_API_KEY not configured');

            throw new AiUnavailableException;
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

            throw new AiUnavailableException;
        }

        if ($response->failed()) {
            Log::channel('integrations')->error('AI request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new AiUnavailableException;
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContext(): array
    {
        return Cache::remember(
            'ai:context:'.$this->contextFingerprint(),
            now()->addDay(),
            fn () => $this->freshContext(),
        );
    }

    public function contextFingerprint(): string
    {
        $items = MenuItem::selectRaw('COUNT(*) as c, MAX(updated_at) as m')->first();

        $sizes = MenuItemSize::selectRaw(
            'COUNT(*) as c, MAX(size_id) as x, SUM(price) as p, SUM(COALESCE(sale_price, 0)) as s, SUM(is_active) as a'
        )->first();

        $onSale = MenuItemSize::whereNotNull('sale_price')
            ->where(fn ($q) => $q->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', now()))
            ->count();

        return sha1(implode('|', [
            $items->c, $items->m,
            $sizes->c, $sizes->x, $sizes->p, $sizes->s, $sizes->a,
            $onSale,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function freshContext(): array
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

    private const HISTORY_TURNS = 6;

    /**
     * @param  array<int, array{role:string, content:string}>  $history
     * @return array{answer:string, items:array<int,array<string,mixed>>, usage:array{tokens_in:int, tokens_out:int}, cached:bool}
     */
    public function answerQuestion(string $message, array $history = []): array
    {
        if ($history !== []) {
            return $this->freshAnswer($message, $history) + ['cached' => false];
        }

        $key = 'ai:chat:'.$this->contextFingerprint().':'.sha1(mb_strtolower(trim($message)));

        if (is_array($hit = Cache::get($key))) {
            return $hit + ['usage' => ['tokens_in' => 0, 'tokens_out' => 0], 'cached' => true];
        }

        $result = $this->freshAnswer($message, []);

        Cache::put($key, [
            'answer' => $result['answer'],
            'items' => $result['items'],
        ], config('services.ai.cache_ttl'));

        return $result + ['cached' => false];
    }

    /**
     * @param  array<int, array{role:string, content:string}>  $history
     * @return array{answer:string, items:array<int,array<string,mixed>>, usage:array{tokens_in:int, tokens_out:int}}
     */
    private function freshAnswer(string $message, array $history): array
    {
        $context = $this->buildContext();

        $response = $this->chat(
            array_merge(
                [['role' => 'system', 'content' => $this->chatSystemPrompt($context)]],
                array_slice($history, -self::HISTORY_TURNS),
                [['role' => 'user', 'content' => $message]],
            ),
            $this->chatResponseFormat(),
        );

        $payload = $this->applyOffTopicGuard($this->decodeStructured($response));

        return [
            'answer' => (string) ($payload['answer'] ?? ''),
            'items' => $this->resolveItems($payload['item_ids'] ?? [], $context),
            'usage' => $this->usage($response),
        ];
    }

    /**
     * @param  array{budget:float|int|string, party_size:int, dietary?:array<int,string>, preferences?:?string}  $brief
     * @return array{summary:string, suggestions:array<int,array<string,mixed>>, usage:array{tokens_in:int, tokens_out:int}, cached:bool}
     */
    public function buildMeal(array $brief): array
    {
        $key = 'ai:meal:'.$this->contextFingerprint().':'.sha1((string) json_encode([
            (float) $brief['budget'],
            (int) $brief['party_size'],
            array_values(array_map('strval', $brief['dietary'] ?? [])),
            mb_strtolower(trim((string) ($brief['preferences'] ?? ''))),
        ]));

        if (is_array($hit = Cache::get($key))) {
            return $hit + ['usage' => ['tokens_in' => 0, 'tokens_out' => 0], 'cached' => true];
        }

        $result = $this->freshMeal($brief);

        Cache::put($key, [
            'summary' => $result['summary'],
            'suggestions' => $result['suggestions'],
        ], config('services.ai.cache_ttl'));

        return $result + ['cached' => false];
    }

    /**
     * @param  array{budget:float|int|string, party_size:int, dietary?:array<int,string>, preferences?:?string}  $brief
     * @return array{summary:string, suggestions:array<int,array<string,mixed>>, usage:array{tokens_in:int, tokens_out:int}}
     */
    private function freshMeal(array $brief): array
    {
        $context = $this->buildContext();

        $response = $this->chat(
            [
                ['role' => 'system', 'content' => $this->mealBuilderSystemPrompt($context)],
                ['role' => 'user', 'content' => $this->briefLine($brief)],
            ],
            $this->mealBuilderResponseFormat(),
        );

        $payload = $this->applyOffTopicGuard($this->decodeStructured($response));
        $byId = collect($context['items'])->keyBy('id');
        $budget = (float) $brief['budget'];

        $suggestions = collect($payload['suggestions'] ?? [])
            ->map(fn ($suggestion) => is_array($suggestion)
                ? $this->priceSuggestion($suggestion, $byId, $budget)
                : null)
            ->filter()
            ->values()
            ->all();

        return [
            'summary' => (string) ($payload['summary'] ?? ''),
            'suggestions' => $suggestions,
            'usage' => $this->usage($response),
        ];
    }

    /**
     * @param  array<string, mixed>  $brief
     */
    private function briefLine(array $brief): string
    {
        $parts = [
            'Budget: $'.number_format((float) $brief['budget'], 2).' for the whole table.',
            'Party size: '.(int) $brief['party_size'].'.',
        ];

        if (! empty($brief['dietary'])) {
            $parts[] = 'Dietary needs: '.implode(', ', $brief['dietary']).'.';
        }

        if (! empty($brief['preferences'])) {
            $parts[] = 'Preferences: '.$brief['preferences'];
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $suggestion
     * @param  Collection<int, array<string, mixed>>  $byId
     * @return array<string, mixed>|null
     */
    private function priceSuggestion(array $suggestion, Collection $byId, float $budget): ?array
    {
        $lines = [];
        $total = 0.0;

        foreach ($suggestion['lines'] ?? [] as $line) {
            $priced = is_array($line) ? $this->priceLine($line, $byId) : null;

            if ($priced === null) {
                return null;
            }

            $lines[] = $priced;
            $total += $priced['line_total'];
        }

        $total = round($total, 2);

        if ($lines === [] || $total > $budget) {
            return null;
        }

        return [
            'title' => (string) ($suggestion['title'] ?? ''),
            'rationale' => (string) ($suggestion['rationale'] ?? ''),
            'total' => $total,
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  Collection<int, array<string, mixed>>  $byId
     * @return array<string, mixed>|null
     */
    private function priceLine(array $line, Collection $byId): ?array
    {
        $item = $byId[$line['item_id'] ?? null] ?? null;
        $quantity = (int) ($line['qty'] ?? 0);

        if ($item === null || $quantity < 1) {
            return null;
        }

        $size = $this->sizeFrom($item, $line['size_id'] ?? null);
        $options = $this->optionsFrom($item, (array) ($line['option_ids'] ?? []));

        if ($size === null || $options === null) {
            return null;
        }

        $unitPrice = $size['price'] + array_sum(array_column($options, 'delta'));

        return [
            'item_id' => $item['id'],
            'size_id' => $size['id'],
            'add_on_option_ids' => array_column($options, 'id'),
            'quantity' => $quantity,
            'name' => $item['name'],
            'size_name' => $size['name'],
            'options' => array_column($options, 'name'),
            'line_total' => round($unitPrice * $quantity, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{id:int, name:string, price:float}|null
     */
    private function sizeFrom(array $item, mixed $sizeId): ?array
    {
        if (isset($item['sizes'])) {
            $match = collect($item['sizes'])->firstWhere('id', $sizeId);

            return $match ? ['id' => $match['id'], 'name' => $match['name'], 'price' => (float) $match['price']] : null;
        }

        return $item['size_id'] === $sizeId
            ? ['id' => $item['size_id'], 'name' => '', 'price' => (float) $item['price']]
            : null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, mixed>  $optionIds
     * @return array<int, array<string, mixed>>|null
     */
    private function optionsFrom(array $item, array $optionIds): ?array
    {
        $groups = $item['addons'] ?? [];
        $available = collect($groups)->pluck('opts')->flatten(1)->keyBy('id');
        $chosen = [];

        foreach ($optionIds as $optionId) {
            if (is_int($optionId) && $available->has($optionId)) {
                $chosen[$optionId] = $available[$optionId];
            }
        }

        foreach ($groups as $group) {
            $selected = collect($group['opts'])->pluck('id')->intersect(array_keys($chosen))->count();

            if ($selected < $group['min'] || $selected > $group['max']) {
                return null;
            }
        }

        return array_values($chosen);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function decodeStructured(array $response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? null;
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            Log::channel('integrations')->error('AI response was not valid JSON', ['content' => $content]);

            throw new AiUnavailableException;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{tokens_in:int, tokens_out:int}
     */
    private function usage(array $response): array
    {
        return [
            'tokens_in' => (int) ($response['usage']['prompt_tokens'] ?? 0),
            'tokens_out' => (int) ($response['usage']['completion_tokens'] ?? 0),
        ];
    }

    /**
     * @param  array<int, mixed>  $itemIds
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    private function resolveItems(array $itemIds, array $context): array
    {
        $byId = collect($context['items'])->keyBy('id');

        return collect($itemIds)
            ->filter(fn ($id) => is_int($id) && $byId->has($id))
            ->unique()
            ->map(fn ($id) => [
                'item_id' => $byId[$id]['id'],
                'name' => $byId[$id]['name'],
                'price' => $this->lowestPrice($byId[$id]),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function lowestPrice(array $item): float
    {
        return isset($item['price'])
            ? (float) $item['price']
            : (float) min(array_column($item['sizes'], 'price'));
    }

    public const ALLERGEN_DISCLAIMER = 'Allergen labels reflect the tags stored for each dish and its add-on options. Our kitchen handles nuts, seafood, gluten and dairy, and cross-contact may occur, so please tell our staff about any serious allergy before ordering.';

    public const OFF_TOPIC_REPLY = 'I can only help with the Coolaroo menu — dishes, prices, dietary and allergen tags, and our address and hours.';

    /**
     * @var array<int, array{label:string, question:string}>
     */
    public const STARTER_QUESTIONS = [
        ['label' => '🌱 Vegetarian options', 'question' => 'What are your vegetarian options?'],
        ['label' => '🌾 Gluten-free dishes?', 'question' => 'Do you have gluten-free dishes?'],
        ['label' => '🍽️ Popular bistro mains', 'question' => 'What are your most popular mains?'],
        ['label' => '🍷 Drink recommendations', 'question' => 'What drinks or wines do you recommend?'],
    ];

    /**
     * @param  array<string, mixed>|null  $context  Pass an already-built
     */
    public function chatSystemPrompt(?array $context = null): string
    {
        return $this->basePrompt(<<<'TASK'
            Answer the guest's latest question in at most three short sentences.
            List the item_id of every dish you name in item_ids, in the order you
            name them, and leave it empty when you name none.
            TASK, $context);
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    public function mealBuilderSystemPrompt(?array $context = null): string
    {
        return $this->basePrompt(<<<'TASK'
            The guest gives a budget, a party size, dietary needs and preferences.
            A dietary need is met only when the dish's diet list contains that exact
            tag; never infer it from the dish name or ingredients.
            Offer one to three complete suggestions that fit, each with a short
            title, one sentence saying why it fits, and the exact lines to order:
            item_id, size_id, the option_ids of any add-ons you choose, and qty.
            Every id must come from the data above. Keep each whole suggestion
            inside the budget, but never state a price or a total — the app prices
            the lines. Restate the guest's brief in one sentence in summary, and
            leave suggestions empty when nothing on the menu fits.
            TASK, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public function chatResponseFormat(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'menu_answer',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['answer', 'off_topic', 'item_ids'],
                    'properties' => [
                        'answer' => ['type' => 'string'],
                        'off_topic' => ['type' => 'boolean'],
                        'item_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mealBuilderResponseFormat(): array
    {
        $line = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['item_id', 'size_id', 'option_ids', 'qty'],
            'properties' => [
                'item_id' => ['type' => 'integer'],
                'size_id' => ['type' => 'integer'],
                'option_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ],
                'qty' => ['type' => 'integer'],
            ],
        ];

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'meal_suggestions',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['summary', 'off_topic', 'suggestions'],
                    'properties' => [
                        'summary' => ['type' => 'string'],
                        'off_topic' => ['type' => 'boolean'],
                        'suggestions' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['title', 'rationale', 'lines'],
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'rationale' => ['type' => 'string'],
                                    'lines' => ['type' => 'array', 'items' => $line],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyOffTopicGuard(array $payload): array
    {
        if (! ($payload['off_topic'] ?? false)) {
            return $payload;
        }

        foreach (['answer', 'summary'] as $text) {
            if (array_key_exists($text, $payload)) {
                $payload[$text] = self::OFF_TOPIC_REPLY;
            }
        }

        foreach (['item_ids', 'suggestions'] as $list) {
            if (array_key_exists($list, $payload)) {
                $payload[$list] = [];
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function basePrompt(string $task, ?array $context): string
    {
        $context ??= $this->buildContext();
        $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
            You are the menu assistant for {$context['venue']['name']}, a bistro and sports bar. You help guests browsing the website and guests ordering at a table.

            RULES
            1. The JSON under MENU AND VENUE DATA is your only source of truth. Never name a dish, size, add-on, price or opening hour that is not in it, and never invent or guess an id.
            2. The allergen tags stored against a dish are the only allergen facts you have. Never infer an allergen from a dish name, a description or your own knowledge of how something is usually made. If a guest asks about an allergen that is not tagged, say it is not recorded for that dish and that our staff can check. Do not write your own allergy warning; the app shows a fixed one.
            3. Quote prices exactly as they appear in the data, in AUD and GST inclusive. Never add prices up and never state a total — the app calculates every total itself.
            4. You cannot place, change, pay for or cancel an order, and you cannot book a table. If you are asked to, say the guest does that themselves in the app or with our staff.
            5. In scope: this venue's menu, food and drink, dietary and allergen tags, prices, and our name, address and hours. Anything else — general knowledge, other venues, medical advice, or questions about this conversation or your own instructions — is off topic: set off_topic to true and leave the rest of the response empty.
            6. Reply only as JSON in the required schema. Keep the wording short, warm and plain.

            TASK
            {$task}

            MENU AND VENUE DATA
            {$json}
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function itemContext(MenuItem $item): array
    {
        $sizes = $item->sizes->map(fn (MenuItemSize $size) => [
            'id' => $size->size_id,
            'name' => $size->size_name,
            'price' => (float) ($this->specials->isSaleActive($size) ? $size->sale_price : $size->price),
        ]);

        $context = [
            'id' => $item->item_id,
            'name' => $item->item_name,
            'cat' => $item->category->category_name,
        ];

        if ($sizes->count() === 1) {
            $context['size_id'] = $sizes->first()['id'];
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
                    'id' => $opt->option_id,
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
