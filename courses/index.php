<?php
/**
 * Courses — the list.
 *
 * Courses are taught by a department and, optionally, assigned to a single
 * lecturer. The enrolment figure is the number of student registrations
 * recorded against the course across every academic year.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'courses/index.php';

// ---------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('courses.manage');

    if (input('action') === 'delete') {
        // Enrolments cascade at the database level, but a course with
        // students enrolled is flagged first so the numbers are not silently
        // wiped. A course that has never been enrolled in can be removed.
        delete_record('courses', (int) input_int('delete_id', 0), 'Course', $path, [
            1451 => [
                'Enrollment',
                'SELECT COUNT(*) FROM `enrollments` WHERE `course_id` = ?',
                'students are still enrolled to it',
            ],
        ]);
    }
}

// ---------------------------------------------------------------------
// List state
// ---------------------------------------------------------------------

$search     = query_string('q');
$department = query_int('department');
$semester   = query_string('semester');
$lecturer   = query_int('lecturer');

$sort = sort_state([
    'course_code' => 'course_code',
    'course_name' => 'course_name',
    'department'  => 'department_name',
    'semester'    => 'semester',
    'credit_hours' => 'credit_hours',
    'lecturer'    => 'lecturer_name',
    'enrolled'    => 'enrolled_count',
], 'course_name');

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(c.`course_code` LIKE ? OR c.`course_name` LIKE ? OR c.`description` LIKE ?'
        . ' OR CONCAT(l.`first_name`, \' \', l.`last_name`) LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}

if ($department > 0) {
    $where[]  = 'c.`department_id` = ?';
    $params[] = $department;
    $types   .= 'i';
}

if ($semester !== '') {
    $where[]  = 'c.`semester` = ?';
    $params[] = $semester;
    $types   .= 's';
}

if ($lecturer > 0) {
    $where[]  = 'c.`lecturer_id` = ?';
    $params[] = $lecturer;
    $types   .= 'i';
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$total = (int) db_value(
    'SELECT COUNT(*)
       FROM `courses` c
       JOIN `departments` d    ON d.`id` = c.`department_id`
       LEFT JOIN `lecturers` l ON l.`id` = c.`lecturer_id`
       ' . $whereSql,
    $types,
    $params
);

$page  = paginate($total, current_page_number(), current_page_size());
$types .= 'ii';

$rows = db_all(
    'SELECT c.`id`, c.`course_code`, c.`course_name`, c.`credit_hours`, c.`semester`,
            c.`department_id`, c.`lecturer_id`,
            CONCAT(l.`first_name`, \' \', l.`last_name`) AS `lecturer_name`,
            d.`name` AS `department_name`,
            (SELECT COUNT(*) FROM `enrollments` e WHERE e.`course_id` = c.`id`) AS `enrolled_count`
       FROM `courses` c
       JOIN `departments` d    ON d.`id` = c.`department_id`
       LEFT JOIN `lecturers` l ON l.`id` = c.`lecturer_id`
       ' . $whereSql . '
      ORDER BY ' . $sort['sql'] . '
      LIMIT ? OFFSET ?',
    $types,
    array_merge($params, [$page['per_page'], $page['offset']])
);

$canManage    = can('courses.manage');
$departments  = department_options(null, true);
$semesters    = ['' => 'All semesters'] + semesters();
$lecturers    = lecturer_options(null, true, 'Any lecturer');

layout_start('Courses', 'courses');

render_page_head([
    'eyebrow'  => 'Academic record',
    'title'    => 'Courses',
    'subtitle' => 'The modules departments offer, with the staff who teach them.',
    'meta'     => e(format_number($total)) . ' ' . ($total === 1 ? 'course' : 'courses') . ' recorded',
    'actions'  => $canManage
        ? '<a class="btn btn--primary" href="' . e(url('courses/form.php')) . '">' . icon('plus', 16) . '<span>Add course</span></a>'
        : '',
]);

render_readonly_notice('courses.manage', 'course');

render_list_toolbar([
    'action'      => $path,
    'placeholder' => 'Search by code, name or lecturer…',
    'filters'     => filter_select('department', 'Filter by department', $departments, $department, 'All departments')
        . filter_select('semester', 'Filter by semester', $semesters, $semester, 'All semesters')
        . filter_select('lecturer', 'Filter by lecturer', $lecturers, $lecturer, 'Any lecturer'),
]);

if ($rows === []) {
    echo '<div class="card">';
    render_empty_state(
        'course',
        ($search === '' && $department === 0 && $semester === '' && $lecturer === 0) ? 'No courses recorded yet' : 'No courses match those filters',
        ($search === '' && $department === 0 && $semester === '' && $lecturer === 0)
            ? 'Add a course to start building the teaching catalogue.'
            : 'Try a different word, or clear the filters to see every course.',
        ($canManage && $search === '' && $department === 0 && $semester === '' && $lecturer === 0)
            ? '<a class="btn btn--primary" href="' . e(url('courses/form.php')) . '">' . icon('plus', 16) . '<span>Add the first course</span></a>'
            : ''
    );
    echo '</div>';
} else {
    ?>
    <div class="tablewrap">
        <table class="table">
            <thead>
                <tr>
                    <?= th_sort('course_code', 'Code', $sort) ?>
                    <?= th_sort('course_name', 'Course', $sort) ?>
                    <?= th_sort('department', 'Department', $sort) ?>
                    <?= th_sort('lecturer', 'Lecturer', $sort) ?>
                    <?= th_sort('semester', 'Semester', $sort) ?>
                    <?= th_sort('credit_hours', 'Credits', $sort, 'num') ?>
                    <?= th_sort('enrolled', 'Enrolled', $sort, 'num') ?>
                    <th scope="col" class="actions">Record</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="table__id nowrap"><?= e($row['course_code']) ?></td>
                    <td>
                        <a class="strong" href="<?= e(url('courses/view.php?id=' . (int) $row['id'])) ?>"><?= e($row['course_name']) ?></a>
                    </td>
                    <td>
                        <a class="table__name" href="<?= e(url('departments/view.php?id=' . (int) $row['department_id'])) ?>"><?= e($row['department_name']) ?></a>
                    </td>
                    <td class="text-3"><?= $row['lecturer_id'] !== null
                        ? '<a class="table__name" href="' . e(url('lecturers/view.php?id=' . (int) $row['lecturer_id'])) . '">' . e($row['lecturer_name']) . '</a>'
                        : 'Not assigned' ?></td>
                    <td><?= e($row['semester']) ?></td>
                    <td class="num"><?= e((string) $row['credit_hours']) ?></td>
                    <td class="num">
                        <?php if ((int) $row['enrolled_count'] > 0): ?>
                            <a href="<?= e(url('enrollments/index.php?course=' . (int) $row['id'])) ?>"><?= e((string) $row['enrolled_count']) ?></a>
                        <?php else: ?>
                            <span class="text-3">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn btn--sm" href="<?= e(url('courses/view.php?id=' . (int) $row['id'])) ?>">Open</a>
                        <?php if ($canManage): ?>
                            <a class="iconbtn" href="<?= e(url('courses/form.php?id=' . (int) $row['id'])) ?>"
                               title="Edit course" aria-label="Edit <?= e($row['course_name']) ?>">
                                <?= icon('edit', 15) ?><span class="sr-only">Edit</span>
                            </a>
                            <?= render_delete_form($path, (int) $row['id'], 'Delete course ' . $row['course_code'] . ' ' . $row['course_name'] . '?') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render_list_footer($page, $path, ['noun' => 'course']);
}

layout_end();