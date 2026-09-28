<?php
/**
 * Students — the list.
 *
 * Students are addressed by their student number, never by an internal id,
 * so every link into this module uses the reference printed on the student's
 * identity card. The faculty and department filters answer the usual question
 * first: who belongs to which part of the university.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'students/index.php';

// ---------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('students.manage');

    if (input('action') === 'delete') {
        // Enrolments cascade at the database level, but a student with a
        // history of registrations is flagged first so the record is not
        // silently wiped.
        delete_record('students', (int) input_int('delete_id', 0), 'Student', $path, [
            1451 => [
                'Enrollment',
                'SELECT COUNT(*) FROM `enrollments` WHERE `student_id` = ?',
                'they are still registered against it',
            ],
        ]);
    }
}

// ---------------------------------------------------------------------
// List state
// ---------------------------------------------------------------------

$search     = query_string('q');
$faculty    = query_int('faculty');
$department = query_int('department');
$status     = query_string('status');
$year       = query_int('year');

$sort = sort_state([
    'student_no'      => 'student_no',
    'name'            => 'name',
    'faculty'         => 'faculty_name',
    'department'      => 'department_name',
    'enrollment_year' => 'enrollment_year',
    'status'          => 'status',
], 'student_no');

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(s.`student_no` LIKE ? OR s.`first_name` LIKE ? OR s.`last_name` LIKE ?'
        . ' OR s.`email` LIKE ?)';
    $like = like_param($search);
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}

if ($faculty > 0) {
    $where[]  = 's.`faculty_id` = ?';
    $params[] = $faculty;
    $types   .= 'i';
}

if ($department > 0) {
    $where[]  = 's.`department_id` = ?';
    $params[] = $department;
    $types   .= 'i';
}

if ($status !== '') {
    $where[]  = 's.`status` = ?';
    $params[] = $status;
    $types   .= 's';
}

if ($year > 0) {
    $where[]  = 's.`enrollment_year` = ?';
    $params[] = $year;
    $types   .= 'i';
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$total = (int) db_value(
    'SELECT COUNT(*)
       FROM `students` s
       JOIN `faculties` f     ON f.`id` = s.`faculty_id`
       JOIN `departments` d   ON d.`id` = s.`department_id`
       ' . $whereSql,
    $types,
    $params
);

$page  = paginate($total, current_page_number(), current_page_size());
$types .= 'ii';

$rows = db_all(
    'SELECT s.`id`, s.`student_no`, s.`first_name`, s.`last_name`, s.`gender`, s.`email`,
            s.`department_id`, s.`faculty_id`, s.`enrollment_year`, s.`enrollment_date`, s.`status`,
            CONCAT(s.`first_name`, \' \', s.`last_name`) AS `name`,
            f.`name` AS `faculty_name`,
            d.`name` AS `department_name`,
            (SELECT COUNT(*) FROM `enrollments` e WHERE e.`student_id` = s.`id`) AS `enrollment_count`
       FROM `students` s
       JOIN `faculties` f     ON f.`id` = s.`faculty_id`
       JOIN `departments` d   ON d.`id` = s.`department_id`
       ' . $whereSql . '
      ORDER BY ' . $sort['sql'] . '
      LIMIT ? OFFSET ?',
    $types,
    array_merge($params, [$page['per_page'], $page['offset']])
);

$canManage    = can('students.manage');
$faculties    = faculty_options(true);
$departments  = department_options(null, true);
$statuses     = ['' => 'All statuses'] + student_status_options();

// A dropdown of the academic years that actually appear in the records.
$yearRows = db_all('SELECT DISTINCT `enrollment_year` FROM `students` ORDER BY `enrollment_year` DESC');
$yearOptions = ['' => 'All years'];
foreach ($yearRows as $row) {
    $yearOptions[$row['enrollment_year']] = (string) $row['enrollment_year'];
}

layout_start('Students', 'students');

render_page_head([
    'eyebrow'  => 'Student body',
    'title'    => 'Students',
    'subtitle' => 'Everyone registered at the university, and the programme they belong to.',
    'meta'     => e(format_number($total)) . ' ' . ($total === 1 ? 'student' : 'students') . ' recorded',
    'actions'  => $canManage
        ? '<a class="btn btn--primary" href="' . e(url('students/form.php')) . '">' . icon('plus', 16) . '<span>Add student</span></a>'
        : '',
]);

render_readonly_notice('students.manage', 'student');

render_list_toolbar([
    'action'      => $path,
    'placeholder' => 'Search by name, student number or email…',
    'filters'     => filter_select('faculty', 'Filter by faculty', $faculties, $faculty, 'All faculties')
        . filter_select('department', 'Filter by department', $departments, $department, 'All departments')
        . filter_select('status', 'Filter by status', $statuses, $status, 'All statuses')
        . filter_select('year', 'Filter by year', $yearOptions, $year, 'All years'),
]);

if ($rows === []) {
    echo '<div class="card">';
    render_empty_state(
        'student',
        ($search === '' && $faculty === 0 && $department === 0 && $status === '' && $year === 0) ? 'No students recorded yet' : 'No students match those filters',
        ($search === '' && $faculty === 0 && $department === 0 && $status === '' && $year === 0)
            ? 'Add a student to start building the student body.'
            : 'Try a different word, or clear the filters to see every student.',
        ($canManage && $search === '' && $faculty === 0 && $department === 0 && $status === '' && $year === 0)
            ? '<a class="btn btn--primary" href="' . e(url('students/form.php')) . '">' . icon('plus', 16) . '<span>Add the first student</span></a>'
            : ''
    );
    echo '</div>';
} else {
    ?>
    <div class="tablewrap">
        <table class="table">
            <thead>
                <tr>
                    <?= th_sort('student_no', 'Student no', $sort) ?>
                    <?= th_sort('name', 'Student', $sort) ?>
                    <?= th_sort('department', 'Department', $sort) ?>
                    <?= th_sort('faculty', 'Faculty', $sort) ?>
                    <?= th_sort('enrollment_year', 'Enrolled', $sort, 'num') ?>
                    <?= th_sort('status', 'Status', $sort) ?>
                    <th scope="col" class="actions">Record</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="table__id nowrap"><?= e($row['student_no']) ?></td>
                    <td>
                        <a class="strong" href="<?= e(url('students/view.php?id=' . urlencode((string) $row['student_no']))) ?>"><?= e(full_name($row['first_name'], $row['last_name'])) ?></a>
                        <span class="table__sub"><?= e($row['email']) ?></span>
                    </td>
                    <td>
                        <a class="table__name" href="<?= e(url('departments/view.php?id=' . (int) $row['department_id'])) ?>"><?= e($row['department_name']) ?></a>
                    </td>
                    <td class="text-3"><?= e($row['faculty_name']) ?></td>
                    <td class="num"><?= e((string) $row['enrollment_year']) ?></td>
                    <td><?= badge($row['status']) ?></td>
                    <td class="actions">
                        <a class="btn btn--sm" href="<?= e(url('students/view.php?id=' . urlencode((string) $row['student_no']))) ?>">Open</a>
                        <?php if ($canManage): ?>
                            <a class="iconbtn" href="<?= e(url('students/form.php?id=' . urlencode((string) $row['student_no']))) ?>"
                               title="Edit student" aria-label="Edit <?= e(full_name($row['first_name'], $row['last_name'])) ?>">
                                <?= icon('edit', 15) ?><span class="sr-only">Edit</span>
                            </a>
                            <?= render_delete_form($path, (int) $row['id'], 'Delete student ' . full_name($row['first_name'], $row['last_name']) . '?') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render_list_footer($page, $path, ['noun' => 'student']);
}

layout_end();