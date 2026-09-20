<?php

namespace App\Models;

use Database\Factories\AddOnOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddOnOption extends Model
{
    /** @use HasFactory<AddOnOptionFactory> */
    use HasFactory;

    protected $table = 'add_on_option';

    protected $primaryKey = 'option_id';

    public $timestamps = false;

    protected $guarded = ['option_id'];

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AddOnGroup::class, 'group_id', 'group_id');
    }
}
