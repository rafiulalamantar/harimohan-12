<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/** Adds browser hardening headers (clickjacking, MIME sniffing, referrer leaks, HTTPS pinning, CSP). */
class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // The Vite dev server injects inline scripts for hot reload, so no policy while `npm run dev` runs.
        if (! Vite::isRunningHot()) {
            // Locally, only report violations in the console so dev tooling keeps working.
            $header = app()->isLocal() ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $response->headers->set($header, $this->contentSecurityPolicy($request));
        }

        return $response;
    }

    /**
     * Scripts only from this site; Google Fonts for type; bKash as the only outside form target
     * (the Pay form redirects to bKash's checkout, and browsers apply form-action to that redirect).
     * Inline styles stay allowed for Laravel's error pages and small computed widths.
     */
    private function contentSecurityPolicy(Request $request): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data:",
            "connect-src 'self'",
            "form-action 'self' https://*.bka.sh https://*.bkash.com",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
