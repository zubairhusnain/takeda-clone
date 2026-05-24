<?php
declare(strict_types=1);

@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '120');

require_once __DIR__ . '/includes/tk-php-polyfill.php';
require_once __DIR__ . '/base-url.php';

tk_send_security_headers();

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);
if (!is_string($path) || $path === '') {
    $path = '/';
}

$path = tk_normalize_request_path($path);
$rootReal = realpath(__DIR__);
$pagesReal = realpath(__DIR__ . '/pages');

if (str_starts_with($path, '/assets/')) {
    $localFs = __DIR__ . $path;
    if (is_file($localFs)) {
        $contentType = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $mt = mime_content_type($localFs);
            if (is_string($mt) && $mt !== '') {
                $contentType = $mt;
            }
        }
        if (str_ends_with($localFs, '.css')) {
            $contentType = 'text/css; charset=utf-8';
        } elseif (str_ends_with($localFs, '.js')) {
            $contentType = 'application/javascript; charset=utf-8';
        } elseif (str_ends_with($localFs, '.svg')) {
            $contentType = 'image/svg+xml';
        }
        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=86400');
        readfile($localFs);
        exit;
    }
    http_response_code(404);
    exit;
}

if (is_string($pagesReal) && str_starts_with($path, '/pages/') && str_ends_with($path, '.php')) {
    $candidate = realpath(__DIR__ . $path);
    if (
        is_string($candidate) &&
        is_string($pagesReal) &&
        str_starts_with($candidate, $pagesReal . DIRECTORY_SEPARATOR) &&
        is_file($candidate)
    ) {
        tk_start_output_rewrite();
        include $candidate;
        exit;
    }
}

$route = trim($path, '/');

if ($route === '') {
    $target = __DIR__ . '/index.php';
} else {
    $target = __DIR__ . '/pages/' . $route . '/index.php';
}

if (!is_file($target)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Not found</title></head><body><h1>404</h1><p><a href="' .
        htmlspecialchars(TK_BASE_URL . '/', ENT_QUOTES, 'UTF-8') .
        '">Home</a></p></body></html>';
    exit;
}

tk_start_output_rewrite();
include $target;
