<?php
/**
 * Enrollments — the list.
 *
 * An enrollment is the registration of one student for one course in one
 * semester of one academic year. The composite unique key means a student
 * appears once per course per term, and the list reflects that: one row per
 * registration, with the marks recorded against it.
 *
 * The `student` filter accepts a student number as well as an id, because
 * students are addressed by their number everywhere else in the application.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'enrollments/index.php';

// ---------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('enrollments.manage');

    if (input('action') === 'delete') {
        // Marks cascade at the database level, but an enrollment with grades
        // recorded is flagged first so the results are not silently wiped.
        delete_record('enrollments', (int) input_int('delete_id', 0), 'Enrollment', $path, [
            1451 => [
                'Grade',
                'SELECT COUNT(*) FROM `grades` WHERE `enrollment_id` = ?',
                'marks are still recorded against it',
            ],
        ]);
    }
}

// ---------------------------------------------------------------------
// List state
// ---------------------------------------------------------------------

$search   = query_string('q');
$course   = query_int('course');
$department = query_int('department');
$semester = query_string('semester');
$year     = query_string('year');
$status   = query_string('status');
$student  = query_int('student');

// Accept a student number in the `student` filter and resolve it to an id.
$studentFilter = query_string('student');
if ($studentFilter !== '' && $student === 0) {
    $byNo = find_student_by_no($studentFilter);
    $student = $byNo !== null ? (int) $byNo['id'] : -1;
}

$sort = sort_state([
    'student'  => 'student_name',
    'course'   => 'course_code',
    'year'     => 'academic_year',
    'semester' => 'semester',
    'status'   => 'status',
    'date'     => 'enrollment_date',
    'marks'    => 'average',
], 'year');

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(c.`course_code` LIKE ? OR c.`course_name` LIKE ? OR s.`student_no` LIKE ?'
        . ' OR s.`first_name` LIKE ? OR s.`last_name` LIKE ?)';
    $like = like_param($search);
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

// A student number that resolves to nothing becomes -1, and a supplied filter is
// always applied. Dropping the clause instead would show the whole ledger under
// a URL that claims to be filtered to one student, with no filter shown.
if ($student !== 0) {
    $where[]  = 'e.`student_id` = ?';
    $params[] = $student;
    $types   .= 'i';
}

if ($course > 0) {
    $where[]  = 'e.`course_id` = ?';
    $params[] = $course;
    $types   .= 'i';
}

if ($department > 0) {
    $where[]  = 'c.`department_id` = ?';
    $params[] = $department;
    $types   .= 'i';
}

if ($semester !== '') {
    $where[]  = 'e.`semester` = ?';
    $params[] = $semester;
    $types   .= 's';
}

if ($year !== '') {
    $where[]  = 'e.`academic_year` = ?';
    $params[] = $year;
    $types   .= 's';
}

if ($status !== '') {
    $where[]  = 'e.`status` = ?';
    $params[] = $status;
    $types   .= 's';
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$total = (int) db_value(
    'SELECT COUNT(*)
       FROM `enrollments` e
       JOIN `students` s   ON s.`id` = e.`student_id`
       JOIN `courses` c    ON c.`id` = e.`course_id`
       ' . $whereSql,
    $types,
    $params
);

$page  = paginate($total, current_page_number(), current_page_size());
$types .= 'ii';

$rows = db_all(
    'SELECT e.`id`, e.`academic_year`, e.`semester`, e.`enrollment_date`, e.`status`,
            e.`student_id`, e.`course_id`,
            s.`student_no`,
            CONCAT(s.`first_name`, \' \', s.`last_name`) AS `student_name`,
            c.`course_code`, c.`course_name`,
            (SELECT COUNT(*) FROM `grades` g WHERE g.`enrollment_id` = e.`id`) AS `grade_count`,
            (SELECT ROUND(AVG(g.`marks`), 2) FROM `grades` g WHERE g.`enrollment_id` = e.`id`) AS `average`
       FROM `enrollments` e
       JOIN `students` s   ON s.`id` = e.`student_id`
       JOIN `courses` c    ON c.`id` = e.`course_id`
       ' . $whereSql . '
      ORDER BY ' . $sort['sql'] . '
      LIMIT ? OFFSET ?',
    $types,
    array_merge($params, [$page['per_page'], $page['offset']])
);

$canManage    = can('enrollments.manage');
$students     = student_options(null, true);
$departments  = department_options(null, true);
$courses      = course_options(null, null, true);
$statuses     = ['' => 'All statuses'] + enrollment_status_options();
$years        = ['' => 'All years'] + array_combine(academic_years(), academic_years());
$semesters    = ['' => 'All semesters'] + semesters();

layout_start('Enrollments', 'enrollments');

render_page_head([
    'eyebrow'  => 'Academic record',
    'title'    => 'Enrollments',
    'subtitle' => 'The registration of students for courses, one row per course per term.',
    'meta'     => e(format_number($total)) . ' ' . ($total === 1 ? 'enrollment' : 'enrollments') . ' recorded',
    'actions'  => $canManage
        ? '<a class="btn btn--primary" href="' . e(url('enrollments/form.php')) . '">' . icon('plus', 16) . '<span>Add enrollment</span></a>'
        : '',
]);

render_readonly_notice('enrollments.manage', 'enrollment');

render_list_toolbar([
    'action'      => $path,
    'placeholder' => 'Search by course or student…',
    'filters'     => filter_select('student', 'Filter by student', $students, $student, 'Any student')
        . filter_select('department', 'Filter by department', $departments, $department, 'All departments')
        . filter_select('course', 'Filter by course', $courses, $course, 'All courses')
        . filter_select('semester', 'Filter by semester', $semesters, $semester, 'All semesters')
        . filter_select('year', 'Filter by year', $years, $year, 'All years')
        . filter_select('status', 'Filter by status', $statuses, $status, 'All statuses'),
]);

if ($rows === []) {
    echo '<div class="card">';
    render_empty_state(
        'enrollment',
        ($search === '' && $student <= 0 && $course === 0 && $department === 0 && $semester === '' && $year === '' && $status === '')
            ? 'No enrollments recorded yet'
            : 'No enrollments match those filters',
        ($search === '' && $student <= 0 && $course === 0 && $department === 0 && $semester === '' && $year === '' && $status === '')
            ? 'An enrollment registers one student for one course in one term.'
            : 'Try a different word, or clear the filters to see every enrollment.',
        ($canManage && $search === '' && $student <= 0 && $course === 0 && $department === 0 && $semester === '' && $year === '' && $status === '')
            ? '<a class="btn btn--primary" href="' . e(url('enrollments/form.php')) . '">' . icon('plus', 16) . '<span>Add the first enrollment</span></a>'
            : ''
    );
    echo '</div>';
} else {
    ?>
    <div class="tablewrap">
        <table class="table">
            <thead>
                <tr>
                    <?= th_sort('year', 'Year', $sort, 'nowrap') ?>
                    <?= th_sort('semester', 'Semester', $sort) ?>
                    <?= th_sort('student', 'Student', $sort) ?>
                    <?= th_sort('course', 'Course', $sort) ?>
                    <?= th_sort('status', 'Status', $sort) ?>
                    <?= th('Marks', 'num') ?>
                    <?= th_sort('date', 'Enrolled', $sort) ?>
                    <th scope="col" class="actions">Record</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="nowrap"><?= e($row['academic_year']) ?></td>
                    <td><?= e($row['semester']) ?></td>
                    <td>
                        <a class="strong" href="<?= e(url('students/view.php?id=' . urlencode((string) $row['student_no']))) ?>"><?= e($row['student_name']) ?></a>
                        <span class="table__sub"><?= e($row['student_no']) ?></span>
                    </td>
                    <td>
                        <a class="table__name" href="<?= e(url('courses/view.php?id=' . (int) $row['course_id'])) ?>"><?= e($row['course_code']) ?></a>
                        <span class="table__sub"><?= e(truncate((string) $row['course_name'], 46)) ?></span>
                    </td>
                    <td><?= badge($row['status']) ?></td>
                    <td class="num">
                        <?php if ($row['average'] !== null): ?>
                            <a class="table__name" href="<?= e(url('enrollments/view.php?id=' . (int) $row['id'])) ?>"><?= e(format_marks($row['average'])) ?></a>
                        <?php else: ?>
                            <span class="text-3">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-3"><?= e(format_db_date($row['enrollment_date'])) ?></td>
                    <td class="actions">
                        <a class="btn btn--sm" href="<?= e(url('enrollments/view.php?id=' . (int) $row['id'])) ?>">Open</a>
                        <?php if ($canManage): ?>
                            <a class="iconbtn" href="<?= e(url('enrollments/form.php?id=' . (int) $row['id'])) ?>"
                               title="Edit enrollment" aria-label="Edit the enrollment of <?= e($row['student_name']) ?> in <?= e($row['course_code']) ?>">
                                <?= icon('edit', 15) ?><span class="sr-only">Edit</span>
                            </a>
                            <?= render_delete_form($path, (int) $row['id'], 'Delete this enrollment?') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render_list_footer($page, $path, ['noun' => 'enrollment']);
}

layout_end();