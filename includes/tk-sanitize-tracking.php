<?php
declare(strict_types=1);

/**
 * Strip marketing / analytics markup from HTML output (safety net after static cleanup).
 */
function tk_strip_tracking_html(string $html): string
{
    $html = preg_replace_callback(
        '~<script>\(self\.__next_s[\s\S]*?</script>\s*~i',
        static function (array $m): string {
            return preg_match(
                '~cookielaw|optimizely|onetrust|Optanon|googletagmanager|google-analytics|facebook|hotjar~i',
                $m[0]
            ) ? '' : $m[0];
        },
        $html
    ) ?? $html;

    $patterns = [
        '~<link\s[^>]*rel=["\']preload["\'][^>]*(?:cookielaw|optimizely|googletagmanager|facebook|hotjar)[^>]*>\s*~i',
        '~<script\b[^>]*(?:cookielaw|optimizely|googletagmanager|google-analytics|OtAutoBlock|otSDKStub)[^>]*>[\s\S]*?</script>\s*~i',
        '~<script\b[^>]*\bsrc=["\'][^"\']*(?:cookielaw|optimizely)[^"\']*["\'][^>]*>\s*</script>\s*~i',
        '~<style[^>]*>[\s\S]*?#onetrust-consent-sdk[\s\S]*?</style>\s*~i',
        '~<iframe\b[^>]*\boptimizely\b[^>]*>[\s\S]*?</iframe>\s*~i',
    ];

    foreach ($patterns as $pattern) {
        $html = preg_replace($pattern, '', $html) ?? $html;
    }

    return $html;
}

function tk_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header(
        'Content-Security-Policy: ' .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline'; " .
        "style-src 'self' 'unsafe-inline'; " .
        "img-src 'self' https://assets-dam.takeda.com data: blob:; " .
        "font-src 'self' data:; " .
        "connect-src 'self'; " .
        "frame-src 'none'; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self'"
    );
}
