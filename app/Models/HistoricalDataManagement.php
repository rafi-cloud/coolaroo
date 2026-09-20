<?php

namespace App\Models;

use Database\Factories\HistoricalDataManagementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricalDataManagement extends Model
{
    /** @use HasFactory<HistoricalDataManagementFactory> */
    use HasFactory;

    protected $table = 'historical_data_management';

    protected $primaryKey = 'history_id';

    public $timestamps = false;

    protected $guarded = ['history_id'];

    protected function casts(): array
    {
        return [
            'record_data' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'log_id', 'log_id');
    }
}
