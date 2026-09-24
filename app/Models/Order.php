<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /**
     * @use HasFactory<OrderFactory>
     */
    use HasFactory;

    protected $table = 'orders';

    protected $primaryKey = 'order_id';

    public $timestamps = false;

    protected $guarded = ['order_id', 'status', 'payment_status'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'total_amount' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'has_stock_conflict' => 'boolean',
            'kitchen_eta_at' => 'datetime',
            'bar_eta_at' => 'datetime',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'started_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id', 'table_id');
    }

    public function table(): BelongsTo
    {
        return $this->restaurantTable();
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id', 'visit_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'taken_by_staff_id', 'staff_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id')->orderBy('line_no');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id', 'order_id')->orderBy('status_seq');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id', 'order_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'order_id', 'order_id');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class, 'order_id', 'order_id');
    }

    public function hasPendingCashRequest(): bool
    {
        return $this->payments->contains(
            fn (Payment $payment) => $payment->method === PaymentMethod::Cash
                && $payment->status === PaymentAttemptStatus::Pending
        );
    }
}
