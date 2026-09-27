<?php
/**
 * Standalone error page.
 *
 * Used for 403, 404, 419 and any other fatal status. It renders its own
 * complete document rather than trying to drop itself inside the admin
 * shell, because it has to work even when the failure happened before
 * (or instead of) a normal page.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';

/**
 * Render an error page and stop.
 *
 * @param string $title   Short heading, sentence case.
 * @param string $message One or two sentences explaining what to do next.
 * @param string $action  Label for the recovery button.
 * @param string $href    Where the button goes. May be a 'javascript:' URL.
 */
function render_error_page(int $code, string $title, string $message, string $action = 'Go back', string $href = 'javascript:history.back()'): void
{
    if (!headers_sent()) {
        http_response_code($code);
    }

    // 419 is not a real HTTP status; PHP would reject it, so map it to 400
    // on the wire and keep 419 in the page heading.
    $wireStatus = $code === 419 ? 400 : $code;
    if (!headers_sent()) {
        http_response_code($wireStatus);
    }

    $hints = [
        403 => 'Insufficient permission',
        404 => 'Nothing here',
        419 => 'Request stopped',
        500 => 'Something went wrong',
    ];

    $hint = $hints[$code] ?? 'Error';
    ?>
    <!doctype html>
    <html lang="en" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title><?= e($code) ?> <?= e($title) ?> · <?= e(APP_NAME) ?></title>
        <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
        <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    </head>
    <body class="page page--centred">
        <main class="errorpage">
            <div class="errorpage__mark" aria-hidden="true">
                <svg viewBox="0 0 48 48" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.25">
                    <path d="M24 5 6 13v11c0 9.5 7.7 17.4 18 19 10.3-1.6 18-9.5 18-19V13L24 5Z"/>
                    <path d="M24 16v10" stroke-linecap="round"/>
                    <circle cx="24" cy="32" r="1.1" fill="currentColor" stroke="none"/>
                </svg>
            </div>
            <p class="errorpage__code"><?= e($code) ?> <span><?= e($hint) ?></span></p>
            <h1 class="errorpage__title"><?= e($title) ?></h1>
            <p class="errorpage__message"><?= $message ?></p>
            <div class="errorpage__actions">
                <a class="btn btn--primary" href="<?= e($href) ?>"><?= e($action) ?></a>
                <a class="btn btn--quiet" href="<?= e(url('auth/login.php')) ?>">Sign in</a>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}
