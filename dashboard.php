<?php
/**
 * Dashboard.
 *
 * The screen a registrar sees on arrival, so it answers the questions that
 * actually get asked: how many students are we carrying, which departments
 * are they in, how is this term going, and what is happening right now.
 *
 * Every figure on this page is a separate aggregate query rather than one
 * large join, so a slow sub-query can only ever affect its own tile.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$user = current_user();

$year     = current_academic_year();
$semester = current_semester();

// ---------------------------------------------------------------------
// Headline figures
// ---------------------------------------------------------------------

$totals = [
    'students'      => table_count('students'),
    'students_active' => table_count('students', '`status` = \'active\''),
    'faculties'     => table_count('faculties'),
    'departments'   => table_count('departments'),
    'lecturers'     => table_count('lecturers'),
    'lecturers_active' => table_count('lecturers', '`status` = \'active\''),
    'courses'       => table_count('courses'),
    'enrollments'   => table_count('enrollments'),
    'users'         => table_count('users', '`status` = \'active\''),
];

$termEnrollments = (int) db_value(
    'SELECT COUNT(*) FROM `enrollments` WHERE `academic_year` = ? AND `semester` = ?',
    'ss',
    [$year, $semester]
);

// ---------------------------------------------------------------------
// Grade distribution, across every recorded mark
// ---------------------------------------------------------------------

$gradeRows = db_all(
    'SELECT `letter_grade` AS `letter`,
            COUNT(*)         AS `n`,
            ROUND(AVG(`marks`), 2) AS `avg_marks`
       FROM `grades`
      GROUP BY `letter_grade`'
);

$distribution = [];
$gradeTotal   = 0;
$gradeAverage = 0.0;
$byLetter = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];

foreach ($gradeRows as $row) {
    $byLetter[$row['letter']] = (int) $row['n'];
    $gradeTotal              += (int) $row['n'];
    $distribution[] = [
        'letter' => $row['letter'],
        'count'  => (int) $row['n'],
        'avg'    => $row['avg_marks'] === null ? null : (float) $row['avg_marks'],
    ];
}

if ($gradeTotal > 0) {
    $gradeAverage = (float) db_value('SELECT ROUND(AVG(`marks`), 2) FROM `grades`') ?? 0.0;
}

$passed = $byLetter['A'] + $byLetter['B'] + $byLetter['C'];
$passRate = $gradeTotal > 0 ? round(($passed / $gradeTotal) * 100, 1) : 0.0;

// ---------------------------------------------------------------------
// Students by faculty
// ---------------------------------------------------------------------

$facultyRows = db_all(
    'SELECT f.`id`, f.`name`,
            COUNT(s.`id`) AS `n`
       FROM `faculties` f
       LEFT JOIN `students` s
              ON s.`faculty_id` = f.`id`
             AND s.`status` = \'active\'
      GROUP BY f.`id`, f.`name`
      ORDER BY `n` DESC, f.`name` ASC'
);

$facultyChart = [];
foreach ($facultyRows as $row) {
    $facultyChart[] = [
        'label' => $row['name'],
        'value' => (int) $row['n'],
        'href'  => url('students/index.php?faculty=' . (int) $row['id']),
    ];
}

$maxFaculty = $facultyChart === [] ? 1 : max(array_column($facultyChart, 'value'));

// ---------------------------------------------------------------------
// Enrollments by faculty, for the running term
// ---------------------------------------------------------------------

$termRows = db_all(
    'SELECT f.`name`, COUNT(e.`id`) AS `n`
       FROM `enrollments` e
       JOIN `courses` c     ON c.`id` = e.`course_id`
       JOIN `departments` d ON d.`id` = c.`department_id`
       JOIN `faculties` f   ON f.`id` = d.`faculty_id`
      WHERE e.`academic_year` = ? AND e.`semester` = ?
      GROUP BY f.`id`, f.`name`
      ORDER BY `n` DESC',
    'ss',
    [$year, $semester]
);

$termChart = [];
foreach ($termRows as $row) {
    $termChart[] = [
        'label' => $row['name'],
        'value' => (int) $row['n'],
        'note'  => null,
    ];
}

// ---------------------------------------------------------------------
// Courses by semester
// ---------------------------------------------------------------------

$semesterRows = db_all(
    'SELECT `semester`, COUNT(*) AS `n` FROM `courses` GROUP BY `semester`'
);

$semesterCounts = ['Semester 1' => 0, 'Semester 2' => 0];
foreach ($semesterRows as $row) {
    $semesterCounts[$row['semester']] = (int) $row['n'];
}

// ---------------------------------------------------------------------
// Recent activity
// ---------------------------------------------------------------------

$recentEnrollments = db_all(
    'SELECT e.`id`, e.`academic_year`, e.`semester`, e.`status`, e.`enrollment_date`,
            s.`student_no`, s.`first_name`, s.`last_name`,
            c.`course_code`, c.`course_name`
       FROM `enrollments` e
       JOIN `students` s ON s.`id` = e.`student_id`
       JOIN `courses` c  ON c.`id` = e.`course_id`
      ORDER BY e.`enrollment_date` DESC, e.`id` DESC
      LIMIT 6'
);

$recentAnnouncements = db_all(
    'SELECT a.`id`, a.`title`, a.`published_at`, a.`status`, u.`full_name`
       FROM `announcements` a
       JOIN `users` u ON u.`id` = a.`author_id`
      WHERE a.`status` = \'published\'
      ORDER BY a.`published_at` DESC
      LIMIT 4'
);

// Students who joined most recently, which is what a registrar checks after
// registering a new intake.
$recentStudents = db_all(
    'SELECT `id`, `student_no`, `first_name`, `last_name`, `enrollment_year`, `status`
       FROM `students`
      ORDER BY `enrollment_date` DESC, `id` DESC
      LIMIT 5'
);

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

layout_start('Dashboard', 'dashboard');
?>

<?php render_page_head([
    'eyebrow' => 'Registrar overview',
    'title'   => $greeting . ', ' . explode(' ', $user['full_name'])[0],
    'subtitle' => 'Here is where the university record stands today.',
    'meta'    => e(date('l, j F Y')) . ' &middot; ' . e($year) . ' &middot; ' . e($semester),
    'actions' => '<a class="btn btn--primary" href="' . e(url('students/index.php')) . '">' . icon('student', 16) . '<span>Student records</span></a>',
]); ?>

<section class="stats" aria-label="Headline figures">
    <article class="stat">
        <div class="stat__top">
            <span class="stat__icon"><?= icon('student', 16) ?></span>
            <p class="stat__label">Students</p>
        </div>
        <p class="stat__value"><?= e(format_number($totals['students'])) ?></p>
        <p class="stat__foot">
            <span class="stat__delta stat__delta--up"><?= e(format_number($totals['students_active'])) ?> active</span>
            <span>of <?= e(format_number($totals['students'])) ?> total</span>
        </p>
    </article>

    <article class="stat">
        <div class="stat__top">
            <span class="stat__icon"><?= icon('faculty', 16) ?></span>
            <p class="stat__label">Structure</p>
        </div>
        <p class="stat__value"><?= e((string) $totals['faculties']) ?> <span class="text-3" style="font-size:.5em">/ <?= e((string) $totals['departments']) ?></span></p>
        <p class="stat__foot"><?= e(ucfirst((string) $totals['faculties'])) ?> faculties, <?= e((string) $totals['departments']) ?> departments</p>
    </article>

    <article class="stat">
        <div class="stat__top">
            <span class="stat__icon"><?= icon('course', 16) ?></span>
            <p class="stat__label">Courses</p>
        </div>
        <p class="stat__value"><?= e(format_number($totals['courses'])) ?></p>
        <p class="stat__foot">
            <?= e((string) $semesterCounts['Semester 1']) ?> in Semester 1 &middot;
            <?= e((string) $semesterCounts['Semester 2']) ?> in Semester 2
        </p>
    </article>

    <article class="stat">
        <div class="stat__top">
            <span class="stat__icon"><?= icon('enrollment', 16) ?></span>
            <p class="stat__label">Enrollments this term</p>
        </div>
        <p class="stat__value"><?= e(format_number($termEnrollments)) ?></p>
        <p class="stat__foot">
            <span><?= e(format_number($totals['enrollments'])) ?> recorded in total</span>
        </p>
    </article>
</section>

<div class="split split--wide mb-3">

    <section class="panel">
        <div class="panel__head">
            <div class="panel__titles">
                <h2 class="panel__title">Active students by faculty</h2>
                <p class="panel__sub">Students with the status <em>active</em>, grouped by faculty</p>
            </div>
            <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('faculties/index.php')) ?>">View faculties</a>
        </div>
        <div class="panel__body">
            <?php
            render_bar_chart($facultyChart, [
                'max'   => $maxFaculty,
                'noun'  => 'student',
                'empty' => 'No faculties have been recorded yet.',
            ]);
            ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel__head">
            <div class="panel__titles">
                <h2 class="panel__title">Grade distribution</h2>
                <p class="panel__sub">Every recorded mark, all terms</p>
            </div>
            <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('grades/index.php')) ?>">All grades</a>
        </div>
        <div class="panel__body">
            <?php render_grade_distribution($distribution, $gradeTotal); ?>

            <hr>

            <?php
            render_figures([
                ['value' => number_format($gradeAverage, 2), 'label' => 'Average mark'],
                ['value' => $passRate . '%', 'label' => 'Pass rate (A to C)'],
                ['value' => number_format($gradeTotal), 'label' => 'Marks recorded'],
            ]);
            ?>
        </div>
    </section>
</div>

<div class="split split--wide mb-3">

    <section class="panel">
        <div class="panel__head">
            <div class="panel__titles">
                <h2 class="panel__title">Latest enrollments</h2>
                <p class="panel__sub">The six most recent records added</p>
            </div>
            <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('enrollments/index.php')) ?>">All enrollments</a>
        </div>
        <div class="panel__body panel__body--flush">
            <?php if ($recentEnrollments === []): ?>
                <?php render_empty_state('enrollment', 'No enrollments yet', 'Enrollments appear here as soon as they are recorded.'); ?>
            <?php else: ?>
                <div class="tablewrap" style="border:0;box-shadow:none;border-radius:0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Student</th>
                                <th scope="col">Course</th>
                                <th scope="col">Term</th>
                                <th scope="col">Status</th>
                                <th scope="col">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentEnrollments as $row): ?>
                            <tr>
                                <td>
                                    <span class="table__person">
                                        <?= render_avatar($row['first_name'], $row['last_name'], url('students/view.php?id=' . urlencode((string) $row['student_no']))) ?>
                                        <span class="table__persontext">
                                            <a class="table__name" href="<?= e(url('students/view.php?id=' . urlencode($row['student_no']))) ?>">
                                                <?= e(full_name($row['first_name'], $row['last_name'])) ?>
                                            </a>
                                            <span class="table__sub table__id"><?= e($row['student_no']) ?></span>
                                        </span>
                                    </span>
                                </td>
                                <td>
                                    <span class="cellstack">
                                        <strong><?= e($row['course_code']) ?></strong>
                                        <span><?= e($row['course_name']) ?></span>
                                    </span>
                                </td>
                                <td class="nowrap">
                                    <span class="cellstack">
                                        <strong><?= e($row['academic_year']) ?></strong>
                                        <span><?= e($row['semester']) ?></span>
                                    </span>
                                </td>
                                <td><?= badge($row['status']) ?></td>
                                <td class="nowrap text-2"><?= e(format_db_date($row['enrollment_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="stackgap">

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Enrollments by faculty</h2>
                    <p class="panel__sub"><?= e($semester) ?> &middot; <?= e($year) ?></p>
                </div>
            </div>
            <div class="panel__body">
                <?php
                render_bar_chart($termChart, [
                    'noun'  => 'enrollment',
                    'empty' => 'No enrollments have been recorded for this term yet.',
                ]);
                ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Notices</h2>
                    <p class="panel__sub">Published announcements</p>
                </div>
                <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('announcements/index.php')) ?>">All</a>
            </div>
            <div class="panel__body">
                <?php if ($recentAnnouncements === []): ?>
                    <?php render_empty_state('announcement', 'Nothing published', 'Published announcements will be listed here.'); ?>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($recentAnnouncements as $row): ?>
                            <article class="timeline__item">
                                <div class="timeline__body">
                                    <p class="timeline__title">
                                        <a href="<?= e(url('announcements/view.php?id=' . (int) $row['id'])) ?>"><?= e($row['title']) ?></a>
                                    </p>
                                    <p class="timeline__meta">
                                        <?= e(time_ago($row['published_at'])) ?> &middot; <?= e($row['full_name']) ?>
                                    </p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<section class="panel">
    <div class="panel__head">
        <div class="panel__titles">
            <h2 class="panel__title">Most recently registered</h2>
            <p class="panel__sub">Students added to the register most recently</p>
        </div>
        <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('students/index.php')) ?>">All students</a>
    </div>
    <div class="panel__body panel__body--flush">
        <div class="tablewrap" style="border:0;box-shadow:none;border-radius:0">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Intake</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="actions">Record</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentStudents as $row): ?>
                    <tr>
                        <td>
                            <span class="table__person">
                                <?= render_avatar($row['first_name'], $row['last_name']) ?>
                                <span class="table__persontext">
                                    <a class="table__name" href="<?= e(url('students/view.php?id=' . urlencode($row['student_no']))) ?>">
                                        <?= e(full_name($row['first_name'], $row['last_name'])) ?>
                                    </a>
                                </span>
                            </span>
                        </td>
                        <td class="table__id"><?= e($row['student_no']) ?></td>
                        <td><?= e((string) $row['enrollment_year']) ?></td>
                        <td><?= badge($row['status']) ?></td>
                        <td class="actions">
                            <a class="btn btn--sm" href="<?= e(url('students/view.php?id=' . urlencode($row['student_no']))) ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php layout_end(['scripts' => ['assets/js/charts.js']]); ?>
