<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/tk-php-polyfill.php';

function tk_install_base_path(): string
{
    static $path = null;
    if ($path !== null) {
        return $path;
    }

    $override = getenv('TK_BASE_PATH');
    if (is_string($override)) {
        $path = ($override === '' || $override === '/') ? '' : (str_starts_with($override, '/') ? $override : '/' . $override);
        return $path;
    }

    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $here = realpath(__DIR__);
    if ($docRoot !== '' && $here !== false) {
        $root = realpath($docRoot);
        if ($root !== false) {
            if ($root === $here) {
                $path = '';
                return $path;
            }
            $rootPrefix = $root . DIRECTORY_SEPARATOR;
            if (str_starts_with($here, $rootPrefix)) {
                $rel = substr($here, strlen($root));
                $path = str_replace('\\', '/', $rel);
                if ($path !== '' && !str_starts_with($path, '/')) {
                    $path = '/' . $path;
                }
                return $path;
            }
        }
    }

    $path = '/' . basename(__DIR__);
    return $path;
}

function tk_normalize_request_path(string $path): string
{
    $base = tk_install_base_path();
    if ($base !== '') {
        if ($path === $base) {
            $path = '/';
        } elseif (str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }
    }

    if ($path === '') {
        $path = '/';
    }

    return $path;
}

if (!defined('TK_BASE_URL')) {
    if (isset($_SERVER['HTTP_HOST'])) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $isHttps ? 'https' : 'http';
        $host = (string)$_SERVER['HTTP_HOST'];
        $base = $scheme . '://' . $host . tk_install_base_path();
    } else {
        $base = 'http://localhost' . tk_install_base_path();
    }
    define('TK_BASE_URL', rtrim($base, '/'));
}

function tk_route_from_fs_path(string $fsPath): string
{
    $rel = str_replace('\\', '/', $fsPath);
    if (str_ends_with($rel, '/index.php')) {
        $rel = substr($rel, 0, -strlen('/index.php'));
    } elseif (str_ends_with($rel, '/index.html')) {
        $rel = substr($rel, 0, -strlen('/index.html'));
    }
    return trim($rel, '/');
}

function tk_is_asset_path(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if (preg_match('~(^|/)(assets/|_next/)~i', $path)) {
        return true;
    }

    return (bool)preg_match('~\.(css|js|mjs|map|svg|png|jpe?g|gif|webp|woff2?|ttf|eot|ico|avif)(\?|#|$)~i', $path);
}

function tk_valid_page_routes(): array
{
    static $validRoutes = null;
    if ($validRoutes !== null) {
        return $validRoutes;
    }

    $validRoutes = ['' => true];
    $pagesDir = __DIR__ . '/pages';
    if (is_dir($pagesDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pagesDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'index.php') {
                $rel = str_replace('\\', '/', substr($file->getPath(), strlen($pagesDir) + 1));
                $validRoutes[$rel] = true;
            }
        }
    }

    return $validRoutes;
}

function tk_resolve_internal_href(string $currentRoute, string $href): ?string
{
    $href = trim($href);
    if (
        $href === '' ||
        str_starts_with($href, '#') ||
        str_starts_with($href, 'mailto:') ||
        str_starts_with($href, 'tel:') ||
        str_starts_with($href, 'javascript:') ||
        preg_match('~^https?://~i', $href)
    ) {
        return null;
    }

    $parts = parse_url($href);
    $path = $parts['path'] ?? '';
    if ($path === '' || tk_is_asset_path($path)) {
        return null;
    }

    $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
    $hash = isset($parts['fragment']) ? ('#' . $parts['fragment']) : '';

    if (str_ends_with($path, '/index.html')) {
        $path = substr($path, 0, -strlen('/index.html'));
    } elseif ($path === 'index.html') {
        $path = '';
    }

    $currentDir = $currentRoute === '' ? '' : $currentRoute;
    $baseDir = $currentDir === '' ? '' : dirname($currentDir);
    if ($baseDir === '.') {
        $baseDir = '';
    }

    if (str_starts_with($path, '/')) {
        $resolved = trim($path, '/');
    } else {
        $prefix = $currentDir === '' ? '' : ($currentDir . '/');
        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        $combined = $prefix . $path;
        $segments = [];
        foreach (explode('/', $combined) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $seg;
        }
        $resolved = implode('/', $segments);
    }

    if ($resolved === 'index.html' || str_ends_with($resolved, '/index.html')) {
        $resolved = preg_replace('~/index\.html$~', '', $resolved) ?? '';
    }

    if (tk_is_asset_path($resolved)) {
        return null;
    }

    $validRoutes = tk_valid_page_routes();
    if ($resolved !== '' && !isset($validRoutes[$resolved])) {
        $resolved = '';
    }

    $url = TK_BASE_URL;
    if ($resolved !== '') {
        $url .= '/' . $resolved;
    }
    return $url . $query . $hash;
}

