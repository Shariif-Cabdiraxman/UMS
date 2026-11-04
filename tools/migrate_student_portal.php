<?php
/**
 * Add the student portal account table to an existing database.
 *
 * Fresh installations get this table from database.sql. An installation that
 * already holds real records should run this instead of re-importing the
 * schema, because database.sql starts from a clean slate.
 *
 * Usage:
 *     php tools/migrate_student_portal.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$exists = db_value(
    "SHOW TABLES LIKE 'student_accounts'"
);

if ($exists !== null) {
    fwrite(STDOUT, "student_accounts already exists; nothing to do.\n");
    exit(0);
}

db_execute(
    'CREATE TABLE `student_accounts` (
        `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `student_id`    INT UNSIGNED NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `status`        ENUM(\'active\',\'inactive\') NOT NULL DEFAULT \'active\',
        `last_login_at` DATETIME     NULL DEFAULT NULL,
        `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_student_accounts_student` (`student_id`),
        KEY `idx_student_accounts_status` (`status`),
        CONSTRAINT `fk_student_accounts_student`
            FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

fwrite(STDOUT, "Created student_accounts. Create the first login with:\n");
fwrite(STDOUT, "    php tools/set_password.php --student <student-number> '<password>'\n");
