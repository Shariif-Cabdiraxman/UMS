<?php
/**
 * Grades — the list.
 *
 * A grade is a mark recorded against one assessment of one enrollment, and
 * through that enrollment the student, course and term are all derived. The
 * letter grade is stored beside the marks so it can be filtered and charted.
 *
 * The `student` filter accepts a student number as well as an id, because
 * students are addressed by their number everywhere else in the application.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'grades/index.php';

// ---------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('grades.manage');

    if (input('action') === 'delete') {
        delete_record('grades', (int) input_int('delete_id', 0), 'Grade', $path);
    }
}

// ---------------------------------------------------------------------
// List state
// ---------------------------------------------------------------------

$search   = query_string('q');
$course   = query_int('course');
$letter   = query_string('letter');
$semester = query_string('semester');
$year     = query_string('year');
$student  = query_int('student');

// Accept a student number in the `student` filter and resolve it to an id.
$studentFilter = query_string('student');
if ($studentFilter !== '' && $student === 0) {
    $byNo = find_student_by_no($studentFilter);
    $student = $byNo !== null ? (int) $byNo['id'] : -1;
}

$sort = sort_state([
    'letter'     => 'letter_grade',
    'marks'      => 'marks',
    'assessment' => 'assessment',
    'student'    => 'student_name',
    'course'     => 'course_code',
    'year'       => 'academic_year',
    'updated'    => 'updated_at',
], 'updated');

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(c.`course_code` LIKE ? OR c.`course_name` LIKE ? OR s.`student_no` LIKE ?'
        . ' OR s.`first_name` LIKE ? OR s.`last_name` LIKE ? OR g.`assessment` LIKE ?)';
    $like = like_param($search);
    array_push($params, $like, $like, $like, $like, $like, $like);
    $types .= 'ssssss';
}

// A student number that resolves to nothing becomes -1, and a supplied filter is
// always applied. Dropping the clause instead would show every mark under a URL
// that claims to be filtered to one student, with no filter shown.
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

if ($letter !== '') {
    $where[]  = 'g.`letter_grade` = ?';
    $params[] = $letter;
    $types   .= 's';
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

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$total = (int) db_value(
    'SELECT COUNT(*)
       FROM `grades` g
       JOIN `enrollments` e ON e.`id` = g.`enrollment_id`
       JOIN `students` s   ON s.`id` = e.`student_id`
       JOIN `courses` c    ON c.`id` = e.`course_id`
       ' . $whereSql,
    $types,
    $params
);

$page  = paginate($total, current_page_number(), current_page_size());
$types .= 'ii';

$rows = db_all(
    'SELECT g.`id`, g.`assessment`, g.`marks`, g.`letter_grade`, g.`updated_at`,
            e.`id` AS `enrollment_id`, e.`course_id`, e.`academic_year`, e.`semester`,
            s.`id` AS `student_id`, s.`student_no`,
            CONCAT(s.`first_name`, \' \', s.`last_name`) AS `student_name`,
            c.`course_code`, c.`course_name`
       FROM `grades` g
       JOIN `enrollments` e ON e.`id` = g.`enrollment_id`
       JOIN `students` s   ON s.`id` = e.`student_id`
       JOIN `courses` c    ON c.`id` = e.`course_id`
       ' . $whereSql . '
      ORDER BY ' . $sort['sql'] . '
      LIMIT ? OFFSET ?',
    $types,
    array_merge($params, [$page['per_page'], $page['offset']])
);

$canManage  = can('grades.manage');
$students   = student_options(null, true);
$courses    = course_options(null, null, true);
$letters    = ['' => 'All grades'] + ['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'F' => 'F'];
$years      = ['' => 'All years'] + array_combine(academic_years(), academic_years());
$semesters  = ['' => 'All semesters'] + semesters();

layout_start('Grades', 'grades');

render_page_head([
    'eyebrow'  => 'Academic record',
    'title'    => 'Grades',
    'subtitle' => 'The marks recorded against each assessment, with the letter grade they translate to.',
    'meta'     => e(format_number($total)) . ' ' . ($total === 1 ? 'grade' : 'grades') . ' recorded',
    'actions'  => $canManage
        ? '<a class="btn btn--primary" href="' . e(url('grades/form.php')) . '">' . icon('plus', 16) . '<span>Add grade</span></a>'
        : '',
]);

render_readonly_notice('grades.manage', 'grade');

render_list_toolbar([
    'action'      => $path,
    'placeholder' => 'Search by student, course or assessment…',
    'filters'     => filter_select('student', 'Filter by student', $students, $student, 'Any student')
        . filter_select('course', 'Filter by course', $courses, $course, 'All courses')
        . filter_select('letter', 'Filter by grade', $letters, $letter, 'Any grade')
        . filter_select('semester', 'Filter by semester', $semesters, $semester, 'All semesters')
        . filter_select('year', 'Filter by year', $years, $year, 'All years'),
]);

if ($rows === []) {
    echo '<div class="card">';
    render_empty_state(
        'grades',
        ($search === '' && $student <= 0 && $course === 0 && $letter === '' && $semester === '' && $year === '')
            ? 'No grades recorded yet'
            : 'No grades match those filters',
        ($search === '' && $student <= 0 && $course === 0 && $letter === '' && $semester === '' && $year === '')
            ? 'Marks entered against an enrollment appear here with their letter grade.'
            : 'Try a different word, or clear the filters to see every grade.',
        ($canManage && $search === '' && $student <= 0 && $course === 0 && $letter === '' && $semester === '' && $year === '')
            ? '<a class="btn btn--primary" href="' . e(url('grades/form.php')) . '">' . icon('plus', 16) . '<span>Add the first grade</span></a>'
            : ''
    );
    echo '</div>';
} else {
    ?>
    <div class="tablewrap">
        <table class="table">
            <thead>
                <tr>
                    <?= th_sort('student', 'Student', $sort) ?>
                    <?= th_sort('course', 'Course', $sort) ?>
                    <?= th_sort('assessment', 'Assessment', $sort) ?>
                    <?= th_sort('marks', 'Marks', $sort, 'num') ?>
                    <?= th_sort('letter', 'Grade', $sort, 'num') ?>
                    <?= th_sort('year', 'Year', $sort, 'nowrap') ?>
                    <?= th_sort('semester', 'Semester', $sort) ?>
                    <th scope="col" class="actions">Record</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <a class="strong" href="<?= e(url('students/view.php?id=' . urlencode((string) $row['student_no']))) ?>"><?= e($row['student_name']) ?></a>
                    </td>
                    <td>
                        <a class="table__name" href="<?= e(url('courses/view.php?id=' . (int) $row['course_id'])) ?>"><?= e($row['course_code']) ?></a>
                    </td>
                    <td class="text-3"><?= e($row['assessment']) ?></td>
                    <td class="num"><?= e(format_marks($row['marks'])) ?></td>
                    <td class="num"><?= grade_badge($row['letter_grade']) ?></td>
                    <td class="nowrap"><?= e($row['academic_year']) ?></td>
                    <td class="text-3"><?= e($row['semester']) ?></td>
                    <td class="actions">
                        <a class="btn btn--sm" href="<?= e(url('grades/view.php?id=' . (int) $row['id'])) ?>">Open</a>
                        <?php if ($canManage): ?>
                            <a class="iconbtn" href="<?= e(url('grades/form.php?id=' . (int) $row['id'])) ?>"
                               title="Edit grade" aria-label="Edit the <?= e($row['assessment']) ?> mark for <?= e($row['student_name']) ?>">
                                <?= icon('edit', 15) ?><span class="sr-only">Edit</span>
                            </a>
                            <?= render_delete_form($path, (int) $row['id'], 'Delete this grade?') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render_list_footer($page, $path, ['noun' => 'grade']);
}

layout_end();