<?php
declare(strict_types=1);

/**
 * Strip marketing / analytics markup from HTML output (safety net after static cleanup).
 *
 * IMPORTANT: Never add a pattern like '~\s*~i' — it removes ALL whitespace and destroys layout.
 */
function tk_html_whitespace_intact(string $html): bool
{
    if (str_contains($html, '<!DOCTYPEhtml') || str_contains($html, '<htmllang=')) {
        return false;
    }

    return str_contains($html, '<!DOCTYPE html>')
        || (str_contains($html, '<html') && preg_match('~<\s*head\b~i', $html) === 1);
}

function tk_strip_tracking_html(string $html): string
{
    $original = $html;

    $html = preg_replace_callback(
        '~<script>\(self\.__next_s[\s\S]*?</script>\s*~i',
        static function (array $m): string {
            return preg_match(
                '~cookielaw|optimizely|onetrust|Optanon|googletagmanager|google-analytics|facebook|hotjar|cloudflareinsights|cdn-cgi~i',
                $m[0]
            ) ? '' : $m[0];
        },
        $html
    ) ?? $html;

    $patterns = [
        '~<link\s[^>]*rel=["\']preload["\'][^>]*(?:cookielaw|optimizely|googletagmanager|facebook|hotjar|cloudflareinsights)[^>]*>\s*~i',
        '~<script\b[^>]*(?:cookielaw|optimizely|googletagmanager|google-analytics|OtAutoBlock|otSDKStub|cloudflareinsights|cdn-cgi)[^>]*>[\s\S]*?</script>\s*~i',
        '~<script\b[^>]*\bsrc=["\'][^"\']*(?:cookielaw|optimizely|cloudflareinsights)[^"\']*["\'][^>]*>\s*</script>\s*~i',
        '~<style[^>]*>[\s\S]*?#onetrust-consent-sdk[\s\S]*?</style>\s*~i',
        '~<iframe\b[^>]*\boptimizely\b[^>]*>[\s\S]*?</iframe>\s*~i',
    ];

    foreach ($patterns as $pattern) {
        if ($pattern === '~\s*~i' || $pattern === '~\\s*~i') {
            continue;
        }
        $html = preg_replace($pattern, '', $html) ?? $html;
        if (!tk_html_whitespace_intact($html)) {
            return $original;
        }
    }

    if (!tk_html_whitespace_intact($html)) {
        return $original;
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
