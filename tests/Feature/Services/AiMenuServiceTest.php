<?php

namespace Tests\Feature\Services;

use App\Exceptions\AiUnavailableException;
use App\Models\Allergen;
use App\Models\DietaryTag;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\AiMenuService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiMenuServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ai.api_key' => 'test-key']);
    }

    public function test_chat_returns_the_decoded_response_on_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'hello']]],
            ], 200),
        ]);

        $result = app(AiMenuService::class)->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('hello', $result['choices'][0]['message']['content']);

        Http::assertSent(function ($request) {
            return $request->url() === rtrim(config('services.ai.base_url'), '/').'/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === config('services.ai.model')
                && $request['messages'] === [['role' => 'user', 'content' => 'hi']];
        });
    }

    public function test_chat_throws_when_api_key_is_not_configured(): void
    {
        config(['services.ai.api_key' => null]);
        Http::fake();

        $this->expectException(AiUnavailableException::class);

        app(AiMenuService::class)->chat([['role' => 'user', 'content' => 'hi']]);

        Http::assertNothingSent();
    }

    public function test_chat_throws_busy_exception_on_provider_rate_limit(): void
    {
        Http::fake(['*' => Http::response([], 429)]);

        $this->expectException(AiUnavailableException::class);

        app(AiMenuService::class)->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_chat_throws_busy_exception_on_provider_server_error(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->expectException(AiUnavailableException::class);

        app(AiMenuService::class)->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_chat_throws_busy_exception_on_timeout(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->expectException(AiUnavailableException::class);

        app(AiMenuService::class)->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_build_context_includes_venue_and_available_items(): void
    {
        $this->seed(SettingSeeder::class);

        $category = MenuCategory::factory()->create(['category_name' => 'Mains']);

        $item = MenuItem::factory()->create([
            'category_id' => $category->category_id,
            'item_name' => 'Chicken Parma',
            'calories_kcal' => 650,
        ]);
        $item->sizes()->create(['size_name' => 'Regular', 'price' => 22.75]);
        $item->allergens()->attach(Allergen::factory()->create(['allergen_name' => 'Gluten']));
        $item->dietaryTags()->attach(DietaryTag::factory()->create(['tag_name' => 'Halal']));

        $soldOut = MenuItem::factory()->create(['category_id' => $category->category_id, 'is_available' => false]);
        $soldOut->sizes()->create(['size_name' => 'Regular', 'price' => 10]);

        $context = app(AiMenuService::class)->buildContext();

        $this->assertNotEmpty($context['venue']['name']);
        $this->assertCount(1, $context['items']);

        $parma = $context['items'][0];
        $this->assertSame('Chicken Parma', $parma['name']);
        $this->assertSame('Mains', $parma['cat']);
        $this->assertSame(22.75, $parma['price']);
        $this->assertArrayNotHasKey('sizes', $parma, 'single-size items collapse onto a flat price');
        $this->assertSame(['Gluten'], $parma['allergens']);
        $this->assertSame(['Halal'], $parma['diet']);
        $this->assertSame(650.0, $parma['nutrition']['kcal']);
    }

    public function test_build_context_uses_the_current_sale_price(): void
    {
        $this->seed(SettingSeeder::class);

        $item = MenuItem::factory()->create();
        $item->sizes()->create([
            'size_name' => 'Regular',
            'price' => 20,
            'sale_price' => 15,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
        ]);

        $context = app(AiMenuService::class)->buildContext();

        $this->assertSame(15.0, $context['items'][0]['price']);
    }

    public function test_build_context_exposes_size_and_option_ids(): void
    {
        $this->seed(SettingSeeder::class);

        $item = MenuItem::factory()->create();
        $regular = $item->sizes()->create(['size_name' => 'Regular', 'price' => 18]);
        $group = $item->addOnGroups()->create(['group_name' => 'Extras', 'min_select' => 0, 'max_select' => 2]);
        $option = $group->options()->create(['option_name' => 'Bacon', 'price_delta' => 3]);

        $twoSizes = MenuItem::factory()->create();
        $small = $twoSizes->sizes()->create(['size_name' => 'Small', 'price' => 8]);
        $large = $twoSizes->sizes()->create(['size_name' => 'Large', 'price' => 12]);

        $items = collect(app(AiMenuService::class)->buildContext()['items'])->keyBy('id');

        $this->assertSame($regular->size_id, $items[$item->item_id]['size_id']);
        $this->assertSame($option->option_id, $items[$item->item_id]['addons'][0]['opts'][0]['id']);
        $this->assertSame(
            [$small->size_id, $large->size_id],
            array_column($items[$twoSizes->item_id]['sizes'], 'id')
        );
    }

    public function test_chat_system_prompt_carries_the_guardrails_and_the_live_menu(): void
    {
        $this->seed(SettingSeeder::class);

        $item = MenuItem::factory()->create(['item_name' => 'Chicken Parma']);
        $item->sizes()->create(['size_name' => 'Regular', 'price' => 22.75]);

        $prompt = app(AiMenuService::class)->chatSystemPrompt();

        $this->assertStringContainsString('Chicken Parma', $prompt);
        $this->assertStringContainsString('"id":'.$item->item_id, $prompt);
        $this->assertStringContainsString('22.75', $prompt);
        $this->assertStringContainsString('allergen tags', $prompt);
        $this->assertStringContainsString('off_topic', $prompt);
        $this->assertStringContainsString('never state a total', $prompt);
        $this->assertStringContainsString('cannot place, change, pay for or cancel an order', $prompt);
    }

    public function test_response_formats_are_strict_json_schemas_without_price_fields(): void
    {
        $service = app(AiMenuService::class);

        $chat = $service->chatResponseFormat();
        $this->assertSame('json_schema', $chat['type']);
        $this->assertTrue($chat['json_schema']['strict']);
        $this->assertFalse($chat['json_schema']['schema']['additionalProperties']);
        $this->assertSame(
            ['answer', 'off_topic', 'item_ids'],
            $chat['json_schema']['schema']['required']
        );

        $builder = $service->mealBuilderResponseFormat();
        $line = $builder['json_schema']['schema']['properties']['suggestions']['items']['properties']['lines']['items'];

        $this->assertTrue($builder['json_schema']['strict']);
        $this->assertSame(['item_id', 'size_id', 'option_ids', 'qty'], $line['required']);
        $this->assertSame(array_keys($line['properties']), $line['required']);
        $this->assertStringNotContainsString('price', json_encode($builder));
        $this->assertStringNotContainsString('total', json_encode($builder));
    }

    public function test_off_topic_guard_replaces_the_answer_and_drops_referenced_items(): void
    {
        $service = app(AiMenuService::class);

        $chat = $service->applyOffTopicGuard([
            'answer' => 'The capital of France is Paris.',
            'off_topic' => true,
            'item_ids' => [7],
        ]);

        $this->assertSame(AiMenuService::OFF_TOPIC_REPLY, $chat['answer']);
        $this->assertSame([], $chat['item_ids']);

        $builder = $service->applyOffTopicGuard([
            'summary' => 'Here is some medical advice.',
            'off_topic' => true,
            'suggestions' => [['title' => 'x', 'rationale' => 'y', 'lines' => []]],
        ]);

        $this->assertSame(AiMenuService::OFF_TOPIC_REPLY, $builder['summary']);
        $this->assertSame([], $builder['suggestions']);

        $onTopic = ['answer' => 'We have four vegetarian mains.', 'off_topic' => false, 'item_ids' => [3, 9]];

        $this->assertSame($onTopic, $service->applyOffTopicGuard($onTopic));
    }

    public function test_build_context_stays_under_the_token_budget_for_a_realistic_menu(): void
    {
        $this->seed(SettingSeeder::class);

        $categories = MenuCategory::factory()->count(6)->create();

        foreach (range(1, 60) as $i) {
            $item = MenuItem::factory()->create([
                'category_id' => $categories->random()->category_id,
                'calories_kcal' => 400,
                'protein_g' => 20,
                'carbohydrates_g' => 30,
                'fat_g' => 15,
            ]);

            $item->sizes()->create(['size_name' => 'Regular', 'price' => 18.50]);

            if ($i % 3 === 0) {
                $item->sizes()->create(['size_name' => 'Large', 'price' => 24.50]);
            }

            if ($i % 2 === 0) {
                $group = $item->addOnGroups()->create(['group_name' => 'Extras', 'min_select' => 0, 'max_select' => 3]);
                $group->options()->createMany([
                    ['option_name' => 'Extra cheese', 'price_delta' => 2],
                    ['option_name' => 'Bacon', 'price_delta' => 3],
                    ['option_name' => 'Avocado', 'price_delta' => 2.5],
                ]);
            }

            $item->allergens()->attach(Allergen::factory()->count(2)->create());
            $item->dietaryTags()->attach(DietaryTag::factory()->create());
        }

        $context = app(AiMenuService::class)->buildContext();
        $estimatedTokens = strlen(json_encode($context)) / 4;

        $this->assertLessThan(8000, $estimatedTokens);
    }
}
