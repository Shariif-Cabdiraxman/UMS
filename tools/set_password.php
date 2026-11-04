<?php
/**
 * Set or reset a staff account password from the command line.
 *
 * The seed file ships no usable password, so a fresh import needs one of:
 *
 *     php tools/set_password.php admin 'choose-a-strong-password'
 *     php tools/set_password.php --demo
 *
 * --demo restores the public demonstration passwords and is only for a local
 * portfolio copy. Never run it on a real installation.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$first = $argv[1] ?? '';

if ($first === '--demo') {
    $accounts = [
        'admin'      => 'Admin@123',
        'registrar'  => 'Registrar@123',
        'admissions' => 'Registrar@123',
    ];
} else {
    $username = $first;
    $password = $argv[2] ?? '';

    if ($username === '' || $password === '') {
        fwrite(STDERR, "Usage: php tools/set_password.php <username> <password>\n");
        fwrite(STDERR, "       php tools/set_password.php --demo\n");
        exit(1);
    }

    if (strlen($password) < 8) {
        fwrite(STDERR, "The password must be at least 8 characters.\n");
        exit(1);
    }

    $accounts = [$username => $password];
}

foreach ($accounts as $username => $password) {
    $user = db_row(
        'SELECT `id` FROM `users` WHERE `username` = ? LIMIT 1',
        's',
        [$username]
    );

    if ($user === null) {
        fwrite(STDERR, "No account found for '{$username}'.\n");
        exit(1);
    }

    db_execute(
        'UPDATE `users` SET `password_hash` = ?, `status` = ? WHERE `id` = ?',
        'ssi',
        [password_hash($password, PASSWORD_DEFAULT), 'active', (int) $user['id']]
    );

    fwrite(STDOUT, "Password set for {$username}.\n");
}

if ($first === '--demo') {
    fwrite(STDOUT, "Demo passwords are public. Never run --demo on a real installation.\n");
}
