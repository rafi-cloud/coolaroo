<?php

namespace Tests\Feature\Public;

use App\Models\Setting;
use App\Services\AiMenuService;
use App\Services\SettingService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_the_widget_renders_for_a_visitor_with_the_disclaimer_and_build_a_meal_link(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('chat-open', false)
            ->assertSee(route('meal-builder'), false)
            ->assertSee(AiMenuService::ALLERGEN_DISCLAIMER);

        $this->get(route('menu.index'))->assertOk()->assertSee('chat-panel', false);
    }

    public function test_the_widget_is_hidden_when_the_assistant_is_switched_off(): void
    {
        Setting::where('setting_key', 'ai_enabled')->update(['setting_value' => '0']);
        app(SettingService::class)->clearCache();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('chat-open', false)
            ->assertDontSee('chat-panel', false);
    }

    public function test_the_widget_renders_globally_on_public_and_customer_layouts(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('chat-open', false);
    }
}
