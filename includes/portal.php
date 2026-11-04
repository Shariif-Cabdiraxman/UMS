<?php
/**
 * Shared data and guards for the student portal.
 *
 * Every query in this file is scoped by a student id that came from the
 * session, never from the URL. A portal page can therefore show a record,
 * an enrolment or a grade only when it belongs to the person signed in.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/** The signed-in student row, loaded once per request. */
function current_student(bool $refresh = false): ?array
{
    static $student = null;
    static $loaded = false;

    if ($refresh) {
        $student = null;
        $loaded = false;
    }

    if ($loaded) {
        return $student;
    }

    $loaded = true;
    $user = current_user();

    if (($user['role'] ?? '') !== 'student' || empty($user['student_id'])) {
        return null;
    }

    $student = db_row(
        'SELECT `id`, `student_no`, `first_name`, `last_name`, `gender`, `date_of_birth`,
                `email`, `phone`, `faculty_id`, `department_id`, `enrollment_year`,
                `enrollment_date`, `status`, `created_at`, `updated_at`
           FROM `students`
          WHERE `id` = ?
          LIMIT 1',
        'i',
        [(int) $user['student_id']]
    );

    return $student;
}

/**
 * Open a portal page, or stop the request.
 *
 * Staff never enter the portal: the student view is a separate surface with
 * its own navigation and its own record. A student whose account has been
 * deactivated loses the session immediately.
 */
function require_student(): array
{
    require_login();

    $user = current_user();

    if (($user['role'] ?? '') !== 'student' || empty($user['student_id'])) {
        render_error_page(
            403,
            'This is the student portal',
            'The student portal is reserved for student accounts. Your account is signed in with the '
                . humanize($user['role'] ?? 'staff') . ' role; use the administration workspace instead.',
            'Go to the dashboard',
            'dashboard.php'
        );
    }

    $account = db_row(
        'SELECT `id`, `status` FROM `student_accounts` WHERE `student_id` = ? LIMIT 1',
        'i',
        [(int) $user['student_id']]
    );

    if ($account === null || $account['status'] !== 'active') {
        logout();
        session_start();
        session_regenerate_id(true);
        flash_set('info', 'Your student portal account is not active. Please contact the registrar.');
        redirect('auth/login.php');
    }

    $student = current_student(true);

    if ($student === null) {
        logout();
        session_start();
        session_regenerate_id(true);
        flash_set('info', 'That student record could not be found. Please contact the registrar.');
        redirect('auth/login.php');
    }

    return $student;
}

/** The faculty and department names attached to a student record. */
function student_programme(array $student): ?array
{
    return db_row(
        'SELECT f.`name` AS `faculty_name`, d.`name` AS `department_name`
           FROM `faculties` f
           JOIN `departments` d ON d.`faculty_id` = f.`id`
          WHERE f.`id` = ? AND d.`id` = ?
          LIMIT 1',
        'ii',
        [(int) $student['faculty_id'], (int) $student['department_id']]
    );
}

/**
 * Every enrolment belonging to one student, newest term first.
 *
 * The average is calculated per enrolment with a correlated subquery so the
 * outer query does not need a GROUP BY and stays compatible with strict SQL
 * modes.
 *
 * @return array<int,array<string,mixed>>
 */
