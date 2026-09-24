<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use ReflectionProperty;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function test_every_response_carries_the_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'self'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_the_policy_admits_the_vite_dev_server_only_while_it_is_running(): void
    {
        $hot = public_path('hot');
        $this->assertFileDoesNotExist($hot, 'Stop `npm run dev` before running the suite.');

        $this->assertStringNotContainsString('5173', $this->get('/')->headers->get('Content-Security-Policy'));

        file_put_contents($hot, 'http://localhost:5173');

        try {
            $policy = $this->get('/')->headers->get('Content-Security-Policy');

            $this->assertStringContainsString("script-src 'self' 'unsafe-inline' http://localhost:5173", $policy);
            $this->assertStringContainsString('connect-src', $policy);
        } finally {
            @unlink($hot);
        }
    }

    public function test_csrf_protection_has_no_exemptions(): void
    {
        $except = new ReflectionProperty(PreventRequestForgery::class, 'except');

        $this->assertSame([], $except->getValue(app(PreventRequestForgery::class)),
            'NFR03 allows no CSRF exemptions — there are no webhook routes to exempt.');
    }

    public function test_every_state_changing_route_runs_through_the_web_middleware_group(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (empty(array_intersect($route->methods(), self::WRITE_METHODS))) {
                continue;
            }

            $this->assertContains('web', $route->gatherMiddleware(),
                "NFR03: {$route->uri()} changes state outside the web group, so no CSRF token is checked.");
        }
    }

    public function test_every_admin_route_is_gated_by_the_admin_role(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'admin')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth:staff', $middleware, "NFR06: {$route->uri()} is not behind staff auth.");
            $this->assertContains('role:admin', $middleware, "NFR06: {$route->uri()} is not gated by the admin role.");
        }
    }

    public function test_every_staff_route_is_behind_staff_auth_and_the_session_timeout(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'staff/')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            if (in_array('guest:staff', $middleware, true)) {
                continue;
            }

            $this->assertContains('auth:staff', $middleware, "NFR06: {$route->uri()} is not behind staff auth.");
            $this->assertContains('staff.session', $middleware, "BR51: {$route->uri()} skips the session timeout check.");
        }
    }

    public function test_the_qr_scan_route_is_signed(): void
    {
        $this->assertContains('signed', Route::getRoutes()->getByName('table.scan')->gatherMiddleware(),
            'NFR04: the table QR URL must be a signed route.');
    }
}
