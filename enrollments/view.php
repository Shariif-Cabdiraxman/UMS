<?php
/**
 * Enrollments — a single record.
 *
 * The detail page of a registration: the student and course at either end of
 * it, the term it belongs to, and the grades recorded against it.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'enrollments/index.php';
$id   = query_int('id');

if ($id < 1) {
    not_found('No enrollment was requested. Open an enrollment from the list to see its record.');
}

$enrollment = db_row(
    'SELECT e.*,
            s.`student_no`, s.`first_name`, s.`last_name`, s.`status` AS `student_status`,
            d.`id` AS `department_id`, d.`name` AS `department_name`,
            c.`course_code`, c.`course_name`, c.`credit_hours`, c.`semester` AS `course_semester`,
            c.`lecturer_id`,
            CONCAT(l.`first_name`, \' \', l.`last_name`) AS `lecturer_name`
       FROM `enrollments` e
       JOIN `students` s    ON s.`id` = e.`student_id`
       JOIN `courses` c     ON c.`id` = e.`course_id`
       JOIN `departments` d ON d.`id` = c.`department_id`
       LEFT JOIN `lecturers` l ON l.`id` = c.`lecturer_id`
      WHERE e.`id` = ?
      LIMIT 1',
    'i',
    [$id]
);

if ($enrollment === null) {
    not_found('That enrollment does not exist. It may have been deleted.');
}

$grades = db_all(
    'SELECT `id`, `assessment`, `marks`, `letter_grade`, `created_at`, `updated_at`
       FROM `grades`
      WHERE `enrollment_id` = ?
      ORDER BY FIELD(`assessment`, \'Midterm Examination\', \'Final Examination\',
                               \'Continuous Assessment\', \'Practical\', \'Project\'), `id`',
    'i',
    [$id]
);

$summary = db_row(
    'SELECT COUNT(*)               AS `grade_count`,
            ROUND(AVG(`marks`), 2) AS `average`,
            MAX(`marks`)           AS `highest`,
            MIN(`marks`)           AS `lowest`
       FROM `grades`
      WHERE `enrollment_id` = ?',
    'i',
    [$id]
);

$canManage = can('enrollments.manage');
$canGrade  = can('grades.manage');

$studentName = full_name($enrollment['first_name'], $enrollment['last_name']);

layout_start('Enrollment', 'enrollments');

render_page_head([
    'eyebrow'  => $enrollment['academic_year'] . ' · ' . $enrollment['semester'],
    'title'    => $enrollment['course_name'],
    'subtitle' => 'Enrollment record #' . $id,
    'meta'     => 'Record created ' . e(format_datetime($enrollment['created_at']))
        . ' &middot; last changed ' . e(format_datetime($enrollment['updated_at'])),
    'actions'  => record_actions($path, $canManage ? 'enrollments/form.php?id=' . $id : ''),
]);

render_readonly_notice('enrollments.manage', 'enrollment');

$gradeItems = [];
foreach ($grades as $grade) {
    $gradeItems[] = [
        'label' => $grade['assessment'],
        'meta'  => 'Marked ' . e(format_marks($grade['marks'])) . ' · recorded '
            . e(format_date($grade['updated_at'])),
        'href'  => url('grades/view.php?id=' . (int) $grade['id']),
        'trail' => grade_badge($grade['letter_grade']),
    ];
}
?>

<section class="recordhead">
    <span class="recordhead__mark"><?= icon('enrollment', 26) ?></span>
    <div class="recordhead__text">
        <h2 class="recordhead__title"><?= e($studentName) ?></h2>
        <p class="recordhead__meta">
            <span class="table__id"><?= e($enrollment['course_code']) ?></span>
            <span><a href="<?= e(url('courses/view.php?id=' . (int) $enrollment['course_id'])) ?>"><?= e($enrollment['course_name']) ?></a></span>
            <span><?= e($enrollment['academic_year']) ?> · <?= e($enrollment['semester']) ?></span>
            <span><?= badge($enrollment['status']) ?></span>
        </p>
    </div>
</section>

<div class="split split--wide mb-3">

    <div class="stackgap">

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Grades</h2>
                    <p class="panel__sub">
                        <?= (int) ($summary['grade_count'] ?? 0) === 0
                            ? 'No marks recorded against this registration yet'
                            : e(format_number((int) $summary['grade_count'])) . ' mark'
                                . ((int) $summary['grade_count'] === 1 ? '' : 's') . ' recorded' ?>
                    </p>
                </div>
                <?php if ($canGrade): ?>
                    <a class="panel__action btn btn--default btn--sm" href="<?= e(url('grades/form.php?enrollment=' . $id)) ?>">
                        <?= icon('plus', 14) ?><span>Add grade</span>
                    </a>
                <?php endif; ?>
            </div>
            <div class="panel__body">
                <?php render_related_list(
                    $gradeItems,
                    'Add an assessment to record the first mark for this enrollment.',
                    'grades'
                ); ?>
            </div>
        </section>

        <?php if ((int) ($summary['grade_count'] ?? 0) > 0): ?>
            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title">Summary</h2>
                        <p class="panel__sub">Across the marks recorded on this registration</p>
                    </div>
                </div>
                <div class="panel__body">
                    <?php render_deflist([
                        ['term' => 'Marks recorded', 'desc' => e(format_number((int) $summary['grade_count']))],
                        ['term' => 'Average', 'desc' => e(format_marks($summary['average']))],
                        ['term' => 'Highest', 'desc' => e(format_marks($summary['highest']))],
                        ['term' => 'Lowest', 'desc' => e(format_marks($summary['lowest']))],
                    ]); ?>
                </div>
            </section>
        <?php endif; ?>

    </div>

    <div class="stackgap">

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Student</h2>
                </div>
            </div>
            <div class="panel__body">
                <?php render_deflist([
                    ['term' => 'Name', 'desc' => '<a class="strong" href="'
                        . e(url('students/view.php?id=' . urlencode((string) $enrollment['student_no']))) . '">'
                        . e($studentName) . '</a>'],
                    ['term' => 'Reference', 'desc' => '<span class="table__id">' . e($enrollment['student_no']) . '</span>'],
                    ['term' => 'Department', 'desc' => '<a href="'
                        . e(url('departments/view.php?id=' . (int) $enrollment['department_id'])) . '">'
                        . e($enrollment['department_name']) . '</a>'],
                    ['term' => 'Status', 'desc' => badge($enrollment['student_status'])],
                ]); ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Course</h2>
                </div>
            </div>
            <div class="panel__body">
                <?php render_deflist([
                    ['term' => 'Code', 'desc' => '<a class="strong" href="'
                        . e(url('courses/view.php?id=' . (int) $enrollment['course_id'])) . '">'
                        . e($enrollment['course_code']) . '</a>'],
                    ['term' => 'Name', 'desc' => e($enrollment['course_name'])],
                    ['term' => 'Credit hours', 'desc' => e((string) $enrollment['credit_hours'])],
                    ['term' => 'Semester', 'desc' => e($enrollment['course_semester'])],
                    ['term' => 'Lecturer', 'desc' => $enrollment['lecturer_id'] !== null
                        ? '<a class="table__name" href="' . e(url('lecturers/view.php?id=' . (int) $enrollment['lecturer_id'])) . '">'
                            . e($enrollment['lecturer_name']) . '</a>'
                        : '<span class="text-3">Not assigned</span>'],
                ]); ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Record</h2>
                </div>
            </div>
            <div class="panel__body">
                <?php render_deflist([
                    ['term' => 'Academic year', 'desc' => e($enrollment['academic_year'])],
                    ['term' => 'Semester', 'desc' => e($enrollment['semester'])],
                    ['term' => 'Enrollment date', 'desc' => e(format_db_date($enrollment['enrollment_date']))],
                    ['term' => 'Status', 'desc' => badge($enrollment['status'])],
                ]); ?>
            </div>
        </section>

    </div>
</div>

<?php layout_end(); ?>