function student_enrollments(int $studentId, ?string $academicYear = null, ?string $semester = null): array
{
    $sql    = 'SELECT e.`id`, e.`course_id`, e.`status`, e.`academic_year`, e.`semester`,
                      e.`enrollment_date`, e.`created_at`,
                      c.`course_code`, c.`course_name`, c.`credit_hours`, c.`description`,
                      c.`semester` AS `course_semester`,
                      l.`id` AS `lecturer_id`,
                      CONCAT(l.`first_name`, \' \', l.`last_name`) AS `lecturer_name`,
                      (SELECT ROUND(AVG(g.`marks`), 2)
                         FROM `grades` g
                        WHERE g.`enrollment_id` = e.`id`) AS `average_marks`
                 FROM `enrollments` e
                 JOIN `courses` c     ON c.`id` = e.`course_id`
                 LEFT JOIN `lecturers` l ON l.`id` = c.`lecturer_id`
                WHERE e.`student_id` = ?';
    $types  = 'i';
    $params = [$studentId];

    if ($academicYear !== null && $academicYear !== '') {
        $sql     .= ' AND e.`academic_year` = ?';
        $types   .= 's';
        $params[] = $academicYear;
    }

    if ($semester !== null && $semester !== '') {
        $sql     .= ' AND e.`semester` = ?';
        $types   .= 's';
        $params[] = $semester;
    }

    $sql .= ' ORDER BY e.`academic_year` DESC, e.`semester` DESC, c.`course_code`';

    return db_all($sql, $types, $params);
}

/** One course on a student's record, using the most recent enrolment. */
function student_course(int $studentId, int $courseId): ?array
{
    return db_row(
        'SELECT e.`id`, e.`course_id`, e.`status`, e.`academic_year`, e.`semester`,
                e.`enrollment_date`,
                c.`course_code`, c.`course_name`, c.`credit_hours`, c.`description`,
                c.`semester` AS `course_semester`,
                d.`name` AS `department_name`,
                l.`id` AS `lecturer_id`,
                CONCAT(l.`first_name`, \' \', l.`last_name`) AS `lecturer_name`,
                (SELECT ROUND(AVG(g.`marks`), 2)
                   FROM `grades` g
                  WHERE g.`enrollment_id` = e.`id`) AS `average_marks`
           FROM `enrollments` e
           JOIN `courses` c     ON c.`id` = e.`course_id`
           JOIN `departments` d ON d.`id` = c.`department_id`
           LEFT JOIN `lecturers` l ON l.`id` = c.`lecturer_id`
          WHERE e.`student_id` = ? AND e.`course_id` = ?
          ORDER BY e.`academic_year` DESC, e.`semester` DESC
          LIMIT 1',
        'ii',
        [$studentId, $courseId]
    );
}

/**
 * Every mark recorded against a student's enrolments.
 *
 * @return array<int,array<string,mixed>>
 */
function student_grades(int $studentId): array
{
    return db_all(
        'SELECT g.`id`, g.`assessment`, g.`marks`, g.`letter_grade`, g.`updated_at`,
                e.`id` AS `enrollment_id`, e.`status` AS `enrollment_status`,
                e.`academic_year`, e.`semester`,
                c.`id` AS `course_id`, c.`course_code`, c.`course_name`, c.`credit_hours`
           FROM `grades` g
           JOIN `enrollments` e ON e.`id` = g.`enrollment_id`
           JOIN `courses` c     ON c.`id` = e.`course_id`
          WHERE e.`student_id` = ?
          ORDER BY e.`academic_year` DESC, e.`semester` DESC, c.`course_code`, g.`assessment`',
        'i',
        [$studentId]
    );
}

/** Overall mark count and average for one student. */
function student_grade_summary(int $studentId): array
{
    $row = db_row(
        'SELECT COUNT(*)               AS `grade_count`,
                ROUND(AVG(g.`marks`), 2) AS `average`
           FROM `grades` g
           JOIN `enrollments` e ON e.`id` = g.`enrollment_id`
          WHERE e.`student_id` = ?',
        'i',
        [$studentId]
    );

    return [
        'grade_count' => (int) ($row['grade_count'] ?? 0),
        'average'     => $row['average'] ?? null,
    ];
}

/** Credit hours from completed enrolments. */
function student_completed_credits(int $studentId): int
{
    return (int) db_value(
        'SELECT COALESCE(SUM(c.`credit_hours`), 0)
           FROM `enrollments` e
           JOIN `courses` c ON c.`id` = e.`course_id`
          WHERE e.`student_id` = ? AND e.`status` = \'completed\'',
        'i',
        [$studentId]
    );
}

/** The student's portal sign-in row, without the password hash. */
function student_account(int $studentId): ?array
{
    return db_row(
        'SELECT `id`, `student_id`, `status`, `last_login_at`, `created_at`, `updated_at`
           FROM `student_accounts`
          WHERE `student_id` = ?
          LIMIT 1',
        'i',
        [$studentId]
    );
}

/**
 * Published notices, newest first.
 *
 * @return array<int,array<string,mixed>>
 */
function student_notices(int $limit = 0): array
{
    $sql = 'SELECT a.`id`, a.`title`, a.`content`, a.`published_at`,
                   u.`full_name` AS `author_name`
              FROM `announcements` a
              LEFT JOIN `users` u ON u.`id` = a.`author_id`
             WHERE a.`status` = \'published\'
               AND a.`published_at` IS NOT NULL
             ORDER BY a.`published_at` DESC';

    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }

    return db_all($sql);
}

/** One published notice, or null when it is a draft, archived or missing. */
function student_notice(int $id): ?array
{
    return db_row(
        'SELECT a.`id`, a.`title`, a.`content`, a.`published_at`,
                u.`full_name` AS `author_name`
           FROM `announcements` a
           LEFT JOIN `users` u ON u.`id` = a.`author_id`
          WHERE a.`id` = ?
            AND a.`status` = \'published\'
            AND a.`published_at` IS NOT NULL
          LIMIT 1',
        'i',
        [$id]
    );
}
