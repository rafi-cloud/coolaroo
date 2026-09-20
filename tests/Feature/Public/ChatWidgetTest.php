<?php

namespace Tests\Feature\Public;

use App\Models\Setting;
use App\Services\AiMenuService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SettingSeeder::class);
    }

    /** S17 sits on the homepage and the menu, for visitors as well as customers. */
    public function test_the_widget_renders_for_a_visitor_with_the_disclaimer_and_build_a_meal_link(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('chat-open', false)
            ->assertSee(route('meal-builder'), false)
            ->assertSee(AiMenuService::ALLERGEN_DISCLAIMER);

        $this->get(route('menu.index'))->assertOk()->assertSee('chat-panel', false);
    }

    /** BR49, FR98: ai_enabled = 0 hides the widget. */
    public function test_the_widget_is_hidden_when_the_assistant_is_switched_off(): void
    {
        Setting::where('setting_key', 'ai_enabled')->update(['setting_value' => '0']);
        app(SettingService::class)->clearCache();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('chat-open', false)
            ->assertDontSee('chat-panel', false);
    }
}
