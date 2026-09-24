<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const POLICY = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self' https://checkout.stripe.com",
        "frame-ancestors 'none'",
        "object-src 'none'",
        "img-src 'self' data:",
        "script-src 'self' 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "connect-src 'self' ws: wss:",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', $this->policy()));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * @return array<int, string>
     */
    private function policy(): array
    {
        $policy = self::POLICY;

        if (app()->isProduction() || ! file_exists(public_path('hot'))) {
            return $policy;
        }

        $origin = rtrim((string) file_get_contents(public_path('hot')), "\r\n /");

        foreach ($policy as $index => $directive) {
            if (str_starts_with($directive, 'script-src ') || str_starts_with($directive, 'connect-src ')) {
                $policy[$index] = $directive.' '.$origin;
            }
        }

        return $policy;
    }
}
