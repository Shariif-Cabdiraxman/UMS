<?php
/**
 * Departments — a single record.
 *
 * Follows the shape set by the faculty detail: a masthead, the fields of the
 * record, then the courses, lecturers and students hanging off it.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'departments/index.php';
$id   = query_int('id');

if ($id < 1) {
    not_found('No department was requested. Open a department from the list to see its record.');
}

$department = db_row(
    'SELECT d.*,
            f.`name`  AS `faculty_name`,
            f.`code`  AS `faculty_code`,
            CONCAT(h.`first_name`, \' \', h.`last_name`) AS `head_name`,
            h.`staff_no` AS `head_staff_no`,
            h.`email`    AS `head_email`,
            h.`specialization` AS `head_specialization`
       FROM `departments` d
       JOIN `faculties` f        ON f.`id` = d.`faculty_id`
       LEFT JOIN `lecturers` h   ON h.`id` = d.`head_id`
      WHERE d.`id` = ?
      LIMIT 1',
    'i',
    [$id]
);

if ($department === null) {
    not_found('That department does not exist. It may have been deleted.');
}

$courses = db_all(
    'SELECT `id`, `course_code`, `course_name`, `credit_hours`, `semester`
       FROM `courses`
      WHERE `department_id` = ?
      ORDER BY `course_code`',
    'i',
    [$id]
);

$lecturers = db_all(
    'SELECT `id`, `staff_no`, `first_name`, `last_name`, `specialization`, `status`
       FROM `lecturers`
      WHERE `department_id` = ?
      ORDER BY `last_name`, `first_name`',
    'i',
    [$id]
);

$students = db_all(
    'SELECT `id`, `student_no`, `first_name`, `last_name`, `enrollment_year`, `status`
       FROM `students`
      WHERE `department_id` = ?
      ORDER BY `student_no`
      LIMIT 12',
    'i',
    [$id]
);

$studentCount = (int) db_value('SELECT COUNT(*) FROM `students` WHERE `department_id` = ?', 'i', [$id]);
$courseCount  = (int) db_value('SELECT COUNT(*) FROM `courses` WHERE `department_id` = ?', 'i', [$id]);
$lecturerCount = (int) db_value('SELECT COUNT(*) FROM `lecturers` WHERE `department_id` = ?', 'i', [$id]);

$canManage = can('departments.manage');

layout_start($department['name'], 'departments');

render_page_head([
    'eyebrow'  => $department['faculty_name'],
    'title'    => $department['name'],
    'subtitle' => 'Department record ' . $department['code'],
    'meta'     => 'Record created ' . e(format_datetime($department['created_at']))
        . ' &middot; last changed ' . e(format_datetime($department['updated_at'])),
    'actions'  => record_actions($path, $canManage ? 'departments/form.php?id=' . $id : ''),
]);

render_readonly_notice('departments.manage', 'department');

$courseItems = [];
foreach ($courses as $course) {
    $courseItems[] = [
        'label' => $course['course_name'],
        'meta'  => $course['course_code'] . ' · ' . $course['credit_hours'] . ' credits · '
            . $course['semester'],
        'href'  => url('courses/view.php?id=' . (int) $course['id']),
    ];
}

$lecturerItems = [];
foreach ($lecturers as $lecturer) {
    $lecturerItems[] = [
        'label' => full_name($lecturer['first_name'], $lecturer['last_name']),
        'meta'  => $lecturer['staff_no'] . ($lecturer['specialization'] !== null
            ? ' · ' . $lecturer['specialization'] : ''),
        'href'  => url('lecturers/view.php?id=' . (int) $lecturer['id']),
        'trail' => badge($lecturer['status']),
    ];
}

$studentItems = [];
foreach ($students as $student) {
    $studentItems[] = [
        'label' => full_name($student['first_name'], $student['last_name']),
        'meta'  => $student['student_no'] . ' · ' . $student['enrollment_year'],
        'href'  => url('students/view.php?id=' . (int) $student['id']),
        'trail' => badge($student['status']),
    ];
}
?>

<section class="recordhead">
    <span class="recordhead__mark"><?= icon('department', 26) ?></span>
    <div class="recordhead__text">
        <h2 class="recordhead__title"><?= e($department['name']) ?></h2>
        <p class="recordhead__meta">
            <span class="table__id"><?= e($department['code']) ?></span>
            <span>
                <a href="<?= e(url('faculties/view.php?id=' . (int) $department['faculty_id'])) ?>"><?= e($department['faculty_name']) ?></a>
            </span>
            <?php if ($department['head_name'] !== null): ?>
                <span>Head: <a href="<?= e(url('lecturers/view.php?id=' . (int) $department['head_id'])) ?>"><?= e($department['head_name']) ?></a></span>
            <?php else: ?>
                <span class="text-3">No head appointed</span>
            <?php endif; ?>
        </p>
    </div>
</section>

<div class="split split--wide mb-3">

    <div class="stackgap">

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Courses</h2>
                    <p class="panel__sub">The programmes this department offers</p>
                </div>
                <?php if ($canManage): ?>
                    <a class="panel__action btn btn--default btn--sm" href="<?= e(url('courses/form.php?department=' . $id)) ?>">
                        <?= icon('plus', 14) ?><span>Add course</span>
                    </a>
                <?php endif; ?>
            </div>
            <div class="panel__body">
                <?php render_related_list(
                    $courseItems,
                    'No courses have been added to this department yet.',
                    'course'
                ); ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Lecturers</h2>
                    <p class="panel__sub">Staff employed by this department</p>
                </div>
                <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('lecturers/index.php?department=' . $id)) ?>">
                    See all
                </a>
            </div>
            <div class="panel__body">
                <?php render_related_list(
                    $lecturerItems,
                    'No lecturers are attached to this department yet.',
                    'lecturer'
                ); ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Students</h2>
                    <p class="panel__sub">
                        <?php if ($studentCount > count($students)): ?>
                            The first <?= e((string) count($students)) ?> of <?= e(format_number($studentCount)) ?>
                        <?php else: ?>
                            Everyone registered to this department
                        <?php endif; ?>
                    </p>
                </div>
                <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('students/index.php?department=' . $id)) ?>">
                    See all
                </a>
            </div>
            <div class="panel__body">
                <?php render_related_list(
                    $studentItems,
                    'No students are registered to this department yet.',
                    'student'
                ); ?>
            </div>
        </section>

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
                    ['term' => 'Code', 'desc' => '<span class="table__id">' . e($department['code']) . '</span>'],
                    ['term' => 'Name', 'desc' => e($department['name'])],
                    ['term' => 'Faculty', 'desc' => '<a href="' . e(url('faculties/view.php?id=' . (int) $department['faculty_id'])) . '">'
                        . e($department['faculty_name']) . '</a>'],
                    ['term' => 'Head', 'desc' => $department['head_name'] !== null
                        ? '<a class="strong" href="' . e(url('lecturers/view.php?id=' . (int) $department['head_id'])) . '">'
                            . e($department['head_name']) . '</a>'
                        : '<span class="text-3">Not appointed</span>'],
                    ['term' => 'Courses', 'desc' => $courseCount > 0
                        ? '<a href="' . e(url('courses/index.php?department=' . $id)) . '">'
                            . e((string) $courseCount) . '</a>'
                        : '<span class="text-3">0</span>'],
                    ['term' => 'Lecturers', 'desc' => $lecturerCount > 0
                        ? '<a href="' . e(url('lecturers/index.php?department=' . $id)) . '">'
                            . e((string) $lecturerCount) . '</a>'
                        : '<span class="text-3">0</span>'],
                    ['term' => 'Students', 'desc' => $studentCount > 0
                        ? '<a href="' . e(url('students/index.php?department=' . $id)) . '">'
                            . e(format_number($studentCount)) . '</a>'
                        : '<span class="text-3">0</span>'],
                ]); ?>
            </div>
        </section>

        <?php if ($department['head_name'] !== null): ?>
            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title">Head of department</h2>
                        <p class="panel__sub">The academic lead of this department</p>
                    </div>
                </div>
                <div class="panel__body">
                    <?php render_deflist([
                        ['term' => 'Name', 'desc' => '<a class="strong" href="'
                            . e(url('lecturers/view.php?id=' . (int) $department['head_id'])) . '">'
                            . e($department['head_name']) . '</a>'],
                        ['term' => 'Reference', 'desc' => '<span class="table__id">' . e((string) $department['head_staff_no']) . '</span>'],
                        ['term' => 'Specialism', 'desc' => e((string) ($department['head_specialization'] ?? ''))],
                        ['term' => 'Email', 'desc' => e((string) $department['head_email'])],
                    ]); ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Notes</h2>
                </div>
            </div>
            <div class="panel__body">
                <?php if (($department['description'] ?? '') === ''): ?>
                    <p class="text-3 text-sm">No description has been recorded for this department.</p>
                <?php else: ?>
                    <div class="prose"><?= nl2br(e($department['description'])) ?></div>
                <?php endif; ?>
            </div>
        </section>

    </div>
</div>

<?php layout_end(); ?>
