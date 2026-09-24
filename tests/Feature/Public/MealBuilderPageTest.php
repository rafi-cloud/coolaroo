<?php

namespace Tests\Feature\Public;

use App\Models\DietaryTag;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Services\AiMenuService;
use App\Services\SettingService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealBuilderPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_it_shows_the_brief_form_with_the_live_dietary_tags(): void
    {
        $offered = DietaryTag::factory()->create(['tag_name' => 'Vegetarian', 'is_active' => true]);
        $retired = DietaryTag::factory()->create(['tag_name' => 'Retired tag', 'is_active' => false]);

        MenuItem::factory()->create()->dietaryTags()->attach([
            $offered->dietary_tag_id,
            $retired->dietary_tag_id,
        ]);

        $this->get(route('meal-builder'))
            ->assertOk()
            ->assertSee('meal-builder-submit', false)
            ->assertSee('meal-builder-diet-'.$offered->dietary_tag_id, false)
            ->assertDontSee('meal-builder-diet-'.$retired->dietary_tag_id, false)
            ->assertSee(AiMenuService::ALLERGEN_DISCLAIMER);
    }

    /**
     * A tag no available dish carries can only produce an empty suggestion
     * list, so the brief form does not offer it.
     */
    public function test_it_hides_a_dietary_tag_no_available_dish_carries(): void
    {
        $unused = DietaryTag::factory()->create(['tag_name' => 'Nut-Free', 'is_active' => true]);
        $unavailable = DietaryTag::factory()->create(['tag_name' => 'Halal', 'is_active' => true]);

        MenuItem::factory()->create(['is_available' => false])
            ->dietaryTags()->attach($unavailable->dietary_tag_id);

        $this->get(route('meal-builder'))
            ->assertOk()
            ->assertDontSee('meal-builder-diet-'.$unused->dietary_tag_id, false)
            ->assertDontSee('meal-builder-diet-'.$unavailable->dietary_tag_id, false);
    }

    /** BR49, FR98. */
    public function test_it_is_not_found_when_the_assistant_is_switched_off(): void
    {
        Setting::where('setting_key', 'ai_enabled')->update(['setting_value' => '0']);
        app(SettingService::class)->clearCache();

        $this->get(route('meal-builder'))->assertNotFound();
    }
}
