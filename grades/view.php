<?php
/**
 * Grades — a single record.
 *
 * The detail page of one mark: the enrollment and therefore the student,
 * course and term it belongs to, plus the assessment, the marks as entered
 * and the letter grade derived from them.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'grades/index.php';
$id   = query_int('id');

if ($id < 1) {
    not_found('No grade was requested. Open a grade from the list to see its record.');
}

$grade = db_row(
    'SELECT g.*,
            e.`id` AS `enrollment_id`, e.`course_id`, e.`academic_year`, e.`semester`, e.`status` AS `enrollment_status`,
            s.`student_no`, s.`first_name`, s.`last_name`,
            c.`course_code`, c.`course_name`, c.`credit_hours`
       FROM `grades` g
       JOIN `enrollments` e ON e.`id` = g.`enrollment_id`
       JOIN `students` s   ON s.`id` = e.`student_id`
       JOIN `courses` c    ON c.`id` = e.`course_id`
      WHERE g.`id` = ?
      LIMIT 1',
    'i',
    [$id]
);

if ($grade === null) {
    not_found('That grade does not exist. It may have been deleted.');
}

$siblings = db_all(
    'SELECT `id`, `assessment`, `marks`, `letter_grade`, `updated_at`
       FROM `grades`
      WHERE `enrollment_id` = ? AND `id` <> ?
      ORDER BY FIELD(`assessment`, \'Midterm Examination\', \'Final Examination\',
                               \'Continuous Assessment\', \'Practical\', \'Project\'), `id`',
    'ii',
    [(int) $grade['enrollment_id'], $id]
);

$canManage = can('grades.manage');
$studentName = full_name($grade['first_name'], $grade['last_name']);

$siblingItems = [];
foreach ($siblings as $sibling) {
    $siblingItems[] = [
        'label' => $sibling['assessment'],
        'meta'  => 'Marked ' . e(format_marks($sibling['marks'])) . ' · recorded '
            . e(format_date($sibling['updated_at'])),
        'href'  => url('grades/view.php?id=' . (int) $sibling['id']),
        'trail' => grade_badge($sibling['letter_grade']),
    ];
}

layout_start('Grade', 'grades');

render_page_head([
    'eyebrow'  => $grade['academic_year'] . ' · ' . $grade['semester'],
    'title'    => $grade['assessment'],
    'subtitle' => 'Marks recorded against an enrollment',
    'meta'     => 'Recorded ' . e(format_datetime($grade['created_at']))
        . ' &middot; last changed ' . e(format_datetime($grade['updated_at'])),
    'actions'  => record_actions($path, $canManage ? 'grades/form.php?id=' . $id : ''),
]);

render_readonly_notice('grades.manage', 'grade');

$term = e($grade['academic_year']) . ' · ' . e($grade['semester']);
?>

<section class="recordhead">
    <span class="recordhead__mark"><?= icon('grades', 26) ?></span>
    <div class="recordhead__text">
        <h2 class="recordhead__title"><?= e($studentName) ?></h2>
        <p class="recordhead__meta">
            <span class="table__id"><?= e($grade['course_code']) ?></span>
            <span><?= e($grade['course_name']) ?></span>
            <span><?= e($grade['assessment']) ?></span>
            <?= grade_badge($grade['letter_grade']) ?>
        </p>
    </div>
</section>

<div class="split split--wide mb-3">

    <div class="stackgap">

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Mark</h2>
                    <p class="panel__sub">The assessment result that was recorded</p>
                </div>
            </div>
            <div class="panel__body">
                <?php render_deflist([
                    ['term' => 'Marks', 'desc' => '<span class="marks">' . e(format_marks($grade['marks'])) . '</span>'],
                    ['term' => 'Letter grade', 'desc' => grade_badge($grade['letter_grade'])],
                ]); ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Audit</h2>
                    <p class="panel__sub">When this mark entered the ledger and last changed</p>
                </div>
            </div>
            <div class="panel__body">
                <?php render_deflist([
                    ['term' => 'Recorded at', 'desc' => e(format_datetime($grade['created_at']))],
                    ['term' => 'Last changed', 'desc' => e(format_datetime($grade['updated_at']))],
                ]); ?>
            </div>
        </section>

    </div>

    <div class="stackgap">

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Enrollment</h2>
                    <p class="panel__sub">The registration this mark was recorded against</p>
                </div>
            </div>
            <div class="panel__body">
                <?php render_deflist([
                    ['term' => 'Student', 'desc' => '<a class="strong" href="'
                        . e(url('students/view.php?id=' . urlencode((string) $grade['student_no']))) . '">'
                        . e($studentName) . '</a>'],
                    ['term' => 'Reference', 'desc' => '<span class="table__id">' . e($grade['student_no']) . '</span>'],
                    ['term' => 'Course', 'desc' => '<a class="table__name" href="'
                        . e(url('courses/view.php?id=' . (int) $grade['course_id'])) . '">'
                        . e($grade['course_code']) . ' — ' . e(truncate((string) $grade['course_name'], 46)) . '</a>'],
                    ['term' => 'Term', 'desc' => e($term)],
                    ['term' => 'Enrollment status', 'desc' => badge($grade['enrollment_status'])],
                    ['term' => 'Full record', 'desc' => '<a class="table__name" href="'
                        . e(url('enrollments/view.php?id=' . (int) $grade['enrollment_id'])) . '">'
                        . 'Open the enrollment — see every mark</a>'],
                ]); ?>
            </div>
        </section>

        <?php if ($siblingItems !== []): ?>
            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title">Other marks for this enrollment</h2>
                        <p class="panel__sub"><?= e(format_number(count($siblingItems))) ?> more assessment<?= count($siblingItems) === 1 ? '' : 's' ?></p>
                    </div>
                </div>
                <div class="panel__body">
                    <?php render_related_list($siblingItems, '', 'grades'); ?>
                </div>
            </section>
        <?php endif; ?>

    </div>
</div>

<?php layout_end(); ?>