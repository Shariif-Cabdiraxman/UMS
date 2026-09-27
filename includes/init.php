<?php
/**
 * Bootstrap.
 *
 * This is the only file a page needs to require. It pulls in the
 * configuration, opens the database layer, makes the shared helpers and
 * authorisation functions available, and sets the security headers.
 *
 *     require_once __DIR__ . '/includes/init.php';
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/error_page.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/crud.php';
require_once __DIR__ . '/list_query.php';

// =====================================================================
// Response headers
//
// The application sends its own HTML, CSS, JS and nothing else, so it can
// commit to a fairly tight policy. No inline scripts are used anywhere, which
// is what lets script-src stay at 'self'.
// =====================================================================

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()');
    header('Content-Security-Policy: ' . implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "img-src 'self' data:",
        "font-src 'self'",
        // Style attributes are used for chart bar heights and the like.
        "style-src 'self' 'unsafe-inline'",
        "script-src 'self'",
    ]));
}

// =====================================================================
// Failure handling
//
// A database that is not running, or a bug in a page, should produce a
// readable message rather than a stack trace leaking onto the screen.
// =====================================================================

/** A page explaining that the database is unreachable. */
function render_database_unavailable(Throwable $exception): void
{
    $detail = APP_DEBUG ? $exception->getMessage() : '';

    render_error_page(
        503,
        'The database is not available',
        'The application could not connect to its MySQL database. On a fresh XAMPP install this '
        . 'usually means MySQL has not been started, or the database has not been imported yet.'
        . ($detail !== '' ? ' <span class="mono">' . $detail . '</span>' : ''),
        'Try again',
        'javascript:location.reload()'
    );
}

/**
 * Turn any uncaught error into a page a person can act on.
 */
set_exception_handler(function (Throwable $exception): void {
    error_log('[hagmah] ' . $exception->getMessage() . ' @ '
        . $exception->getFile() . ':' . $exception->getLine());

    render_error_page(
        500,
        'Something went wrong',
        'The request could not be completed. The problem has been written to the PHP error log.'
        . (APP_DEBUG ? '<br><span class="mono">' . e($exception->getMessage()) . '</span>' : ''),
        'Try again',
        'javascript:location.reload()'
    );
});

// =====================================================================
// Database availability
//
// Connecting is lazy, so the first real query is where a stopped MySQL
// would otherwise surface. Checking here means every page fails the same
// helpful way instead of some pages working and others not.
// =====================================================================

try {
    db()->ping();
} catch (Throwable $exception) {
    render_database_unavailable($exception);
}
