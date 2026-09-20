<?php

namespace Tests\Feature\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * T219, NFR08. Every index SDD 6.4 lists under "Indexes and constraints",
 * checked against the schema the migrations actually build. A missing index is
 * invisible until the table is large, which is exactly when it is expensive to
 * discover.
 */
class SchemaIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Table => the column lists SDD 6.4 requires an index on, in order.
     * Unique constraints count: they index too.
     *
     * @return array<string, array<int, array<int, string>>>
     */
    private function expectedIndexes(): array
    {
        return [
            'role' => [['role_name']],
            'staff' => [['email'], ['role_id', 'is_active']],
            'customer' => [['email'], ['phone']],
            'restaurant_table' => [['table_number'], ['qr_token'], ['status', 'is_active']],
            'slot_capacity' => [['slot_time']],
            'menu_category' => [['parent_category_id', 'display_order']],
            'menu_item' => [['category_id', 'is_active', 'is_available'], ['is_featured', 'is_active']],
            'menu_item_size' => [['item_id', 'size_name'], ['item_id', 'is_active']],
            'add_on_group' => [['item_id', 'display_order']],
            'add_on_option' => [['group_id', 'is_active']],
            'allergen' => [['allergen_name']],
            'menu_item_allergen' => [['allergen_id']],
            'dietary_tag' => [['tag_name']],
            'menu_item_dietary_tag' => [['dietary_tag_id']],
            'visit' => [['table_id', 'closed_at'], ['reservation_id']],
            'reservation' => [['reference_code'], ['booking_date', 'status'], ['customer_id', 'status'], ['slot_id', 'booking_date']],
            'orders' => [['order_number'], ['idempotency_key'], ['status', 'placed_at'], ['table_id', 'status'], ['customer_id', 'placed_at'], ['paid_at']],
            'order_item' => [['order_id', 'line_no'], ['destination', 'status'], ['item_id']],
            'payment' => [['stripe_session_id'], ['succeeded_order_id'], ['order_id', 'status'], ['method', 'paid_at']],
            'refund' => [['status', 'requested_at'], ['order_id'], ['provider_refund_id']],
            'feedback' => [['is_hidden', 'is_featured'], ['submitted_at']],
            'audit_log' => [['entity_name', 'entity_id'], ['action_type', 'logged_at'], ['staff_id', 'logged_at']],
            'historical_data_management' => [['log_id'], ['entity_name', 'record_id']],
        ];
    }

    public function test_every_index_the_data_design_lists_exists_in_the_schema(): void
    {
        $missing = [];

        foreach ($this->expectedIndexes() as $table => $indexes) {
            $actual = array_map(
                fn (array $index) => array_map('strtolower', $index['columns']),
                Schema::getIndexes($table),
            );

            // MySQL indexes every foreign key column whether or not the
            // migration says `index()`, and the app runs on MySQL. SQLite,
            // which these tests use, does not — so a single-column index the
            // data design asks for counts as present when a foreign key on
            // that column provides it at runtime.
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $actual[] = array_map('strtolower', $foreignKey['columns']);
            }

            foreach ($indexes as $columns) {
                $found = false;

                foreach ($actual as $actualColumns) {
                    if (array_slice($actualColumns, 0, count($columns)) === $columns) {
                        $found = true;
                        break;
                    }
                }

                if (! $found) {
                    $missing[] = $table.' ('.implode(', ', $columns).')';
                }
            }
        }

        $this->assertSame([], $missing,
            'SDD 6.4 lists indexes the schema does not have: '.implode('; ', $missing));
    }
}
