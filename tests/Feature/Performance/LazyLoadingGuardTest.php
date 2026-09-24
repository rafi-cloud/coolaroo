<?php

namespace Tests\Feature\Performance;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LazyLoadingGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_lazy_loading_a_relation_on_a_result_set_throws_outside_production(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        Order::factory()->count(2)->create();

        $orders = Order::query()->get();

        $this->expectException(LazyLoadingViolationException::class);

        $orders->first()->restaurantTable->table_number;
    }
}
