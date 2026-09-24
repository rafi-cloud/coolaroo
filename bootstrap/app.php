<?php

use App\Exceptions\AiUnavailableException;
use App\Exceptions\InvalidTransitionException;
use App\Http\Middleware\EnsureQrOrderingEnabled;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStaffSessionIsActive;
use App\Http\Middleware\EnsureTableContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'staff.session' => EnsureStaffSessionIsActive::class,
            'table.context' => EnsureTableContext::class,
            'qr.ordering.enabled' => EnsureQrOrderingEnabled::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('staff*', 'admin*')
            ? route('staff.login')
            : route('customer.login'));

        $middleware->redirectUsersTo(fn (Request $request) => $request->is('staff*', 'admin*')
            ? (Auth::guard('staff')->user()?->role?->landingUrl() ?? route('home'))
            : route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (InvalidTransitionException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
            }

            return back()->with('error', $e->getMessage());
        });

        $exceptions->render(function (AiUnavailableException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
            }

            return back()->with('error', $e->getMessage());
        });
    })->create();
