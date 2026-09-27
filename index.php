<?php
/**
 * Front controller for signed-out visitors.
 *
 * There is no public front page: the sign-in screen is the first thing
 * anybody sees, so index.php simply sends them to it.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

redirect('auth/login.php');
