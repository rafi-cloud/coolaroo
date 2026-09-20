<?php

namespace Tests\Feature\Services;

use App\Exceptions\AiUnavailableException;
use App\Models\Allergen;
use App\Models\DietaryTag;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\AiMenuService;
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
            return $request->url() === 'https://models.github.ai/inference/chat/completions'
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
        $this->seed(\Database\Seeders\SettingSeeder::class);

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
        $this->seed(\Database\Seeders\SettingSeeder::class);

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

    /**
     * 07.9's "under 8K tokens" budget, checked with the commonly-cited
     * ~4 characters/token approximation — no real tokenizer is installed,
     * and this is not an exact count.
     */
    public function test_build_context_stays_under_the_token_budget_for_a_realistic_menu(): void
    {
        $this->seed(\Database\Seeders\SettingSeeder::class);

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
