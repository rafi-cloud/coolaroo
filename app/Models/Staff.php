<?php

namespace App\Models;

use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Staff extends Authenticatable
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory, Notifiable;

    const UPDATED_AT = null;

    protected $table = 'staff';

    protected $primaryKey = 'staff_id';

    protected $authPasswordName = 'password_hash';

    protected $guarded = ['staff_id'];

    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    /**
     * Admin clears every role check, so admin sees the shared
     * staff screens with admin navigation around them.
     */
    public function isAdmin(): bool
    {
        return $this->role?->role_name === 'admin';
    }
}
