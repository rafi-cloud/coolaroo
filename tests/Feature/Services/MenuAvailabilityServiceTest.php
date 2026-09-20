<?php

namespace Tests\Feature\Services;

use App\Enums\Destination;
use App\Events\MenuAvailabilityChanged;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Staff;
use App\Services\MenuAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MenuAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithRole(string $role): Staff
    {
        $roleModel = Role::firstWhere('role_name', $role) ?? Role::factory()->{$role}()->create();

        return Staff::factory()->create(['role_id' => $roleModel->role_id]);
    }

    public function test_toggling_a_menu_item_flips_it_and_broadcasts(): void
    {
        Event::fake([MenuAvailabilityChanged::class]);
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Kitchen]);

        app(MenuAvailabilityService::class)->toggleMenuItem($item, $this->staffWithRole('kitchen'));

        $this->assertFalse($item->fresh()->is_available);
        Event::assertDispatched(MenuAvailabilityChanged::class);
    }

    public function test_toggling_an_add_on_option_flips_it_and_broadcasts(): void
    {
        Event::fake([MenuAvailabilityChanged::class]);
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Bar]);
        $option = $item->addOnGroups()->create(['group_name' => 'Add-ons', 'is_required' => false])
            ->options()->create(['option_name' => 'Extra shot', 'price_delta' => 1])
            ->fresh();

        app(MenuAvailabilityService::class)->toggleAddOnOption($option, $this->staffWithRole('bar'));

        $this->assertFalse($option->fresh()->is_available);
        Event::assertDispatched(MenuAvailabilityChanged::class);
    }
}
