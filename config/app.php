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
//
// Off unless it is explicitly switched on, so a fresh checkout on a public
// server cannot leak exception messages by accident.
//
// To turn it on locally, create an empty file:
//
//     config/debug.local.php
//
// That file is listed in .gitignore, so switching debugging on never ends up
// in a commit. An environment variable works too, which is handy on a server:
//
//     setx APP_DEBUG 1            (PowerShell, then restart Apache)
//     $env:APP_DEBUG = '1'        (current shell only)
// ---------------------------------------------------------------------
$debugSetting = strtolower((string) (getenv('APP_DEBUG') ?: ''));
$debugEnabled = in_array($debugSetting, ['1', 'true', 'yes', 'on'], true)
    || is_file(APP_ROOT . '/config/debug.local.php');

define('APP_DEBUG', $debugEnabled);

// ---------------------------------------------------------------------
// Demonstration mode.
//
// While this is on, the sign-in screen lists the seeded accounts so the
// project can be opened and used straight away. It is on by default because
// this repository is a portfolio project rather than a live system, and the
// seeded data is fictitious either way.
//
// Turn it off for any real installation, so the sign-in page stops
// advertising working credentials. Create an empty file:
//
//     config/demo.off.local.php
//
// or set an environment variable:
//
//     setx APP_DEMO_MODE 0        (PowerShell, then restart Apache)
//     $env:APP_DEMO_MODE = '0'    (current shell only)
// ---------------------------------------------------------------------
$demoSetting = strtolower((string) (getenv('APP_DEMO_MODE') ?: ''));
$demoDisabled = in_array($demoSetting, ['0', 'false', 'no', 'off'], true)
    || is_file(APP_ROOT . '/config/demo.off.local.php');
$demoEnabled = !$demoDisabled;

define('APP_DEMO_MODE', $demoEnabled);

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
