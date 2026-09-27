<?php
/**
 * Sign out.
 *
 * This is a GET, because a sign-out link has to work from the navigation.
 * That is safe here: signing out cannot damage anything, it only ends the
 * session. Every action that changes data in this application is a POST with
 * a CSRF token, which is the request that must be protected.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

if (is_logged_in()) {
    $name = current_user()['full_name'] ?? '';
    logout();

    // A fresh session just to carry the confirmation message across.
    session_start();
    session_regenerate_id(true);
    flash_set('success', $name !== '' ? 'You have been signed out, ' . $name . '.' : 'You have been signed out.');
}

redirect('auth/login.php');
