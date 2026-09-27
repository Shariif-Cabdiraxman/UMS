<?php
/**
 * Application-wide configuration.
 *
 * Every page loads this file first (usually through includes/init.php).
 * It sets the timezone, error reporting, session rules and the handful of
 * constants the rest of the application refers to.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Identity of the institution
// ---------------------------------------------------------------------
define('APP_NAME', 'Hagmah University');
define('APP_TAGLINE', "Registrar's Office");
define('APP_SHORT', 'HAGMAH');

// ---------------------------------------------------------------------
// Paths
// ---------------------------------------------------------------------
define('APP_ROOT', dirname(__DIR__));
define('APP_INC', APP_ROOT . '/includes');

// ---------------------------------------------------------------------
// Timezone. Set this to your own zone, e.g. 'Indian/Maldives', 'Africa/Nairobi'.
// ---------------------------------------------------------------------
date_default_timezone_set('Indian/Maldives');

// ---------------------------------------------------------------------
// Development vs production error reporting.
// Set to false before putting this on a public server.
// ---------------------------------------------------------------------
define('APP_DEBUG', true);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// ---------------------------------------------------------------------
// Base URL
//
// Worked out by comparing this folder with Apache's document root, so the
// application keeps working if you rename the project folder or move it
// somewhere else inside htdocs.
// ---------------------------------------------------------------------
$documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
    ? str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'))
    : '';
$appPath      = str_replace('\\', '/', APP_ROOT);

$baseUrl = '/';
if ($documentRoot !== '' && strpos($appPath, $documentRoot) === 0) {
    $baseUrl = substr($appPath, strlen($documentRoot));
    $baseUrl = '/' . trim($baseUrl, '/');
}
define('BASE_URL', rtrim($baseUrl, '/') . '/');

// ---------------------------------------------------------------------
// Session
//
// session.use_strict_mode stops PHP accepting a session id that was never
// issued by the server, which blocks session fixation attempts.
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string) (60 * 60)); // 1 hour idle
    session_name('HAGSESSID');
    session_start();
}

// ---------------------------------------------------------------------
// Session lifetime
// ---------------------------------------------------------------------
define('SESSION_IDLE_TIMEOUT', 60 * 60);       // sign out after 1 hour idle
define('SESSION_ABSOLUTE_TIMEOUT', 60 * 60 * 8); // and after 8 hours in total

// ---------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------
define('PAGE_SIZE_DEFAULT', 15);
define('PAGE_SIZE_OPTIONS', '15,25,50,100');
