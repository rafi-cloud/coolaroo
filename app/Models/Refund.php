<?php

namespace App\Models;

use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected $table = 'refund';

    protected $primaryKey = 'refund_id';

    public $timestamps = false;

    protected $guarded = ['refund_id', 'status'];

    protected function casts(): array
    {
        return [
            'method' => RefundMethod::class,
            'status' => RefundStatus::class,
            'amount' => 'decimal:2',
            'return_to_stock' => 'boolean',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'order_item_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'payment_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'requested_by_staff_id', 'staff_id');
    }

    public function requestedByCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'requested_by_customer_id', 'customer_id');
    }

    /** BR27: exactly one of the two requesters is set, so screens can name whoever asked. */
    public function requesterName(): string
    {
        return $this->requestedBy?->full_name
            ?? ($this->requestedByCustomer !== null ? $this->requestedByCustomer->full_name.' (customer)' : '—');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'processed_by_staff_id', 'staff_id');
    }
}
