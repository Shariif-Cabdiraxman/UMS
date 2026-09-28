<?php
/**
 * Departments — the list.
 *
 * A department belongs to exactly one faculty, so the faculty filter is the
 * most useful thing on this screen: it is how you answer "how many students do
 * we have in each department of this faculty".
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'departments/index.php';

// ---------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('departments.manage');

    if (input('action') === 'delete') {
        delete_record('departments', (int) input_int('delete_id', 0), 'Department', $path, [
            1451 => [
                'Student',
                'SELECT COUNT(*) FROM `students` WHERE `department_id` = ?',
                'they are still registered to it',
            ],
            1452 => [
                'Course',
                'SELECT COUNT(*) FROM `courses` WHERE `department_id` = ?',
                'they still belong to it',
            ],
            1453 => [
                'Lecturer',
                'SELECT COUNT(*) FROM `lecturers` WHERE `department_id` = ?',
                'they are still attached to it',
            ],
        ]);
    }
}

// ---------------------------------------------------------------------
// List state
// ---------------------------------------------------------------------

$search  = query_string('q');
$faculty = query_int('faculty');

$sort = sort_state([
    'code'    => 'code',
    'name'    => 'name',
    'faculty' => 'faculty_name',
    'students' => 'student_count',
    'courses'  => 'course_count',
    'lecturers' => 'lecturer_count',
], 'name');

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(d.`name` LIKE ? OR d.`code` LIKE ? OR d.`description` LIKE ?'
        . ' OR CONCAT(h.`first_name`, \' \', h.`last_name`) LIKE ?)';
    $like = like_param($search);
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}

if ($faculty > 0) {
    $where[]  = 'd.`faculty_id` = ?';
    $params[] = $faculty;
    $types   .= 'i';
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$total = (int) db_value(
    'SELECT COUNT(*)
       FROM `departments` d
       JOIN `faculties` f    ON f.`id` = d.`faculty_id`
       LEFT JOIN `lecturers` h ON h.`id` = d.`head_id`
       ' . $whereSql,
    $types,
    $params
);

$page  = paginate($total, current_page_number(), current_page_size());
$types .= 'ii';

$rows = db_all(
    'SELECT d.`id`, d.`code`, d.`name`, d.`description`, d.`faculty_id`, d.`head_id`,
            f.`name` AS `faculty_name`,
            CONCAT(h.`first_name`, \' \', h.`last_name`) AS `head_name`,
            (SELECT COUNT(*) FROM `students` s  WHERE s.`department_id`  = d.`id`) AS `student_count`,
            (SELECT COUNT(*) FROM `courses` c   WHERE c.`department_id`  = d.`id`) AS `course_count`,
            (SELECT COUNT(*) FROM `lecturers` l WHERE l.`department_id` = d.`id`) AS `lecturer_count`
       FROM `departments` d
       JOIN `faculties` f       ON f.`id` = d.`faculty_id`
       LEFT JOIN `lecturers` h  ON h.`id` = d.`head_id`
       ' . $whereSql . '
      ORDER BY ' . $sort['sql'] . '
      LIMIT ? OFFSET ?',
    $types,
    array_merge($params, [$page['per_page'], $page['offset']])
);

$canManage = can('departments.manage');
$faculties = faculty_options(true);

layout_start('Departments', 'departments');

render_page_head([
    'eyebrow'  => 'Academic structure',
    'title'    => 'Departments',
    'subtitle' => 'The teaching units inside each faculty, and the students and staff attached to them.',
    'meta'     => e(format_number($total)) . ' ' . ($total === 1 ? 'department' : 'departments')
        . ($faculty > 0 ? ' in the selected faculty' : ' recorded'),
    'actions'  => $canManage
        ? '<a class="btn btn--primary" href="' . e(url('departments/form.php')) . '">' . icon('plus', 16) . '<span>Add department</span></a>'
        : '',
]);

render_readonly_notice('departments.manage', 'department');

render_list_toolbar([
    'action'     => $path,
    'placeholder' => 'Search by name, code or head of department…',
    'filters'    => filter_select('faculty', 'Filter by faculty', $faculties, $faculty, 'All faculties'),
]);

if ($rows === []) {
    echo '<div class="card">';
    render_empty_state(
        'department',
        ($search === '' && $faculty === 0) ? 'No departments recorded yet' : 'No departments match those filters',
        ($search === '' && $faculty === 0)
            ? 'Departments sit inside a faculty and carry the courses, lecturers and students beneath them.'
            : 'Try a different word, or clear the filters to see every department.',
        ($canManage && $search === '' && $faculty === 0)
            ? '<a class="btn btn--primary" href="' . e(url('departments/form.php')) . '">' . icon('plus', 16) . '<span>Add the first department</span></a>'
            : ''
    );
    echo '</div>';
} else {
    ?>
    <div class="tablewrap">
        <table class="table">
            <thead>
                <tr>
                    <?= th_sort('code', 'Code', $sort) ?>
                    <?= th_sort('name', 'Department', $sort) ?>
                    <?= th_sort('faculty', 'Faculty', $sort) ?>
                    <?= th('Head of department') ?>
                    <?= th_sort('students', 'Students', $sort, 'num') ?>
                    <?= th_sort('lecturers', 'Lecturers', $sort, 'num') ?>
                    <?= th_sort('courses', 'Courses', $sort, 'num') ?>
                    <th scope="col" class="actions">Record</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="table__id nowrap"><?= e($row['code']) ?></td>
                    <td>
                        <a class="strong" href="<?= e(url('departments/view.php?id=' . (int) $row['id'])) ?>"><?= e($row['name']) ?></a>
                        <?php if ($row['description'] !== null && $row['description'] !== ''): ?>
                            <span class="table__sub"><?= e(truncate($row['description'], 64)) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="table__name" href="<?= e(url('faculties/view.php?id=' . (int) $row['faculty_id'])) ?>"><?= e($row['faculty_name']) ?></a>
                    </td>
                    <td>
                        <?php if ($row['head_name'] !== null): ?>
                            <a class="table__name" href="<?= e(url('lecturers/view.php?id=' . (int) $row['head_id'])) ?>"><?= e($row['head_name']) ?></a>
                        <?php else: ?>
                            <span class="text-3">Not appointed</span>
                        <?php endif; ?>
                    </td>
                    <td class="num">
                        <?php if ((int) $row['student_count'] > 0): ?>
                            <a href="<?= e(url('students/index.php?department=' . (int) $row['id'])) ?>"><?= e((string) $row['student_count']) ?></a>
                        <?php else: ?>
                            <span class="text-3">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="num">
                        <?php if ((int) $row['lecturer_count'] > 0): ?>
                            <a href="<?= e(url('lecturers/index.php?department=' . (int) $row['id'])) ?>"><?= e((string) $row['lecturer_count']) ?></a>
                        <?php else: ?>
                            <span class="text-3">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= e((string) $row['course_count']) ?></td>
                    <td class="actions">
                        <a class="btn btn--sm" href="<?= e(url('departments/view.php?id=' . (int) $row['id'])) ?>">Open</a>
                        <?php if ($canManage): ?>
                            <a class="iconbtn" href="<?= e(url('departments/form.php?id=' . (int) $row['id'])) ?>"
                               title="Edit department" aria-label="Edit <?= e($row['name']) ?>">
                                <?= icon('edit', 15) ?><span class="sr-only">Edit</span>
                            </a>
                            <?= render_delete_form($path, (int) $row['id'], 'Delete department ' . $row['name'] . '?') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render_list_footer($page, $path, ['noun' => 'department']);
}

layout_end();
