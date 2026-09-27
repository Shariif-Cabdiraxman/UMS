<?php
/**
 * Faculties — a single record.
 *
 * Detail pages in this application follow the same shape: a masthead, the
 * fields of the record, then whatever child records hang off it. The faculty
 * detail is the first of them, and the pattern is set here for the rest.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'faculties/index.php';
$id   = query_int('id');

if ($id < 1) {
    not_found('No faculty was requested. Open a faculty from the list to see its record.');
}

$faculty = db_row(
    'SELECT f.*,
            CONCAT(l.`first_name`, \' \', l.`last_name`) AS `dean_name`,
            l.`staff_no`  AS `dean_staff_no`,
            l.`email`     AS `dean_email`
       FROM `faculties` f
       LEFT JOIN `lecturers` l ON l.`id` = f.`dean_id`
      WHERE f.`id` = ?
      LIMIT 1',
    'i',
    [$id]
);

if ($faculty === null) {
    not_found('That faculty does not exist. It may have been deleted.');
}

$departments = db_all(
    'SELECT `id`, `code`, `name`,
            (SELECT COUNT(*) FROM `students` s WHERE s.`department_id` = d.`id`) AS `student_count`,
            (SELECT COUNT(*) FROM `courses`  c WHERE c.`department_id` = d.`id`) AS `course_count`
       FROM `departments` d
      WHERE d.`faculty_id` = ?
      ORDER BY d.`name`',
    'i',
    [$id]
);

$lecturers = db_all(
    'SELECT `id`, `staff_no`, `first_name`, `last_name`, `specialization`, `status`
       FROM `lecturers`
      WHERE `department_id` IN (SELECT `id` FROM `departments` WHERE `faculty_id` = ?)
      ORDER BY `last_name`, `first_name`',
    'i',
    [$id]
);

$studentCount = (int) db_value('SELECT COUNT(*) FROM `students` WHERE `faculty_id` = ?', 'i', [$id]);
$courseCount  = (int) db_value(
    'SELECT COUNT(*) FROM `courses` WHERE `department_id` IN (SELECT `id` FROM `departments` WHERE `faculty_id` = ?)',
    'i',
    [$id]
);

$canManage = can('faculties.manage');

layout_start($faculty['name'], 'faculties');

render_page_head([
    'eyebrow'  => 'Academic structure',
    'title'    => $faculty['name'],
    'subtitle' => 'Faculty record ' . $faculty['code'],
    'meta'     => 'Record created ' . e(format_datetime($faculty['created_at']))
        . ' &middot; last changed ' . e(format_datetime($faculty['updated_at'])),
    'actions'  => record_actions($path, $canManage ? 'faculties/form.php?id=' . $id : ''),
]);

render_readonly_notice('faculty', 'faculty');

/**
 * A short link-list of records, used for the child tables on detail pages.
 *
 * @param array<int,array{label:string,meta:string,href:string,trail?:string}> $items
 */
function render_related_list(array $items, string $emptyMessage, string $iconName = 'document'): void
{
    if ($items === []) {
        render_empty_state($iconName, 'Nothing linked yet', $emptyMessage);

        return;
    }
    ?>
    <ul class="relatedlist">
        <?php foreach ($items as $item): ?>
            <li class="relatedlist__item">
                <a class="relatedlist__link" href="<?= e($item['href']) ?>">
                    <span class="relatedlist__label"><?= e($item['label']) ?></span>
                    <?php if (!empty($item['meta'])): ?>
                        <span class="relatedlist__meta"><?= e($item['meta']) ?></span>
                    <?php endif; ?>
                </a>
                <?php if (!empty($item['trail'])): ?>
                    <span class="relatedlist__trail"><?= $item['trail'] ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}

$departmentItems = [];
foreach ($departments as $department) {
    $departmentItems[] = [
        'label' => $department['name'],
        'meta'  => $department['code'] . ' · ' . $department['student_count'] . ' students · '
            . $department['course_count'] . ' courses',
        'href'  => url('departments/view.php?id=' . (int) $department['id']),
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
?>

<section class="recordhead">
    <span class="recordhead__mark"><?= icon('faculty', 26) ?></span>
    <div class="recordhead__text">
        <h2 class="recordhead__title"><?= e($faculty['name']) ?></h2>
        <p class="recordhead__meta">
            <span class="table__id"><?= e($faculty['code']) ?></span>
            <?php if ($faculty['dean_name'] !== null): ?>
                <span>Dean: <a href="<?= e(url('lecturers/view.php?id=' . (int) $faculty['dean_id'])) ?>"><?= e($faculty['dean_name']) ?></a></span>
            <?php else: ?>
                <span class="text-3">No dean assigned</span>
            <?php endif; ?>
        </p>
    </div>
</section>

<div class="split split--wide mb-3">

    <div class="stackgap">

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Departments</h2>
                    <p class="panel__sub">The academic departments that sit inside this faculty</p>
                </div>
                <?php if ($canManage): ?>
                    <a class="panel__action btn btn--default btn--sm" href="<?= e(url('departments/form.php?faculty=' . $id)) ?>">
                        <?= icon('plus', 14) ?><span>Add department</span>
                    </a>
                <?php endif; ?>
            </div>
            <div class="panel__body">
                <?php render_related_list(
                    $departmentItems,
                    'No departments have been added to this faculty yet.',
                    'department'
                ); ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Lecturers</h2>
                    <p class="panel__sub">Staff attached to the departments of this faculty</p>
                </div>
                <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('lecturers/index.php?faculty=' . $id)) ?>">
                    See all
                </a>
            </div>
            <div class="panel__body">
                <?php render_related_list(
                    $lecturerItems,
                    'No lecturers are attached to the departments of this faculty yet.',
                    'lecturer'
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
                    ['term' => 'Code', 'desc' => '<span class="table__id">' . e($faculty['code']) . '</span>'],
                    ['term' => 'Name', 'desc' => e($faculty['name'])],
                    ['term' => 'Departments', 'desc' => e((string) count($departments))],
                    ['term' => 'Lecturers', 'desc' => e((string) count($lecturers))],
                    ['term' => 'Students', 'desc' => '<a href="' . e(url('students/index.php?faculty=' . $id)) . '">'
                        . e(format_number($studentCount)) . ' students</a>'],
                    ['term' => 'Courses', 'desc' => e((string) $courseCount)],
                ]); ?>
            </div>
        </section>

        <?php if ($faculty['dean_name'] !== null): ?>
            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title">Dean</h2>
                        <p class="panel__sub">The academic lead of this faculty</p>
                    </div>
                </div>
                <div class="panel__body">
                    <?php render_deflist([
                        ['term' => 'Name', 'desc' => '<a class="strong" href="'
                            . e(url('lecturers/view.php?id=' . (int) $faculty['dean_id'])) . '">'
                            . e($faculty['dean_name']) . '</a>'],
                        ['term' => 'Reference', 'desc' => '<span class="table__id">' . e((string) $faculty['dean_staff_no']) . '</span>'],
                        ['term' => 'Email', 'desc' => e((string) $faculty['dean_email'])],
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
                <?php if (($faculty['description'] ?? '') === ''): ?>
                    <p class="text-3 text-sm">No description has been recorded for this faculty.</p>
                <?php else: ?>
                    <div class="prose"><?= nl2br(e($faculty['description'])) ?></div>
                <?php endif; ?>
            </div>
        </section>

    </div>
</div>

<?php layout_end(); ?>
