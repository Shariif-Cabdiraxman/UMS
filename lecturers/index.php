<?php
/**
 * Lecturers — the list.
 *
 * A lecturer belongs to one department, and departments sit inside a faculty,
 * so both of those work as filters here. The course count is the number of
 * courses currently assigned to them, not a historical total.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'lecturers/index.php';

// ---------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('lecturers.manage');

    if (input('action') === 'delete') {
        // Faculties, departments and courses only hold SET NULL pointers to
        // a lecturer, so nothing ever blocks a lecturer being removed; the
        // system simply records the post as vacant.
        delete_record('lecturers', (int) input_int('delete_id', 0), 'Lecturer', $path);
    }
}

// ---------------------------------------------------------------------
// List state
// ---------------------------------------------------------------------

$search     = query_string('q');
$department = query_int('department');
$faculty    = query_int('faculty');

$sort = sort_state([
    'staff_no'     => 'staff_no',
    'name'         => 'name',
    'department'   => 'department_name',
    'specialization' => 'specialization',
    'status'       => 'status',
    'courses'      => 'course_count',
], 'name');

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(l.`staff_no` LIKE ? OR l.`first_name` LIKE ? OR l.`last_name` LIKE ?'
        . ' OR l.`email` LIKE ? OR l.`specialization` LIKE ?)';
    $like = like_param($search);
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

if ($department > 0) {
    $where[]  = 'l.`department_id` = ?';
    $params[] = $department;
    $types   .= 'i';
}

if ($faculty > 0) {
    $where[]  = 'd.`faculty_id` = ?';
    $params[] = $faculty;
    $types   .= 'i';
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$total = (int) db_value(
    'SELECT COUNT(*)
       FROM `lecturers` l
       JOIN `departments` d ON d.`id` = l.`department_id`
       ' . $whereSql,
    $types,
    $params
);

$page  = paginate($total, current_page_number(), current_page_size());
$types .= 'ii';

$rows = db_all(
    'SELECT l.`id`, l.`staff_no`, l.`first_name`, l.`last_name`, l.`email`,
            l.`phone`, l.`specialization`, l.`status`, l.`department_id`,
            CONCAT(l.`first_name`, \' \', l.`last_name`) AS `name`,
            d.`name` AS `department_name`,
            (SELECT COUNT(*) FROM `courses` c WHERE c.`lecturer_id` = l.`id`) AS `course_count`
       FROM `lecturers` l
       JOIN `departments` d ON d.`id` = l.`department_id`
       ' . $whereSql . '
      ORDER BY ' . $sort['sql'] . '
      LIMIT ? OFFSET ?',
    $types,
    array_merge($params, [$page['per_page'], $page['offset']])
);

$canManage    = can('lecturers.manage');
$departments  = department_options(null, true);
$faculties    = faculty_options(true);

layout_start('Lecturers', 'lecturers');

render_page_head([
    'eyebrow'  => 'Academic staff',
    'title'    => 'Lecturers',
    'subtitle' => 'The teaching staff of each department, and the courses they are assigned to.',
    'meta'     => e(format_number($total)) . ' ' . ($total === 1 ? 'lecturer' : 'lecturers') . ' recorded',
    'actions'  => $canManage
        ? '<a class="btn btn--primary" href="' . e(url('lecturers/form.php')) . '">' . icon('plus', 16) . '<span>Add lecturer</span></a>'
        : '',
]);

render_readonly_notice('lecturers.manage', 'lecturer');

render_list_toolbar([
    'action'      => $path,
    'placeholder' => 'Search by name, staff number, email or specialism…',
    'filters'     => filter_select('faculty', 'Filter by faculty', $faculties, $faculty, 'All faculties')
        . filter_select('department', 'Filter by department', $departments, $department, 'All departments'),
]);

if ($rows === []) {
    echo '<div class="card">';
    render_empty_state(
        'lecturer',
        ($search === '' && $department === 0 && $faculty === 0) ? 'No lecturers recorded yet' : 'No lecturers match those filters',
        ($search === '' && $department === 0 && $faculty === 0)
            ? 'Add a member of staff to start building the teaching record.'
            : 'Try a different word, or clear the filters to see every lecturer.',
        ($canManage && $search === '' && $department === 0 && $faculty === 0)
            ? '<a class="btn btn--primary" href="' . e(url('lecturers/form.php')) . '">' . icon('plus', 16) . '<span>Add the first lecturer</span></a>'
            : ''
    );
    echo '</div>';
} else {
    ?>
    <div class="tablewrap">
        <table class="table">
            <thead>
                <tr>
                    <?= th_sort('staff_no', 'Staff no', $sort) ?>
                    <?= th_sort('name', 'Lecturer', $sort) ?>
                    <?= th_sort('department', 'Department', $sort) ?>
                    <?= th_sort('specialization', 'Specialism', $sort) ?>
                    <?= th_sort('status', 'Status', $sort) ?>
                    <?= th_sort('courses', 'Courses', $sort, 'num') ?>
                    <th scope="col" class="actions">Record</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="table__id nowrap"><?= e($row['staff_no']) ?></td>
                    <td>
                        <a class="strong" href="<?= e(url('lecturers/view.php?id=' . (int) $row['id'])) ?>"><?= e(full_name($row['first_name'], $row['last_name'])) ?></a>
                        <span class="table__sub"><?= e($row['email']) ?></span>
                    </td>
                    <td>
                        <a class="table__name" href="<?= e(url('departments/view.php?id=' . (int) $row['department_id'])) ?>"><?= e($row['department_name']) ?></a>
                    </td>
                    <td class="text-3"><?= e($row['specialization'] ?? '—') ?></td>
                    <td><?= badge($row['status']) ?></td>
                    <td class="num"><?= e((string) $row['course_count']) ?></td>
                    <td class="actions">
                        <a class="btn btn--sm" href="<?= e(url('lecturers/view.php?id=' . (int) $row['id'])) ?>">Open</a>
                        <?php if ($canManage): ?>
                            <a class="iconbtn" href="<?= e(url('lecturers/form.php?id=' . (int) $row['id'])) ?>"
                               title="Edit lecturer" aria-label="Edit <?= e(full_name($row['first_name'], $row['last_name'])) ?>">
                                <?= icon('edit', 15) ?><span class="sr-only">Edit</span>
                            </a>
                            <?= render_delete_form($path, (int) $row['id'], 'Delete lecturer ' . full_name($row['first_name'], $row['last_name']) . '?') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render_list_footer($page, $path, ['noun' => 'lecturer']);
}

layout_end();