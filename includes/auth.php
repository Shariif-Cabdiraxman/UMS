<?php
/**
 * Authentication, authorisation and CSRF protection.
 *
 * Roles
 * -----
 *   admin      Full access to every module, including the structural
 *              records (faculties, departments, lecturers).
 *   registrar  Manages the academic record: students, courses, enrollments,
 *              grades and announcements. Read-only elsewhere.
 *
 * Authorisation is always checked on the server. Hiding a button in the
 * navigation is a convenience for the person using the interface, never the
 * thing that actually protects the data.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/error_page.php';

/**
 * Which permissions each role holds.
 *
 * A permission ending in `.manage` means "may create, edit and delete".
 * Every signed-in user can read every module; this map only controls
 * writes.
 */
const ROLE_PERMISSIONS = [
    'admin' => [
        'faculties.manage',
        'departments.manage',
        'lecturers.manage',
        'students.manage',
        'courses.manage',
        'enrollments.manage',
        'grades.manage',
        'announcements.manage',
        'users.manage',
    ],
    'registrar' => [
        'students.manage',
        'courses.manage',
        'enrollments.manage',
        'grades.manage',
        'announcements.manage',
    ],
];

/** How many failed sign-in attempts are tolerated before a short cool-down. */
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 900; // 15 minutes

// =====================================================================
// Session lifecycle
// =====================================================================

/**
 * Sign the user out and clear the session completely.
 */
function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Sign a user in after their password has been verified.
 *
 * The session id is regenerated at the moment of privilege change so that
 * an id an attacker might already hold cannot be reused afterwards.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['created_at']    = time();
    $_SESSION['last_activity'] = time();
    unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);

    db_execute(
        'UPDATE `users` SET `last_login_at` = NOW() WHERE `id` = ?',
        'i',
        [(int) $user['id']]
    );
}

/** The signed-in user as stored in the session, or null. */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id'         => (int) $_SESSION['user_id'],
        'username'   => $_SESSION['username'] ?? '',
        'full_name'  => $_SESSION['full_name'] ?? '',
        'email'      => $_SESSION['email'] ?? '',
        'role'       => $_SESSION['role'] ?? 'registrar',
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/**
 * Sign out and bounce to the login page if the session is missing or stale.
 *
 * Every protected page calls this at the top.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        // Remember where they were heading so login can return them there.
        $_SESSION['intended_url'] = current_path_and_query();
        flash_set('info', 'Please sign in to continue.');
        redirect('auth/login.php');
    }

    enforce_session_timeout();
}

/** Abandon a session that has been idle too long or has simply run too long. */
function enforce_session_timeout(): void
{
    $now = time();
    $idle = $now - (int) ($_SESSION['last_activity'] ?? $now);
    $age = $now - (int) ($_SESSION['created_at'] ?? $now);

    if ($idle > SESSION_IDLE_TIMEOUT || $age > SESSION_ABSOLUTE_TIMEOUT) {
        logout();
        session_start();
        flash_set('info', 'Your session expired. Please sign in again.');
        redirect('auth/login.php');
    }

    $_SESSION['last_activity'] = $now;
}

/** The current request path including its query string. */
function current_path_and_query(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    // Drop the base path so the stored value is relative to the project root.
    if (BASE_URL !== '/' && strpos($uri, BASE_URL) === 0) {
        $uri = '/' . ltrim(substr($uri, strlen(BASE_URL)), '/');
    }

    return $uri === '' ? '/' : $uri;
}

// =====================================================================
// Authorisation
// =====================================================================

/** May the signed-in user perform this action? */
function can(string $permission): bool
{
    $user = current_user();
    if ($user === null) {
        return false;
    }

    $granted = ROLE_PERMISSIONS[$user['role']] ?? [];

    return in_array($permission, $granted, true);
}

/**
 * Stop the request unless the signed-in user holds this permission.
 *
 * Call this at the top of every page that writes data. Because the check
 * runs here and not in the interface, editing the URL or calling the POST
 * endpoint directly does not get anyone past it.
 */
