<?php

namespace Tests\Feature\Public;

use App\Models\AuditLog;
use App\Models\MenuItem;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiMealBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ai.api_key' => 'test-key']);
        $this->seed(SettingSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fakeSuggestions(array $payload): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($payload)]]],
                'usage' => ['prompt_tokens' => 1500, 'completion_tokens' => 220],
            ], 200),
        ]);
    }

    public function test_it_prices_a_suggestion_server_side_and_returns_a_cart_ready_payload(): void
    {
        $item = MenuItem::factory()->create(['item_name' => 'Chicken Parma']);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 24.00]);
        $group = $item->addOnGroups()->create(['group_name' => 'Extras', 'min_select' => 0, 'max_select' => 2]);
        $bacon = $group->options()->create(['option_name' => 'Bacon', 'price_delta' => 2.75]);

        $this->fakeSuggestions([
            'summary' => 'Two mains under $60.',
            'off_topic' => false,
            'suggestions' => [[
                'title' => 'Pub classics',
                'rationale' => 'Shareable and filling.',
                'lines' => [[
                    'item_id' => $item->item_id,
                    'size_id' => $size->size_id,
                    'option_ids' => [$bacon->option_id],
                    'qty' => 2,
                ]],
            ]],
        ]);

        $response = $this->postJson(route('ai.meal-builder'), ['budget' => 60, 'party_size' => 2]);

        $response->assertOk()
            ->assertJsonPath('summary', 'Two mains under $60.')
            ->assertJsonPath('suggestions.0.title', 'Pub classics')
            ->assertJsonPath('suggestions.0.total', 53.5)
            ->assertJsonPath('suggestions.0.lines.0.line_total', 53.5)
            ->assertJsonPath('suggestions.0.lines.0.item_id', $item->item_id)
            ->assertJsonPath('suggestions.0.lines.0.size_id', $size->size_id)
            ->assertJsonPath('suggestions.0.lines.0.add_on_option_ids', [$bacon->option_id])
            ->assertJsonPath('suggestions.0.lines.0.quantity', 2)
            ->assertJsonPath('can_add_to_cart', false);

        $log = AuditLog::where('action_type', 'ai_request')->sole();
        $this->assertSame('meal_builder', $log->details['feature']);
        $this->assertSame(1500, $log->details['tokens_in']);
    }

    public function test_it_drops_a_suggestion_that_busts_the_budget_once_repriced(): void
    {
        $item = MenuItem::factory()->create();
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 40.00]);

        $this->fakeSuggestions([
            'summary' => 'Here you go.',
            'off_topic' => false,
            'suggestions' => [[
                'title' => 'Too pricey',
                'rationale' => 'The model thought this fit.',
                'lines' => [['item_id' => $item->item_id, 'size_id' => $size->size_id, 'option_ids' => [], 'qty' => 3]],
            ]],
        ]);

        $this->postJson(route('ai.meal-builder'), ['budget' => 60, 'party_size' => 2])
            ->assertOk()
            ->assertJsonCount(0, 'suggestions');
    }

    public function test_it_drops_a_suggestion_whose_line_uses_a_size_from_another_item(): void
    {
        $item = MenuItem::factory()->create();
        $item->sizes()->create(['size_name' => 'Regular', 'price' => 20.00]);

        $other = MenuItem::factory()->create();
        $otherSize = $other->sizes()->create(['size_name' => 'Regular', 'price' => 12.00]);

        $this->fakeSuggestions([
            'summary' => 'Here you go.',
            'off_topic' => false,
            'suggestions' => [[
                'title' => 'Mismatched',
                'rationale' => 'Wrong size id.',
                'lines' => [['item_id' => $item->item_id, 'size_id' => $otherSize->size_id, 'option_ids' => [], 'qty' => 1]],
            ]],
        ]);

        $this->postJson(route('ai.meal-builder'), ['budget' => 60, 'party_size' => 2])
            ->assertOk()
            ->assertJsonCount(0, 'suggestions');
    }

    public function test_add_to_cart_is_offered_only_in_table_context(): void
    {
        $item = MenuItem::factory()->create();
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 20.00]);

        $this->fakeSuggestions([
            'summary' => 'Here you go.',
            'off_topic' => false,
            'suggestions' => [[
                'title' => 'Simple',
                'rationale' => 'One main.',
                'lines' => [['item_id' => $item->item_id, 'size_id' => $size->size_id, 'option_ids' => [], 'qty' => 1]],
            ]],
        ]);

        $this->withSession(['table_id' => 7])
            ->postJson(route('ai.meal-builder'), ['budget' => 60, 'party_size' => 2])
            ->assertOk()
            ->assertJsonPath('can_add_to_cart', true);
    }

    public function test_it_rejects_a_party_size_of_zero(): void
    {
        Http::fake();

        $this->postJson(route('ai.meal-builder'), ['budget' => 60, 'party_size' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('party_size');

        Http::assertNothingSent();
    }
}
