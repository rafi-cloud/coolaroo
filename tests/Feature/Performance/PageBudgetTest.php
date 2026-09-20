<?php

namespace Tests\Feature\Performance;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\Staff;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * T219, NFR08. A page meets its budget when its query count is flat in the
 * number of rows it renders. Asserting "same count at 3 rows and at 24" catches
 * an N+1 without hard-coding a number that every later change has to chase.
 */
class PageBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    /** @return int the number of queries the callback ran */
    private function countQueries(callable $callback): int
    {
        $queries = 0;

        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $callback();

        return $queries;
    }

    private function seedMenu(int $itemsPerCategory): void
    {
        foreach (MenuCategory::factory()->count(3)->create(['is_active' => true]) as $category) {
            MenuItem::factory()
                ->count($itemsPerCategory)
                ->create(['category_id' => $category->category_id, 'is_active' => true])
                ->each(fn (MenuItem $item) => $item->sizes()->create([
                    'size_name' => 'Regular',
                    'price' => 18.50,
                    'is_active' => true,
                ]));
        }
    }

    public function test_the_menu_page_query_count_does_not_grow_with_the_menu(): void
    {
        $this->seedMenu(1);

        // Warm the settings cache first: its one-off read would otherwise show
        // up only in the first measurement and look like a saving.
        $this->get('/menu')->assertOk();

        $small = $this->countQueries(fn () => $this->get('/menu')->assertOk());

        $this->seedMenu(7);
        $large = $this->countQueries(fn () => $this->get('/menu')->assertOk());

        $this->assertSame($small, $large,
            "NFR08: the menu page ran {$large} queries for 24 items against {$small} for 3 — that is an N+1.");
    }

    public function test_the_admin_orders_list_query_count_does_not_grow_with_the_orders(): void
    {
        $admin = Staff::factory()->create([
            'role_id' => Role::where('role_name', 'admin')->value('role_id') ?? Role::factory()->create(['role_name' => 'admin'])->role_id,
            'is_active' => true,
        ]);

        Order::factory()->count(3)->create();

        $this->actingAs($admin, 'staff')->get('/admin/orders')->assertOk();

        $small = $this->countQueries(fn () => $this->actingAs($admin, 'staff')->get('/admin/orders')->assertOk());

        Order::factory()->count(20)->create();
        $large = $this->countQueries(fn () => $this->actingAs($admin, 'staff')->get('/admin/orders')->assertOk());

        $this->assertSame($small, $large,
            "NFR08: the admin orders list ran {$large} queries for 23 orders against {$small} for 3 — that is an N+1.");
    }
}
