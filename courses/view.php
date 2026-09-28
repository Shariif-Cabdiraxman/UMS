<?php
/**
 * Courses — a single record.
 *
 * Follows the shape set by the faculty and department details: a masthead,
 * the fields of the record, then the lecturer and the enrolled students that
 * hang off it, with the students' recorded marks alongside.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'courses/index.php';
$id   = query_int('id');

if ($id < 1) {
    not_found('No course was requested. Open a course from the list to see its record.');
}

$course = db_row(
    'SELECT c.*,
            d.`name` AS `department_name`,
            f.`id`   AS `faculty_id`,
            f.`name` AS `faculty_name`,
            CONCAT(l.`first_name`, \' \', l.`last_name`) AS `lecturer_name`,
            l.`staff_no` AS `lecturer_staff_no`
       FROM `courses` c
       JOIN `departments` d    ON d.`id` = c.`department_id`
       JOIN `faculties` f      ON f.`id` = d.`faculty_id`
       LEFT JOIN `lecturers` l ON l.`id` = c.`lecturer_id`
      WHERE c.`id` = ?
      LIMIT 1',
    'i',
    [$id]
);

if ($course === null) {
    not_found('That course does not exist. It may have been deleted.');
}

$enrollments = db_all(
    'SELECT e.`id`, e.`status`, e.`academic_year`, e.`semester`,
            s.`id` AS `student_id`, s.`student_no`, s.`first_name`, s.`last_name`,
            (SELECT ROUND(AVG(g.`marks`), 2) FROM `grades` g WHERE g.`enrollment_id` = e.`id`) AS `average_marks`
       FROM `enrollments` e
       JOIN `students` s ON s.`id` = e.`student_id`
      WHERE e.`course_id` = ?
      ORDER BY e.`academic_year` DESC, e.`semester` DESC, s.`last_name`, s.`first_name`
      LIMIT 12',
    'i',
    [$id]
);

$enrollmentCount = (int) db_value('SELECT COUNT(*) FROM `enrollments` WHERE `course_id` = ?', 'i', [$id]);

$canManage = can('courses.manage');

layout_start($course['course_name'], 'courses');

render_page_head([
    'eyebrow'  => $course['department_name'],
    'title'    => $course['course_name'],
    'subtitle' => 'Course record ' . $course['course_code'],
    'meta'     => 'Record created ' . e(format_datetime($course['created_at']))
        . ' &middot; last changed ' . e(format_datetime($course['updated_at'])),
    'actions'  => record_actions($path, $canManage ? 'courses/form.php?id=' . $id : ''),
]);

render_readonly_notice('courses.manage', 'course');

$enrollmentItems = [];
foreach ($enrollments as $enrollment) {
    $marks = $enrollment['average_marks'];
    $enrollmentItems[] = [
        'label' => full_name($enrollment['first_name'], $enrollment['last_name']),
        'meta'  => $enrollment['student_no'] . ' · ' . $enrollment['academic_year'] . ' · '
            . $enrollment['semester']
            . ($marks !== null ? ' · avg ' . format_marks($marks) : ''),
        'href'  => url('enrollments/view.php?id=' . (int) $enrollment['id']),
        'trail' => badge($enrollment['status']),
    ];
}
?>

<section class="recordhead">
    <span class="recordhead__mark"><?= icon('course', 26) ?></span>
    <div class="recordhead__text">
        <h2 class="recordhead__title"><?= e($course['course_name']) ?></h2>
        <p class="recordhead__meta">
            <span class="table__id"><?= e($course['course_code']) ?></span>
            <span>
                <a href="<?= e(url('departments/view.php?id=' . (int) $course['department_id'])) ?>"><?= e($course['department_name']) ?></a>
            </span>
            <span><?= e((string) $course['credit_hours']) ?> credits</span>
            <span><?= e($course['semester']) ?></span>
            <?php if ($course['lecturer_id'] !== null): ?>
                <span>Taught by <a href="<?= e(url('lecturers/view.php?id=' . (int) $course['lecturer_id'])) ?>"><?= e($course['lecturer_name']) ?></a></span>
            <?php else: ?>
                <span class="text-3">No lecturer assigned</span>
            <?php endif; ?>
        </p>
    </div>
</section>

<div class="split split--wide mb-3">

    <div class="stackgap">

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Enrolled students</h2>
                    <p class="panel__sub">
                        <?php if ($enrollmentCount > count($enrollments)): ?>
                            The first <?= e((string) count($enrollments)) ?> of <?= e(format_number($enrollmentCount)) ?>
                        <?php else: ?>
                            <?= e(format_number($enrollmentCount)) ?> <?= $enrollmentCount === 1 ? 'registration' : 'registrations' ?> in the record
                        <?php endif; ?>
                    </p>
                </div>
                <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('enrollments/index.php?course=' . $id)) ?>">
                    See all
                </a>
            </div>
            <div class="panel__body">
                <?php render_related_list(
                    $enrollmentItems,
                    'No students have been enrolled to this course yet.',
                    'student'
                ); ?>
            </div>
        </section>

        <?php if ($course['lecturer_id'] !== null): ?>
            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title">Lecturer</h2>
                        <p class="panel__sub">The member of staff assigned to teach this course</p>
                    </div>
                </div>
                <div class="panel__body">
                    <?php render_deflist([
                        ['term' => 'Name', 'desc' => '<a class="strong" href="'
                            . e(url('lecturers/view.php?id=' . (int) $course['lecturer_id'])) . '">'
                            . e($course['lecturer_name']) . '</a>'],
                        ['term' => 'Reference', 'desc' => '<span class="table__id">' . e($course['lecturer_staff_no']) . '</span>'],
                    ]); ?>
                </div>
            </section>
        <?php endif; ?>

    </div>

    <div class="stackgap">

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Record</h2>
                </div>
            </div>
            <div class="panel__body">
                <?php render_deflist([
                    ['term' => 'Code', 'desc' => '<span class="table__id">' . e($course['course_code']) . '</span>'],
                    ['term' => 'Name', 'desc' => e($course['course_name'])],
                    ['term' => 'Department', 'desc' => '<a href="' . e(url('departments/view.php?id=' . (int) $course['department_id'])) . '">'
                        . e($course['department_name']) . '</a>'],
                    ['term' => 'Faculty', 'desc' => '<a href="' . e(url('faculties/view.php?id=' . (int) $course['faculty_id'])) . '">'
                        . e($course['faculty_name']) . '</a>'],
                    ['term' => 'Semester', 'desc' => e($course['semester'])],
                    ['term' => 'Credit hours', 'desc' => e((string) $course['credit_hours'])],
                    ['term' => 'Enrolled', 'desc' => $enrollmentCount > 0
                        ? '<a href="' . e(url('enrollments/index.php?course=' . $id)) . '">'
                            . e(format_number($enrollmentCount)) . '</a>'
                        : '<span class="text-3">None yet</span>'],
                ]); ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Notes</h2>
                </div>
            </div>
            <div class="panel__body">
                <?php if (($course['description'] ?? '') === ''): ?>
                    <p class="text-3 text-sm">No description has been recorded for this course.</p>
                <?php else: ?>
                    <div class="prose"><?= nl2br(e($course['description'])) ?></div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (can('enrollments.manage')): ?>
            <section class="panel">
                <div class="panel__body">
                    <a class="btn btn--default" href="<?= e(url('enrollments/form.php?course=' . $id)) ?>">
                        <?= icon('plus', 15) ?><span>Enroll a student</span>
                    </a>
                </div>
            </section>
        <?php endif; ?>

    </div>
</div>

<?php layout_end(); ?>