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

/**
 * Throw away anything already written, so a failure can be reported cleanly.
 *
 * Output buffering is started at the bottom of this file. Without it, a page
 * that fails after it has begun rendering has already committed the response:
 * the status stays 200 and the visitor gets half a page with an error box
 * wedged into the middle of it. Discarding the buffer first means a failure
 * always arrives as a whole, correctly status-coded page.
 */
function discard_output(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

/** A page explaining that the database is unreachable. */
function render_database_unavailable(Throwable $exception): void
{
    $detail = APP_DEBUG ? $exception->getMessage() : '';

    discard_output();

    render_error_page(
        503,
        'The database is not available',
        'The application could not connect to its MySQL database. On a fresh XAMPP install this '
        . 'usually means MySQL has not been started, or the database has not been imported yet.'
        . ($detail !== '' ? ' The driver reported: ' . $detail : '') . '.',
        'Try again',
        'reload'
    );
}

/**
 * Turn any uncaught error into a page a person can act on.
 */
set_exception_handler(function (Throwable $exception): void {
    error_log('[hagmah] ' . $exception->getMessage() . ' @ '
        . $exception->getFile() . ':' . $exception->getLine());

    discard_output();

    render_error_page(
        500,
        'Something went wrong',
        'The request could not be completed. The problem has been written to the PHP error log.'
        . (APP_DEBUG ? ' The message was: ' . $exception->getMessage() : ''),
        'Try again',
        'reload'
    );
});

// =====================================================================
// Buffering
//
// Started last, so that everything above (configuration, headers, handlers)
// runs before there is any buffer to discard. The buffer is flushed by PHP at
// the end of a successful request; discard_output() throws it away on failure.
// =====================================================================

ob_start();

/**
 * Catch the failures that never become an exception.
 *
 * Running out of memory, or a fatal error raised while a handler was already
 * running, bypasses set_exception_handler() entirely. PHP reports those here
 * at shutdown, which is the last chance to say something useful.
 */
register_shutdown_function(function (): void {
    $problem = error_get_last();

    if ($problem === null
        || !in_array($problem['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    // A page that finished normally leaves no buffer, so an error logged
    // earlier in the request is history rather than a failed response.
    if (ob_get_level() === 0) {
        return;
    }

    error_log('[hagmah] fatal: ' . $problem['message'] . ' @ '
        . $problem['file'] . ':' . $problem['line']);

    discard_output();

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="robots" content="noindex">'
        . '<title>Something went wrong &middot; ' . e(APP_NAME) . '</title>'
        . '<link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '"></head>'
        . '<body class="page page--centred"><main class="errorpage">'
        . '<p class="errorpage__code">500 <span>Something went wrong</span></p>'
        . '<h1 class="errorpage__title">The request could not be completed</h1>'
        . '<p class="errorpage__message">The problem has been written to the PHP error log.</p>'
        . '<div class="errorpage__actions">'
        . '<a class="btn btn--primary" href="' . e(current_url_path()) . '">Try again</a>'
        . '</div></main></body></html>';
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
