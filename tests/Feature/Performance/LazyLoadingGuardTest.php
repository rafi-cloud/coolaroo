<?php

namespace Tests\Feature\Performance;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T219, NFR08. Proof that the strict-loading guard in AppServiceProvider is on
 * outside production — without this, a passing suite says nothing about N+1,
 * because a silently disabled guard also passes.
 */
class LazyLoadingGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_lazy_loading_a_relation_on_a_result_set_throws_outside_production(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        Order::factory()->count(2)->create();

        // The guard only arms models hydrated from a multi-row result, because
        // one model loading one relation is one extra query, not an N+1.
        $orders = Order::query()->get();

        $this->expectException(LazyLoadingViolationException::class);

        $orders->first()->restaurantTable->table_number;
    }
}
