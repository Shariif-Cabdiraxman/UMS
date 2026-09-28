<?php
/**
 * Lecturers — a single record.
 *
 * Follows the shape set by the faculty and department details: a masthead,
 * the fields of the record, then what hangs off it — the courses taught and,
 * for a dean or head, the leadership roles they hold.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'lecturers/index.php';
$id   = query_int('id');

if ($id < 1) {
    not_found('No lecturer was requested. Open a lecturer from the list to see their record.');
}

$lecturer = db_row(
    'SELECT l.*,
            d.`name` AS `department_name`,
            f.`id`   AS `faculty_id`,
            f.`name` AS `faculty_name`
       FROM `lecturers` l
       JOIN `departments` d ON d.`id` = l.`department_id`
       JOIN `faculties` f   ON f.`id` = d.`faculty_id`
      WHERE l.`id` = ?
      LIMIT 1',
    'i',
    [$id]
);

if ($lecturer === null) {
    not_found('That lecturer does not exist. They may have been deleted.');
}

$courses = db_all(
    'SELECT `id`, `course_code`, `course_name`, `semester`, `credit_hours`
       FROM `courses`
      WHERE `lecturer_id` = ?
      ORDER BY `semester`, `course_code`',
    'i',
    [$id]
);

$deanships = db_all(
    'SELECT `id`, `code`, `name`
       FROM `faculties`
      WHERE `dean_id` = ?
      ORDER BY `name`',
    'i',
    [$id]
);

$headships = db_all(
    'SELECT `id`, `code`, `name`
       FROM `departments`
      WHERE `head_id` = ?
      ORDER BY `name`',
    'i',
    [$id]
);

$courseCount = (int) db_value('SELECT COUNT(*) FROM `courses` WHERE `lecturer_id` = ?', 'i', [$id]);

$canManage = can('lecturers.manage');

$name = full_name($lecturer['first_name'], $lecturer['last_name']);

layout_start($name, 'lecturers');

render_page_head([
    'eyebrow'  => 'Academic staff',
    'title'    => $name,
    'subtitle' => 'Staff record ' . $lecturer['staff_no'],
    'meta'     => 'Record created ' . e(format_datetime($lecturer['created_at']))
        . ' &middot; last changed ' . e(format_datetime($lecturer['updated_at'])),
    'actions'  => record_actions($path, $canManage ? 'lecturers/form.php?id=' . $id : ''),
]);

render_readonly_notice('lecturers.manage', 'lecturer');

$courseItems = [];
foreach ($courses as $course) {
    $courseItems[] = [
        'label' => $course['course_name'],
        'meta'  => $course['course_code'] . ' · ' . $course['credit_hours'] . ' credits · '
            . $course['semester'],
        'href'  => url('courses/view.php?id=' . (int) $course['id']),
    ];
}

$leadershipItems = [];
foreach ($deanships as $faculty) {
    $leadershipItems[] = [
        'label' => $faculty['name'],
        'meta'  => 'Dean · ' . $faculty['code'],
        'href'  => url('faculties/view.php?id=' . (int) $faculty['id']),
        'trail' => badge('active', 'Dean'),
    ];
}
foreach ($headships as $department) {
    $leadershipItems[] = [
        'label' => $department['name'],
        'meta'  => 'Head of department · ' . $department['code'],
        'href'  => url('departments/view.php?id=' . (int) $department['id']),
        'trail' => badge('active', 'Head'),
    ];
}
?>

<section class="recordhead">
    <span class="recordhead__mark"><?= icon('lecturer', 26) ?></span>
    <div class="recordhead__text">
        <h2 class="recordhead__title"><?= e($name) ?></h2>
        <p class="recordhead__meta">
            <span class="table__id"><?= e($lecturer['staff_no']) ?></span>
            <span>
                <a href="<?= e(url('departments/view.php?id=' . (int) $lecturer['department_id'])) ?>"><?= e($lecturer['department_name']) ?></a>
                · <a href="<?= e(url('faculties/view.php?id=' . (int) $lecturer['faculty_id'])) ?>"><?= e($lecturer['faculty_name']) ?></a>
            </span>
            <span><?= badge($lecturer['status']) ?></span>
        </p>
    </div>
</section>

<div class="split split--wide mb-3">

    <div class="stackgap">

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Courses taught</h2>
                    <p class="panel__sub">
                        <?= $courseCount > 0 ? 'The course' . ($courseCount === 1 ? '' : 's') . ' currently assigned to this lecturer' : 'Nothing assigned yet' ?>
                    </p>
                </div>
                <?php if (can('courses.manage')): ?>
                    <a class="panel__action btn btn--default btn--sm" href="<?= e(url('courses/form.php?lecturer=' . $id)) ?>">
                        <?= icon('plus', 14) ?><span>Assign a course</span>
                    </a>
                <?php endif; ?>
            </div>
            <div class="panel__body">
                <?php render_related_list(
                    $courseItems,
                    'No courses have been assigned to this lecturer yet.',
                    'course'
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
                    ['term' => 'Reference', 'desc' => '<span class="table__id">' . e($lecturer['staff_no']) . '</span>'],
                    ['term' => 'Name', 'desc' => e($name)],
                    ['term' => 'Department', 'desc' => '<a href="' . e(url('departments/view.php?id=' . (int) $lecturer['department_id'])) . '">'
                        . e($lecturer['department_name']) . '</a>'],
                    ['term' => 'Specialism', 'desc' => $lecturer['specialization'] !== null
                        ? e($lecturer['specialization']) : '<span class="text-3">Not recorded</span>'],
                    ['term' => 'Status', 'desc' => badge($lecturer['status'])],
                    ['term' => 'Courses', 'desc' => $courseCount > 0
                        ? e((string) $courseCount) : '<span class="text-3">None</span>'],
                ]); ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Contact</h2>
                </div>
            </div>
            <div class="panel__body">
                <?php render_deflist([
                    ['term' => 'Email', 'desc' => '<a class="strong" href="mailto:' . e($lecturer['email']) . '">'
                        . e($lecturer['email']) . '</a>'],
                    ['term' => 'Phone', 'desc' => $lecturer['phone'] !== null
                        ? e($lecturer['phone']) : '<span class="text-3">Not recorded</span>'],
                ]); ?>
            </div>
        </section>

        <?php if ($leadershipItems !== []): ?>
            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title">Leadership roles</h2>
                        <p class="panel__sub">Posts this lecturer currently holds</p>
                    </div>
                </div>
                <div class="panel__body">
                    <?php render_related_list($leadershipItems, '', 'award'); ?>
                </div>
            </section>
        <?php endif; ?>

    </div>
</div>

<?php layout_end(); ?>