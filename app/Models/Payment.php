<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'payment';

    protected $primaryKey = 'payment_id';

    protected $guarded = ['payment_id', 'status', 'succeeded_order_id'];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentAttemptStatus::class,
            'amount' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'change_given' => 'decimal:2',
            'rounding_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'recorded_by_staff_id', 'staff_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'payment_id', 'payment_id');
    }
}
