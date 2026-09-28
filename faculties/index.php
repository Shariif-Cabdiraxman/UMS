<?php
/**
 * Faculties — the list.
 *
 * A faculty is the top of the academic structure, and almost everything else
 * hangs off it, so this screen also shows how much of the university each
 * faculty is carrying. That is the number people actually want from this page.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'faculties/index.php';

// ---------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('faculties.manage');

    if (input('action') === 'delete') {
        delete_record('faculties', (int) input_int('delete_id', 0), 'Faculty', $path, [
            1451 => [
                'Department',
                'SELECT COUNT(*) FROM `departments` WHERE `faculty_id` = ?',
                'they still belong to it',
            ],
        ]);
    }
}

// ---------------------------------------------------------------------
// List state
// ---------------------------------------------------------------------

$search = query_string('q');
$sort   = sort_state([
    'code'    => 'code',
    'name'    => 'name',
    'dean'    => 'dean_name',
    'departments' => 'department_count',
    'students'    => 'student_count',
], 'name');

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(f.`name` LIKE ? OR f.`code` LIKE ? OR f.`description` LIKE ?'
        . ' OR CONCAT(l.`first_name`, \' \', l.`last_name`) LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$total = (int) db_value(
    'SELECT COUNT(*) FROM `faculties` f
       LEFT JOIN `lecturers` l ON l.`id` = f.`dean_id`
       ' . $whereSql,
    $types,
    $params
);

$page  = paginate($total, current_page_number(), current_page_size());
$types .= 'ii';

$rows = db_all(
    'SELECT f.`id`, f.`code`, f.`name`, f.`description`,
            CONCAT(l.`first_name`, \' \', l.`last_name`) AS `dean_name`,
            l.`id` AS `dean_id`,
            COUNT(DISTINCT d.`id`) AS `department_count`,
            COUNT(DISTINCT s.`id`) AS `student_count`
       FROM `faculties` f
       LEFT JOIN `lecturers` l  ON l.`id` = f.`dean_id`
       LEFT JOIN `departments` d ON d.`faculty_id` = f.`id`
       LEFT JOIN `students` s    ON s.`faculty_id` = f.`id`
       ' . $whereSql . '
      GROUP BY f.`id`, f.`code`, f.`name`, f.`description`, l.`first_name`, l.`last_name`, l.`id`
      ORDER BY ' . $sort['sql'] . '
      LIMIT ? OFFSET ?',
    $types,
    array_merge($params, [$page['per_page'], $page['offset']])
);

$canManage = can('faculties.manage');

layout_start('Faculties', 'faculties');

render_page_head([
    'eyebrow'  => 'Academic structure',
    'title'    => 'Faculties',
    'subtitle' => 'The teaching divisions of the university, and what each one carries.',
    'meta'     => e(format_number($total)) . ' ' . ($total === 1 ? 'faculty' : 'faculties') . ' recorded',
    'actions'  => $canManage
        ? '<a class="btn btn--primary" href="' . e(url('faculties/form.php')) . '">' . icon('plus', 16) . '<span>Add faculty</span></a>'
        : '',
]);

render_readonly_notice('faculties.manage', 'faculty');

render_list_toolbar([
    'action'     => $path,
    'placeholder' => 'Search by name, code or dean…',
]);

if ($rows === []) {
    echo '<div class="card">';
    render_empty_state(
        'faculty',
        $search === '' ? 'No faculties recorded yet' : 'No faculties match that search',
        $search === ''
            ? 'A faculty is the top level of the academic structure. Everything else sits inside one.'
            : 'Try a different word, or clear the filters to see all faculties.',
        $canManage && $search === ''
            ? '<a class="btn btn--primary" href="' . e(url('faculties/form.php')) . '">' . icon('plus', 16) . '<span>Add the first faculty</span></a>'
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
                    <?= th_sort('name', 'Faculty', $sort) ?>
                    <?= th_sort('dean', 'Dean', $sort) ?>
                    <?= th_sort('departments', 'Departments', $sort, 'num') ?>
                    <?= th_sort('students', 'Students', $sort, 'num') ?>
                    <th scope="col" class="actions">Record</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="table__id nowrap"><?= e($row['code']) ?></td>
                    <td>
                        <span class="cellstack">
                            <a class="strong" href="<?= e(url('faculties/view.php?id=' . (int) $row['id'])) ?>"><?= e($row['name']) ?></a>
                            <?php if ($row['description'] !== null && $row['description'] !== ''): ?>
                                <span><?= e(truncate($row['description'], 68)) ?></span>
                            <?php endif; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($row['dean_name'] !== null): ?>
                            <a class="table__name" href="<?= e(url('lecturers/view.php?id=' . (int) $row['dean_id'])) ?>"><?= e($row['dean_name']) ?></a>
                        <?php else: ?>
                            <span class="text-3">No dean assigned</span>
                        <?php endif; ?>
                    </td>
                    <td class="num">
                        <?php if ((int) $row['department_count'] > 0): ?>
                            <a href="<?= e(url('departments/index.php?faculty=' . (int) $row['id'])) ?>"><?= e((string) $row['department_count']) ?></a>
                        <?php else: ?>
                            <span class="text-3">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= e(format_number((int) $row['student_count'])) ?></td>
                    <td class="actions">
                        <a class="btn btn--sm" href="<?= e(url('faculties/view.php?id=' . (int) $row['id'])) ?>">Open</a>
                        <?php if ($canManage): ?>
                            <a class="iconbtn" href="<?= e(url('faculties/form.php?id=' . (int) $row['id'])) ?>" title="Edit faculty" aria-label="Edit <?= e($row['name']) ?>">
                                <?= icon('edit', 15) ?><span class="sr-only">Edit</span>
                            </a>
                            <?= render_delete_form($path, (int) $row['id'], 'Delete faculty ' . $row['name'] . '?') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render_list_footer($page, $path, ['noun' => 'faculty']);
}

layout_end();
