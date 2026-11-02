<?php
/**
 * Students — a single record.
 *
 * A student is addressed by their student number, so the record is looked up
 * by that reference rather than by an internal id. Beyond the personal and
 * programme fields sits a summary of their enrolments and recorded marks.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path    = 'students/index.php';
$student = null;

$handle = query_string('id');

if ($handle === '') {
    not_found('No student was requested. Open a student from the list to see their record.');
}

$student = find_student_by_no($handle);

if ($student === null) {
    not_found('That student does not exist. They may have been deleted.');
}

$id = (int) $student['id'];

$programme = db_row(
    'SELECT f.`name` AS `faculty_name`,
            d.`name` AS `department_name`
       FROM `faculties` f
       JOIN `departments` d ON d.`faculty_id` = f.`id`
      WHERE f.`id` = ? AND d.`id` = ?
      LIMIT 1',
    'ii',
    [(int) $student['faculty_id'], (int) $student['department_id']]
);

$enrollments = db_all(
    'SELECT e.`id`, e.`status`, e.`academic_year`, e.`semester`, e.`enrollment_date`,
            c.`course_code`, c.`course_name`,
            (SELECT ROUND(AVG(g.`marks`), 2) FROM `grades` g WHERE g.`enrollment_id` = e.`id`) AS `average_marks`
       FROM `enrollments` e
       JOIN `courses` c ON c.`id` = e.`course_id`
      WHERE e.`student_id` = ?
      ORDER BY e.`academic_year` DESC, e.`semester` DESC, c.`course_code`
      LIMIT 15',
    'i',
    [$id]
);

$enrollmentCount = (int) db_value('SELECT COUNT(*) FROM `enrollments` WHERE `student_id` = ?', 'i', [$id]);

$gradeSummary = db_row(
    'SELECT COUNT(*)               AS `grade_count`,
            ROUND(AVG(g.`marks`), 2) AS `average`
       FROM `grades` g
       JOIN `enrollments` e ON e.`id` = g.`enrollment_id`
      WHERE e.`student_id` = ?',
    'i',
    [$id]
);

$recentGrades = db_all(
    'SELECT g.`id`, g.`assessment`, g.`marks`, g.`letter_grade`,
            c.`course_code`, e.`academic_year`, e.`semester`, e.`id` AS `enrollment_id`
       FROM `grades` g
       JOIN `enrollments` e ON e.`id` = g.`enrollment_id`
       JOIN `courses` c     ON c.`id` = e.`course_id`
      WHERE e.`student_id` = ?
      ORDER BY g.`updated_at` DESC
      LIMIT 6',
    'i',
    [$id]
);

$canManage = can('students.manage');

$name = full_name($student['first_name'], $student['last_name']);

layout_start($name, 'students');

render_page_head([
    'eyebrow'  => $programme['faculty_name'] ?? 'Student body',
    'title'    => $name,
    'subtitle' => 'Student record ' . $student['student_no'],
    'meta'     => 'Record created ' . e(format_datetime($student['created_at']))
        . ' &middot; last changed ' . e(format_datetime($student['updated_at'])),
    'actions'  => record_actions($path, $canManage
        ? 'students/form.php?id=' . urlencode((string) $student['student_no'])
        : ''),
]);

render_readonly_notice('students.manage', 'student');

$enrollmentItems = [];
foreach ($enrollments as $enrollment) {
    $marks = $enrollment['average_marks'];
    $enrollmentItems[] = [
        'label' => $enrollment['course_name'],
        'meta'  => $enrollment['course_code'] . ' · ' . $enrollment['academic_year'] . ' · '
            . $enrollment['semester']
            . ($marks !== null ? ' · avg ' . format_marks($marks) : ''),
        'href'  => url('enrollments/view.php?id=' . (int) $enrollment['id']),
        'trail' => badge($enrollment['status']),
    ];
}
?>

<div class="profile-layout">
    <aside class="profile-card">
        <div class="profile-card__avatar" aria-hidden="true"><?= icon('student', 36) ?></div>
        <h2 class="profile-card__name"><?= e($name) ?></h2>
        <p class="profile-card__role">
            <?= e($programme['department_name'] ?? 'No department') ?>
            <?php if ($programme !== null): ?>
                · <?= e($programme['faculty_name']) ?>
            <?php endif; ?>
        </p>
        <div class="profile-card__badges">
            <?= badge($student['status']) ?>
            <span class="badge badge--neutral"><span class="badge__mark" aria-hidden="true"></span><?= e(humanize($student['gender'])) ?></span>
        </div>

        <div class="profile-card__divider"></div>

        <div class="profile-meta">
            <div class="profile-meta__item">
                <span class="profile-meta__icon"><?= icon('student', 14) ?></span>
                <span>
                    <span class="profile-meta__label">Student number</span>
                    <span class="profile-meta__value table__id"><?= e($student['student_no']) ?></span>
                </span>
            </div>
            <div class="profile-meta__item">
                <span class="profile-meta__icon"><?= icon('mail', 14) ?></span>
                <span>
                    <span class="profile-meta__label">Email</span>
                    <span class="profile-meta__value"><a href="mailto:<?= e($student['email']) ?>"><?= e($student['email']) ?></a></span>
                </span>
            </div>
            <div class="profile-meta__item">
                <span class="profile-meta__icon"><?= icon('phone', 14) ?></span>
                <span>
                    <span class="profile-meta__label">Phone</span>
                    <span class="profile-meta__value"><?= $student['phone'] !== null ? e($student['phone']) : '<span class="text-3">Not recorded</span>' ?></span>
                </span>
            </div>
            <div class="profile-meta__item">
                <span class="profile-meta__icon"><?= icon('calendar', 14) ?></span>
                <span>
                    <span class="profile-meta__label">Date of birth</span>
                    <span class="profile-meta__value">
                        <?= e(format_db_date($student['date_of_birth'])) ?>
                        <?php if ($student['date_of_birth'] !== null): ?>
                            · age <?= e((string) age_from($student['date_of_birth'])) ?>
                        <?php endif; ?>
                    </span>
                </span>
            </div>
            <div class="profile-meta__item">
                <span class="profile-meta__icon"><?= icon('faculty', 14) ?></span>
                <span>
                    <span class="profile-meta__label">Programme</span>
                    <span class="profile-meta__value">
                        <?php if ($programme !== null): ?>
                            <a href="<?= e(url('departments/view.php?id=' . (int) $student['department_id'])) ?>"><?= e($programme['department_name']) ?></a>
                        <?php else: ?>
                            <span class="text-3">Not assigned</span>
                        <?php endif; ?>
                    </span>
                </span>
            </div>
            <div class="profile-meta__item">
                <span class="profile-meta__icon"><?= icon('clock', 14) ?></span>
                <span>
                    <span class="profile-meta__label">Enrolled</span>
                    <span class="profile-meta__value"><?= e((string) $student['enrollment_year']) ?> · <?= e(format_db_date($student['enrollment_date'])) ?></span>
                </span>
            </div>
        </div>

        <?php if (can('enrollments.manage')): ?>
            <a class="btn btn--primary btn--block mt-3" href="<?= e(url('enrollments/form.php?student=' . urlencode((string) $student['student_no']))) ?>">
                <?= icon('plus', 15) ?><span>Enroll in a course</span>
            </a>
        <?php endif; ?>
    </aside>

    <div class="stackgap">
        <section class="stats stats--3" aria-label="Student summary">
            <article class="stat">
                <div class="stat__top">
                    <span class="stat__icon"><?= icon('enrollment', 16) ?></span>
                    <p class="stat__label">Enrollments</p>
                </div>
                <p class="stat__value"><?= e(format_number($enrollmentCount)) ?></p>
                <p class="stat__foot">Courses registered in the record</p>
            </article>
            <article class="stat">
                <div class="stat__top">
                    <span class="stat__icon stat__icon--blue"><?= icon('grades', 16) ?></span>
                    <p class="stat__label">Marks recorded</p>
                </div>
                <p class="stat__value"><?= e(format_number((int) ($gradeSummary['grade_count'] ?? 0))) ?></p>
                <p class="stat__foot">Across every assessment</p>
            </article>
            <article class="stat">
                <div class="stat__top">
                    <span class="stat__icon stat__icon--peach"><?= icon('award', 16) ?></span>
                    <p class="stat__label">Average mark</p>
                </div>
                <p class="stat__value">
                    <?= ($gradeSummary['average'] ?? null) !== null ? e(format_marks($gradeSummary['average'])) : '—' ?>
                </p>
                <p class="stat__foot">
                    <?php if (($gradeSummary['average'] ?? null) !== null): ?>
                        <?= grade_badge(grade_letter((float) $gradeSummary['average'])) ?>
                        <span>current standing</span>
                    <?php else: ?>
                        <span>No marks yet</span>
                    <?php endif; ?>
                </p>
            </article>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Enrolments</h2>
                    <p class="panel__sub">
                        <?php if ($enrollmentCount > count($enrollments)): ?>
                            The first <?= e((string) count($enrollments)) ?> of <?= e(format_number($enrollmentCount)) ?>
                        <?php else: ?>
                            <?= e(format_number($enrollmentCount)) ?> <?= $enrollmentCount === 1 ? 'registration' : 'registrations' ?> in the record
                        <?php endif; ?>
                    </p>
                </div>
                <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('enrollments/index.php?student=' . urlencode((string) $student['student_no']))) ?>">
                    See all
                </a>
            </div>
            <div class="panel__body">
                <?php render_related_list(
                    $enrollmentItems,
                    'No courses have been enrolled to this student yet.',
                    'enrollment'
                ); ?>
            </div>
        </section>

        <?php if ($recentGrades !== []): ?>
            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title">Recent marks</h2>
                        <p class="panel__sub">The most recently recorded assessments</p>
                    </div>
                    <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('grades/index.php?student=' . urlencode((string) $student['student_no']))) ?>">View all</a>
                </div>
                <div class="panel__body panel__body--flush">
                    <div class="tablewrap" style="border:0;box-shadow:none;border-radius:0">
                        <table class="table">
                            <thead>
                                <tr>
                                    <?= th('Course') ?>
                                    <?= th('Assessment') ?>
                                    <?= th('Marks', 'num') ?>
                                    <?= th('Grade', 'num') ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentGrades as $grade): ?>
                                <tr>
                                    <td>
                                        <a class="table__name" href="<?= e(url('enrollments/view.php?id=' . (int) $grade['enrollment_id'])) ?>">
                                            <?= e($grade['course_code']) ?>
                                        </a>
                                        <span class="table__sub"><?= e($grade['academic_year']) ?> · <?= e($grade['semester']) ?></span>
                                    </td>
                                    <td class="text-3"><?= e($grade['assessment']) ?></td>
                                    <td class="num"><?= e(format_marks($grade['marks'])) ?></td>
                                    <td class="num"><?= grade_badge($grade['letter_grade']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Record</h2>
                    <p class="panel__sub">Identity and programme information</p>
                </div>
            </div>
            <div class="panel__body">
                <?php render_deflist([
                    ['term' => 'Full name', 'desc' => e($name)],
                    ['term' => 'Reference', 'desc' => '<span class="table__id">' . e($student['student_no']) . '</span>'],
                    ['term' => 'Faculty', 'desc' => $programme !== null
                        ? '<a href="' . e(url('faculties/view.php?id=' . (int) $student['faculty_id'])) . '">' . e($programme['faculty_name']) . '</a>'
                        : '<span class="text-3">Not assigned</span>'],
                    ['term' => 'Department', 'desc' => $programme !== null
                        ? '<a href="' . e(url('departments/view.php?id=' . (int) $student['department_id'])) . '">' . e($programme['department_name']) . '</a>'
                        : '<span class="text-3">Not assigned</span>'],
                    ['term' => 'Gender', 'desc' => e(humanize($student['gender']))],
                    ['term' => 'Status', 'desc' => badge($student['status'])],
                ]); ?>
            </div>
        </section>
    </div>
</div>

<?php layout_end(); ?>
