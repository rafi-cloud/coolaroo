<?php

namespace Tests\Feature\Services;

use App\Models\MenuItem;
use App\Services\SpecialsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sale_with_no_start_or_end_date_is_active(): void
    {
        $item = MenuItem::factory()->create();
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 20, 'sale_price' => 15, 'is_active' => true]);

        $this->assertTrue(app(SpecialsService::class)->isSaleActive($size));
    }

    public function test_a_sale_that_has_already_ended_is_not_active(): void
    {
        $item = MenuItem::factory()->create();
        $size = $item->sizes()->create([
            'size_name' => 'Regular', 'price' => 20, 'sale_price' => 15, 'is_active' => true,
            'sale_ends_at' => now()->subDay(),
        ]);

        $this->assertFalse(app(SpecialsService::class)->isSaleActive($size));
    }

    public function test_a_sale_that_has_not_started_yet_is_not_active(): void
    {
        $item = MenuItem::factory()->create();
        $size = $item->sizes()->create([
            'size_name' => 'Regular', 'price' => 20, 'sale_price' => 15, 'is_active' => true,
            'sale_starts_at' => now()->addDay(),
        ]);

        $this->assertFalse(app(SpecialsService::class)->isSaleActive($size));
    }

    public function test_items_on_special_only_returns_items_with_an_active_sale(): void
    {
        $onSpecial = MenuItem::factory()->create();
        $onSpecial->sizes()->create(['size_name' => 'Regular', 'price' => 20, 'sale_price' => 15]);

        $notOnSpecial = MenuItem::factory()->create();
        $notOnSpecial->sizes()->create(['size_name' => 'Regular', 'price' => 20]);

        $results = app(SpecialsService::class)->itemsOnSpecial();

        $this->assertTrue($results->contains('item_id', $onSpecial->item_id));
        $this->assertFalse($results->contains('item_id', $notOnSpecial->item_id));
    }

    public function test_top_special_picks_the_largest_percentage_discount_not_the_largest_dollar_amount(): void
    {
        $smallPercentBigDollar = MenuItem::factory()->create();
        $smallPercentBigDollar->sizes()->create(['size_name' => 'Regular', 'price' => 40, 'sale_price' => 35]); // 12.5%

        $bigPercentSmallDollar = MenuItem::factory()->create();
        $bigPercentSmallDollar->sizes()->create(['size_name' => 'Regular', 'price' => 10, 'sale_price' => 8]); // 20%

        $top = app(SpecialsService::class)->topSpecial();

        $this->assertSame($bigPercentSmallDollar->item_id, $top['size']->item_id);
        $this->assertSame(20.0, $top['discount_percent']);
    }
}
