<?php

namespace Tests\Feature\Public;

use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Services\AiMenuService;
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
        $this->seed(\Database\Seeders\SettingSeeder::class);
    }

    /** @param array<string, mixed> $payload */
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

    /** BR47: an id the live menu does not have never reaches the guest. */
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

    /** BR49: ai_enabled = 0 hides the assistant, endpoint included. */
    public function test_it_is_not_found_when_the_assistant_is_switched_off(): void
    {
        Setting::where('setting_key', 'ai_enabled')->update(['setting_value' => '0']);
        app(\App\Services\SettingService::class)->clearCache();
        Http::fake();

        $this->postJson(route('ai.chat'), ['message' => 'Hi'])->assertNotFound();

        Http::assertNothingSent();
    }

    /** BR49: a provider limit surfaces as 'assistant busy', not a stack trace. */
    public function test_a_provider_rate_limit_returns_the_busy_response(): void
    {
        Http::fake(['*' => Http::response([], 429)]);

        $this->postJson(route('ai.chat'), ['message' => 'Hi'])->assertStatus(503);

        $this->assertSame(0, AuditLog::where('action_type', 'ai_request')->count());
    }
}