function tk_rewrite_html_urls(string $html): string
{
    $base = TK_BASE_URL;
    $requestPath = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestPath, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }
    $path = tk_normalize_request_path($path);
    $currentRoute = trim($path, '/');

    $html = str_replace('__TK_BASE__', $base, $html);

    // 1. Rewrite static assets first (must run before page-link resolver)
    $html = preg_replace(
        '~\b(href|src)=(["\'])(?:\.\./)+(assets/[^"\']*)\2~i',
        '$1=$2' . $base . '/$3$2',
        $html
    ) ?? $html;

    $html = preg_replace(
        '~\b(href|src)=(["\'])\./(assets/[^"\']*)\2~i',
        '$1=$2' . $base . '/$3$2',
        $html
    ) ?? $html;

    $html = preg_replace(
        '~\b(href|src)=(["\'])/(assets/[^"\']*)\2~i',
        '$1=$2' . $base . '/$3$2',
        $html
    ) ?? $html;

    $html = preg_replace(
        '~\b(href|src)=(["\'])\.\/_next\/static\/chunks\/([^"\']+?)\/index\.html\2~i',
        '$1=$2' . $base . '/assets/www.takeda.com/_next/static/chunks/$3$2',
        $html
    ) ?? $html;

    $html = preg_replace(
        '~\b(href|src)=(["\'])\.\/_next\/([^"\']+?)\2~i',
        '$1=$2' . $base . '/assets/www.takeda.com/_next/$3$2',
        $html
    ) ?? $html;

    $html = preg_replace(
        '~\b(href|src)=(["\'])(?:\.\./)+_next\/static\/chunks\/([^"\']+?)(?:/index\.html)?\2~i',
        '$1=$2' . $base . '/assets/www.takeda.com/_next/static/chunks/$3$2',
        $html
    ) ?? $html;

    $html = preg_replace(
        '~\b(href|src)=(["\'])(?:\.\./)+_next\/([^"\']+?)(?:/index\.html)?\2~i',
        '$1=$2' . $base . '/assets/www.takeda.com/_next/$3$2',
        $html
    ) ?? $html;

    // 2. Rewrite internal page links only (not CSS/JS/assets)
    $html = preg_replace_callback(
        '~\b(href)=(["\'])([^"\']+)\2~i',
        static function (array $m) use ($currentRoute): string {
            $resolved = tk_resolve_internal_href($currentRoute, $m[3]);
            if ($resolved === null) {
                return $m[0];
            }
            return 'href=' . $m[2] . $resolved . $m[2];
        },
        $html
    ) ?? $html;

    $html = preg_replace(
        '~\b(href)=(["\'])' . preg_quote($base, '~') . '/index\.html\2~i',
        'href=$2' . $base . '$2',
        $html
    ) ?? $html;

    return $html;
}

function tk_start_output_rewrite(): void
{
    static $started = false;
    if ($started) {
        return;
    }
    $started = true;
    ob_start('tk_rewrite_html_urls');
    register_shutdown_function(static function (): void {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    });
}