function require_permission(string $permission): void
{
    if (can($permission)) {
        return;
    }

    $user = current_user();

    render_error_page(
        403,
        'You do not have access to this section',
        'Your account is signed in as ' . ($user['full_name'] ?? 'a user') . ' with the '
        . humanize($user['role'] ?? '') . ' role. This action is reserved for an administrator.',
        'Go to the dashboard',
        'dashboard.php'
    );
}

/**
 * Is this module read-only for the signed-in user?
 *
 * Used to hide the create and edit controls. The server-side check is
 * require_permission() on the POST handlers; this only decides what to draw.
 */
function is_read_only(string $permission = 'students.manage'): bool
{
    return !can($permission);
}

// =====================================================================
// Sign-in
// =====================================================================

/**
 * Verify a username (or email) and password.
 *
 * @return array{ok:bool,message:string,user:?array}
 */
function attempt_login(string $identifier, string $password): array
{
    // Basic per-session brute force brake. A public deployment would also
    // want server-side throttling and account lockout.
    $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
    if ($lockedUntil > time()) {
        $minutes = (int) ceil(($lockedUntil - time()) / 60);

        return [
            'ok'      => false,
            'message' => 'Too many failed attempts. Try again in ' . $minutes . ' minute'
                . ($minutes === 1 ? '' : 's') . '.',
            'user'    => null,
        ];
    }

    $identifier = trim($identifier);

    // One statement, two possible columns to match on. The value is bound,
    // never concatenated, so this cannot be used to probe the table.
    $user = db_row(
        'SELECT `id`, `username`, `email`, `password_hash`, `full_name`, `role`, `status`
           FROM `users`
          WHERE (`username` = ? OR `email` = ?)
          LIMIT 1',
        'ss',
        [$identifier, $identifier]
    );

    // password_verify is run even when no user matched, so that a wrong
    // username and a wrong password take the same amount of time and the
    // form cannot be used to discover which usernames exist.
    $hash = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';

    if (!password_verify($password, $hash) || $user === null) {
        record_failed_login();

        return [
            'ok'      => false,
            'message' => 'Those credentials do not match an account.',
            'user'    => null,
        ];
    }

    if ($user['status'] !== 'active') {
        return [
            'ok'      => false,
            'message' => 'This account has been deactivated. Contact an administrator.',
            'user'    => null,
        ];
    }

    // Transparently upgrade the stored hash if PHP's default algorithm moves on.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_execute(
            'UPDATE `users` SET `password_hash` = ? WHERE `id` = ?',
            'si',
            [password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]
        );
    }

    unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
    login_user($user);

    return ['ok' => true, 'message' => '', 'user' => $user];
}

function record_failed_login(): void
{
    $failures = (int) ($_SESSION['login_failures'] ?? 0) + 1;
    $_SESSION['login_failures'] = $failures;

    if ($failures >= LOGIN_MAX_ATTEMPTS) {
        $_SESSION['login_locked_until'] = time() + LOGIN_LOCKOUT_SECONDS;
        $_SESSION['login_failures'] = 0;
    }
}

// =====================================================================
// CSRF protection
// =====================================================================

/** The CSRF token for this session, created on first use. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** A hidden input to drop inside every form that changes data. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Reject the request unless it carries this session's CSRF token.
 *
 * Without this, another site could submit a form to this one while the user
 * is signed in and quietly delete records.
 */
function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';

    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
    render_error_page(
        419,
        'That form could not be verified',
        'The security token on the form was missing or out of date, so the request was '
        . 'stopped before anything was changed. This usually happens when a page was left '
        . 'open for a long time. Please go back, reload the page and try again.',
        'Reload the page',
        'reload'
    );
    }
}

/** Helper for a 404. */
function not_found(string $message = 'The page you asked for does not exist.'): void
{
    http_response_code(404);
    render_error_page(404, 'Page not found', $message, 'Go to the dashboard', 'dashboard.php');
}
