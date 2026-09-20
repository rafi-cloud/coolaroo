<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    protected $table = 'audit_log';

    protected $primaryKey = 'log_id';

    public $timestamps = false;

    protected $guarded = ['log_id'];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'logged_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function archive(): HasOne
    {
        return $this->hasOne(HistoricalDataManagement::class, 'log_id', 'log_id');
    }

    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new \RuntimeException('audit_log is append-only — no updates.');
        }

        return parent::save($options);
    }

    public function delete()
    {
        throw new \RuntimeException('audit_log is append-only — no deletes.');
    }
}
