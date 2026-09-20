<?php

namespace Tests\Feature\Public;

use App\Models\DietaryTag;
use App\Models\Setting;
use App\Services\AiMenuService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealBuilderPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SettingSeeder::class);
    }

    public function test_it_shows_the_brief_form_with_the_live_dietary_tags(): void
    {
        DietaryTag::factory()->create(['tag_name' => 'Vegetarian', 'is_active' => true]);
        DietaryTag::factory()->create(['tag_name' => 'Retired tag', 'is_active' => false]);

        $this->get(route('meal-builder'))
            ->assertOk()
            ->assertSee('meal-builder-submit', false)
            ->assertSee('Vegetarian')
            ->assertDontSee('Retired tag')
            ->assertSee(AiMenuService::ALLERGEN_DISCLAIMER);
    }

    /** BR49, FR98. */
    public function test_it_is_not_found_when_the_assistant_is_switched_off(): void
    {
        Setting::where('setting_key', 'ai_enabled')->update(['setting_value' => '0']);
        app(SettingService::class)->clearCache();

        $this->get(route('meal-builder'))->assertNotFound();
    }
}
