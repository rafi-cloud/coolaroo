<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

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

    /**
     * The screen this role opens after login, and the screen an already
     * signed-in member is returned to if they ask for the login page again.
     * Falls back to the public site only when the configured route is
     * missing, which is a misconfiguration worth recording.
     */
    public function landingUrl(): string
    {
        if (! Route::has($this->landing_screen)) {
            Log::warning('Role landing_screen is not a registered route name; falling back to home.', [
                'role' => $this->role_name,
                'landing_screen' => $this->landing_screen,
            ]);

            return url('/');
        }

        return route($this->landing_screen);
    }
}
