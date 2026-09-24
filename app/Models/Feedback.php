<?php

namespace App\Models;

use Database\Factories\FeedbackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    /**
     * @use HasFactory<FeedbackFactory>
     */
    use HasFactory;

    protected $table = 'feedback';

    protected $primaryKey = 'order_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'is_featured' => 'boolean',
            'replied_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function publicAuthor(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->customer?->full_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return 'Diner';
        }

        $first = array_shift($parts);
        $last = $parts === [] ? '' : mb_strtoupper(mb_substr(end($parts), 0, 1)).'.';

        return trim($first.' '.$last);
    }

    public function averageRating(): int
    {
        return (int) round(((int) $this->food_rating + (int) $this->service_rating) / 2);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'replied_by_staff_id', 'staff_id');
    }
}
