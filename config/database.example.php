<?php
/**
 * Template for the database configuration.
 *
 * Copy this file to database.php and adjust the values for your machine:
 *
 *     cp config/database.example.php config/database.php
 *
 * On Windows, rename the copy in Explorer. The real database.php is listed
 * in .gitignore so your credentials are never pushed to a public
 * repository. Environment variables take precedence over the values below,
 * which lets you deploy without editing files.
 */

declare(strict_types=1);

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'university_management');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Create and return the shared mysqli connection.
 *
 * mysqli_report() is switched to exceptions so that a bad connection or a
 * broken query throws instead of silently returning false.
 */
function db(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $connection->set_charset(DB_CHARSET);
    } catch (mysqli_sql_exception $e) {
        // Never show credentials or the raw driver message to the browser.
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(503);
        exit(
            '<!doctype html><meta charset="utf-8">'
            . '<title>Database unavailable</title>'
            . '<div style="font:15px/1.6 system-ui,sans-serif;max-width:40rem;margin:15vh auto;padding:0 1.5rem">'
            . '<h1 style="font-size:1.25rem;margin:0 0 .75rem">The database is not available</h1>'
            . '<p style="color:#555;margin:0 0 1rem">The application could not connect to MySQL. '
            . 'Check that MySQL is running in the XAMPP control panel and that '
            . '<code>config/database.php</code> holds the right credentials.</p>'
            . '<p style="color:#555;margin:0">Have you imported <code>database.sql</code> into phpMyAdmin?</p>'
            . '</div>'
        );
    }

    return $connection;
}
