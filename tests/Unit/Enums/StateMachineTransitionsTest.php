<?php

namespace Tests\Unit\Enums;

use App\Enums\Destination;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Enums\VisitCloseReason;
use App\Exceptions\InvalidTransitionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StateMachineTransitionsTest extends TestCase
{
    public static function machines(): array
    {
        return [
            '05.1 orders.status' => [OrderStatus::class, [
                'pending_payment' => ['paid', 'cancelled'],
                'paid' => ['preparing', 'cancelled'],
                'preparing' => ['ready'],
                'ready' => ['served'],
                'served' => [],
                'cancelled' => [],
            ]],
            '05.2 orders.payment_status' => [PaymentStatus::class, [
                'unpaid' => ['paid'],
                'paid' => ['partially_refunded', 'refunded'],
                'partially_refunded' => ['partially_refunded', 'refunded'],
                'refunded' => [],
            ]],
            '05.3 order_item.status' => [OrderItemStatus::class, [
                'pending' => ['preparing', 'cancelled'],
                'preparing' => ['ready', 'cancelled'],
                'ready' => ['served'],
                'served' => [],
                'cancelled' => [],
            ]],
            '05.4 payment.status' => [PaymentAttemptStatus::class, [
                'pending' => ['succeeded', 'failed', 'expired'],
                'succeeded' => [],
                'failed' => [],
                'expired' => [],
            ]],
            '05.5 refund.status' => [RefundStatus::class, [
                'requested' => ['processing', 'completed', 'rejected'],
                'processing' => ['completed', 'failed'],
                'failed' => ['processing'],
                'completed' => [],
                'rejected' => [],
            ]],
            '05.6 restaurant_table.status' => [TableStatus::class, [
                'available' => ['occupied', 'reserved'],
                'reserved' => ['occupied', 'available'],
                'occupied' => ['available'],
            ]],
            '05.8 reservation.status' => [ReservationStatus::class, [
                'requested' => ['confirmed', 'declined', 'expired', 'cancelled'],
                'confirmed' => ['requested', 'cancelled', 'seated', 'no_show'],
                'seated' => ['completed'],
                'declined' => [],
                'expired' => [],
                'cancelled' => [],
                'completed' => [],
                'no_show' => [],
            ]],
        ];
    }

    #[DataProvider('machines')]
    public function test_state_values_match_section_06_3(string $enum, array $expected): void
    {
        $this->assertEqualsCanonicalizing(array_keys($expected), array_column($enum::cases(), 'value'));
    }

    #[DataProvider('machines')]
    public function test_every_transition_pair_matches_section_05(string $enum, array $expected): void
    {
        foreach ($enum::cases() as $from) {
            foreach ($enum::cases() as $to) {
                $this->assertSame(
                    in_array($to->value, $expected[$from->value], true),
                    $from->canTransitionTo($to),
                    "{$from->value} -> {$to->value}",
                );
            }
        }
    }

    public static function plainEnums(): array
    {
        return [
            'visit.close_reason' => [VisitCloseReason::class, ['staff_clear', 'auto_clear', 'no_show', 'cancelled', 'unassigned', 'override']],
            'payment.method' => [PaymentMethod::class, ['stripe', 'cash']],
            'refund.method' => [RefundMethod::class, ['stripe', 'cash', 'manual']],
            'destination' => [Destination::class, ['kitchen', 'bar']],
        ];
    }

    #[DataProvider('plainEnums')]
    public function test_plain_enum_values_match_section_06_3(string $enum, array $values): void
    {
        $this->assertEqualsCanonicalizing($values, array_column($enum::cases(), 'value'));
    }

    public function test_illegal_transition_throws_409(): void
    {
        try {
            OrderStatus::Served->ensureCanTransitionTo(OrderStatus::Paid);
            $this->fail('Expected InvalidTransitionException');
        } catch (InvalidTransitionException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame('Cannot change OrderStatus from served to paid.', $e->getMessage());
        }
    }

    public function test_legal_transition_does_not_throw(): void
    {
        OrderStatus::PendingPayment->ensureCanTransitionTo(OrderStatus::Paid);

        $this->addToAssertionCount(1);
    }
}
