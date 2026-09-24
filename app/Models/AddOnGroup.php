<?php

namespace App\Models;

use Database\Factories\AddOnGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddOnGroup extends Model
{
    /**
     * @use HasFactory<AddOnGroupFactory>
     */
    use HasFactory;

    protected $table = 'add_on_group';

    protected $primaryKey = 'group_id';

    public $timestamps = false;

    protected $guarded = ['group_id'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id', 'item_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(AddOnOption::class, 'group_id', 'group_id');
    }
}
