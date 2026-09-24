<?php

namespace Tests\Feature\Public;

use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Services\AiMenuService;
use App\Services\SettingService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatTest extends TestCase
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
    private function fakeAnswer(array $payload): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($payload)]]],
                'usage' => ['prompt_tokens' => 1200, 'completion_tokens' => 80],
            ], 200),
        ]);
    }

    public function test_it_answers_a_menu_question_and_records_token_usage(): void
    {
        $item = MenuItem::factory()->create(['item_name' => 'Garden Salad']);
        $item->sizes()->create(['size_name' => 'Regular', 'price' => 16.50]);

        $this->fakeAnswer([
            'answer' => 'Our Garden Salad is vegetarian.',
            'off_topic' => false,
            'item_ids' => [$item->item_id],
        ]);

        $response = $this->postJson(route('ai.chat'), ['message' => 'Anything vegetarian?']);

        $response->assertOk()->assertJson([
            'answer' => 'Our Garden Salad is vegetarian.',
            'items' => [['item_id' => $item->item_id, 'name' => 'Garden Salad', 'price' => 16.50]],
            'disclaimer' => AiMenuService::ALLERGEN_DISCLAIMER,
        ]);

        $log = AuditLog::where('action_type', 'ai_request')->sole();
        $this->assertSame('chat', $log->details['feature']);
        $this->assertSame(1200, $log->details['tokens_in']);
        $this->assertSame(80, $log->details['tokens_out']);
        $this->assertNull($log->customer_id);
    }

    public function test_it_drops_item_ids_that_are_not_on_the_live_menu(): void
    {
        $item = MenuItem::factory()->create(['item_name' => 'Garden Salad']);
        $item->sizes()->create(['size_name' => 'Regular', 'price' => 16.50]);

        $this->fakeAnswer([
            'answer' => 'Two options for you.',
            'off_topic' => false,
            'item_ids' => [$item->item_id, 999999],
        ]);

        $this->postJson(route('ai.chat'), ['message' => 'What is vegetarian?'])
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.item_id', $item->item_id);
    }

    public function test_it_rejects_an_empty_message(): void
    {
        Http::fake();

        $this->postJson(route('ai.chat'), ['message' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        Http::assertNothingSent();
    }

    public function test_it_is_not_found_when_the_assistant_is_switched_off(): void
    {
        Setting::where('setting_key', 'ai_enabled')->update(['setting_value' => '0']);
        app(SettingService::class)->clearCache();
        Http::fake();

        $this->postJson(route('ai.chat'), ['message' => 'Hi'])->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_a_provider_rate_limit_returns_the_busy_response(): void
    {
        Http::fake(['*' => Http::response([], 429)]);

        $this->postJson(route('ai.chat'), ['message' => 'Hi'])->assertStatus(503);

        $this->assertSame(0, AuditLog::where('action_type', 'ai_request')->count());
    }

    public function test_a_repeated_question_is_served_from_cache_without_a_second_call(): void
    {
        MenuItem::factory()->create(['item_name' => 'Garden Salad']);

        $this->fakeAnswer(['answer' => 'Yes, several.', 'off_topic' => false, 'item_ids' => []]);

        $this->postJson(route('ai.chat'), ['message' => 'Do you have gluten-free dishes?'])->assertOk();
        Http::assertSentCount(1);

        $this->postJson(route('ai.chat'), ['message' => '  Do you have GLUTEN-FREE dishes?  '])
            ->assertOk()
            ->assertJson(['answer' => 'Yes, several.']);

        Http::assertSentCount(1);
    }

    public function test_a_cached_answer_records_zero_token_usage(): void
    {
        $this->fakeAnswer(['answer' => 'Yes, several.', 'off_topic' => false, 'item_ids' => []]);

        $this->postJson(route('ai.chat'), ['message' => 'Do you have gluten-free dishes?'])->assertOk();
        $this->postJson(route('ai.chat'), ['message' => 'Do you have gluten-free dishes?'])->assertOk();

        $logs = AuditLog::where('action_type', 'ai_request')->orderBy('log_id')->get();

        $this->assertSame(1200, $logs[0]->details['tokens_in']);
        $this->assertSame(0, $logs[1]->details['tokens_in']);
        $this->assertSame(0, $logs[1]->details['tokens_out']);
    }

    public function test_a_menu_change_invalidates_the_cached_answer(): void
    {
        $this->fakeAnswer(['answer' => 'Yes, several.', 'off_topic' => false, 'item_ids' => []]);

        $this->postJson(route('ai.chat'), ['message' => 'Do you have gluten-free dishes?'])->assertOk();
        Http::assertSentCount(1);

        MenuItem::factory()->create(['item_name' => 'New Dish']);

        $this->postJson(route('ai.chat'), ['message' => 'Do you have gluten-free dishes?'])->assertOk();
        Http::assertSentCount(2);
    }

    public function test_a_follow_up_with_history_is_never_served_from_cache(): void
    {
        $this->fakeAnswer(['answer' => 'Yes, several.', 'off_topic' => false, 'item_ids' => []]);

        $history = [
            ['role' => 'user', 'content' => 'Tell me about the salad'],
            ['role' => 'assistant', 'content' => 'It is vegetarian.'],
        ];

        $this->postJson(route('ai.chat'), ['message' => 'Is it vegan?', 'history' => $history])->assertOk();
        $this->postJson(route('ai.chat'), ['message' => 'Is it vegan?', 'history' => $history])->assertOk();

        Http::assertSentCount(2);
    }
}
