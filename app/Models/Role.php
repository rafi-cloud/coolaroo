<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $table = 'role';

    protected $primaryKey = 'role_id';

    public $timestamps = false;

    protected $guarded = ['role_id'];

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class, 'role_id', 'role_id');
    }
}
