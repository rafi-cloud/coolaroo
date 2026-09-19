<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * FR04, 3.3: role:admin,waitstaff etc. Admin passes every check (3.2).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $staff = $request->user('staff');

        abort_if($staff === null, 403);

        $roleName = $staff->role->role_name;

        abort_unless($roleName === 'admin' || in_array($roleName, $roles, true), 403);

        return $next($request);
    }
}
