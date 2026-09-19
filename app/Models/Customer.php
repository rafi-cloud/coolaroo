<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory, MustVerifyEmail, Notifiable;

    const UPDATED_AT = null;

    protected $table = 'customer';

    protected $primaryKey = 'customer_id';

    protected $authPasswordName = 'password_hash';

    protected $guarded = ['customer_id'];

    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'customer_id', 'customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id', 'customer_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class, 'customer_id', 'customer_id');
    }
}
