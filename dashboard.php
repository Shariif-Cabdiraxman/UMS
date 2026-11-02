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
    'students'         => table_count('students'),
    'students_active'  => table_count('students', '`status` = \'active\''),
    'faculties'        => table_count('faculties'),
    'departments'      => table_count('departments'),
    'lecturers'        => table_count('lecturers'),
    'lecturers_active' => table_count('lecturers', '`status` = \'active\''),
    'courses'          => table_count('courses'),
    'enrollments'      => table_count('enrollments'),
    'users'            => table_count('users', '`status` = \'active\''),
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
      GROUP BY `letter_grade`
      ORDER BY `letter_grade`'
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
$facultyDonut = [];
foreach ($facultyRows as $row) {
    $facultyChart[] = [
        'label' => $row['name'],
        'value' => (int) $row['n'],
        'href'  => url('students/index.php?faculty=' . (int) $row['id']),
    ];

    if ((int) $row['n'] > 0) {
        $facultyDonut[] = [
            'label' => $row['name'],
            'value' => (int) $row['n'],
        ];
    }
}

$maxFaculty = $facultyChart === [] ? 1 : max(array_column($facultyChart, 'value'));

// Recent enrollment activity, grouped by month using real enrollment dates.
$monthlyEnrollments = db_all(
    'SELECT DATE_FORMAT(`enrollment_date`, "%Y-%m") AS `month_key`,
            DATE_FORMAT(`enrollment_date`, "%b %Y") AS `month_label`,
            COUNT(*) AS `n`
       FROM `enrollments`
      WHERE `enrollment_date` >= DATE_SUB(CURDATE(), INTERVAL 7 MONTH)
      GROUP BY `month_key`, `month_label`
      ORDER BY `month_key` ASC'
);
// ---------------------------------------------------------------------
// Enrollment trend by academic year
// ---------------------------------------------------------------------

$trendRows = db_all(
    'SELECT `academic_year`, COUNT(*) AS `n`
       FROM `enrollments`
      GROUP BY `academic_year`
      ORDER BY `academic_year`'
);

$trend = [];
$aYearTotal = 0;
foreach ($trendRows as $row) {
    $parts = explode('/', (string) $row['academic_year']);
    $label = count($parts) === 2
        ? substr($parts[0], -2) . '/' . substr($parts[1], -2)
        : (string) $row['academic_year'];

    $trend[] = ['label' => $label, 'value' => (int) $row['n'], 'year' => (string) $row['academic_year']];
    $aYearTotal += (int) $row['n'];
}

$latestYear    = $trend === [] ? '' : (string) end($trend)['year'];
$isCurrentYear = $latestYear === $year;
$latest        = $trend === [] ? 0 : (int) end($trend)['value'];
$before        = count($trend) > 1 ? (int) $trend[count($trend) - 2]['value'] : 0;

// Comparing a year that is still being recorded against a finished one
// produces a misleading drop, so only show growth for completed years.
$growth = (!$isCurrentYear && $before > 0)
    ? round((($latest - $before) / $before) * 100, 1)
    : null;

$currentYearParts = explode('/', $year);
$currentYearLabel = count($currentYearParts) === 2
    ? substr($currentYearParts[0], -2) . '/' . substr($currentYearParts[1], -2)
    : $year;

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
// Records this month, for the calendar
// ---------------------------------------------------------------------

$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

$monthRows = db_all(
    'SELECT DISTINCT DAY(`enrollment_date`) AS `day`
       FROM `enrollments`
      WHERE `enrollment_date` BETWEEN ? AND ?',
    'ss',
    [$monthStart, $monthEnd]
);

$eventDays = array_map(static fn (array $row): int => (int) $row['day'], $monthRows);

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
$activeStudentShare = $totals['students'] > 0
    ? min(100, round(($totals['students_active'] / $totals['students']) * 100, 1))
    : 0;

layout_start('Dashboard', 'dashboard');
?>

<div class="dashboard-shell">

    <!-- Top Greeting Section in Fintech Editorial Style -->
    <section class="dash-greeting">
        <div class="dash-greeting__main">
            <h1 class="dash-greeting__title pagehead__title"><?= e($greeting) ?>, <?= e(explode(' ', $user['full_name'])[0]) ?> 👋</h1>
            <p class="dash-greeting__sub"><?= e($year) ?> &middot; <?= e($semester) ?> &middot; Registrar Overview</p>
        </div>
        <div class="dash-greeting__badge">
            <span class="status-pill status-pill--ok">
                <span class="status-pill__icon"><?= icon('check-circle', 16) ?></span>
                <span class="status-pill__text">
                    <strong>Academic Session Online</strong>
                    <small><?= e($year) ?> &middot; <?= e($semester) ?></small>
                </span>
            </span>
        </div>
    </section>

    <!-- Institution KPIs -->
    <section class="metrics-grid" aria-label="Institutional statistics">
        <article class="metric-card metric-card--students">
            <div class="metric-card__summary">
                <div class="metric-card__header">
                    <span class="metric-card__tag">TOTAL STUDENTS</span>
                    <span class="metric-card__icon"><?= icon('student', 17) ?></span>
                </div>
                <p class="metric-card__value"><?= e(format_number($totals['students'])) ?></p>
                <p class="metric-card__foot"><?= e(format_number($totals['students_active'])) ?> active students</p>
            </div>
            <span class="metric-card__ring" style="--ring-value: <?= e((string) $activeStudentShare) ?>%"
                  role="img" aria-label="<?= e((string) $activeStudentShare) ?> percent of students are active">
                <span><?= e((string) round($activeStudentShare)) ?>%</span>
            </span>
        </article>
        <article class="metric-card">
            <div class="metric-card__header">
                <span class="metric-card__tag">FACULTIES</span>
                <span class="metric-card__icon"><?= icon('faculty', 16) ?></span>
            </div>
            <p class="metric-card__value"><?= e((string) $totals['faculties']) ?></p>
            <p class="metric-card__foot">Academic faculties</p>
        </article>

        <article class="metric-card">
            <div class="metric-card__header">
                <span class="metric-card__tag">DEPARTMENTS</span>
                <span class="metric-card__icon"><?= icon('department', 16) ?></span>
            </div>
            <p class="metric-card__value"><?= e((string) $totals['departments']) ?></p>
            <p class="metric-card__foot">Degree & diploma programs</p>
        </article>

        <article class="metric-card">
            <div class="metric-card__header">
                <span class="metric-card__tag">COURSES</span>
                <span class="metric-card__icon"><?= icon('course', 16) ?></span>
            </div>
            <p class="metric-card__value"><?= e(format_number($totals['courses'])) ?></p>
            <p class="metric-card__foot"><?= e((string) $semesterCounts['Semester 1']) ?> Sem 1 &middot; <?= e((string) $semesterCounts['Semester 2']) ?> Sem 2</p>
        </article>

        <article class="metric-card">
            <div class="metric-card__header">
                <span class="metric-card__tag">ENROLLMENTS</span>
                <span class="metric-card__icon"><?= icon('enrollment', 16) ?></span>
            </div>
            <p class="metric-card__value"><?= e(format_number($termEnrollments)) ?></p>
            <p class="metric-card__foot">Recorded for <?= e($semester) ?></p>
        </article>
    </section>

    <!-- 4 Pastel Quick Action Cards Grid (Direct Match of Reference Image) -->
    <section class="quick-cards-grid" aria-label="Campus Quick Access">
        <a class="quick-card quick-card--sage" href="<?= e(url('students/index.php')) ?>">
            <div class="quick-card__icon-wrap">
                <?= icon('student', 18) ?>
            </div>
            <h3 class="quick-card__title">Student Records</h3>
            <p class="quick-card__desc">Review enrollment files, contact directories, and active status.</p>
            <span class="quick-card__link">
                <span>View Directory</span>
                <?= icon('arrow-right', 13) ?>
            </span>
        </a>

        <a class="quick-card quick-card--sky" href="<?= e(url('courses/index.php')) ?>">
            <div class="quick-card__icon-wrap">
                <?= icon('course', 18) ?>
            </div>
            <h3 class="quick-card__title">Course Catalog</h3>
            <p class="quick-card__desc">Browse credit allocations, syllabus mappings, and lectures.</p>
            <span class="quick-card__link">
                <span>Browse Courses</span>
                <?= icon('arrow-right', 13) ?>
            </span>
        </a>

        <a class="quick-card quick-card--lavender" href="<?= e(url('grades/index.php')) ?>">
            <div class="quick-card__icon-wrap">
                <?= icon('grades', 18) ?>
            </div>
            <h3 class="quick-card__title">Grade Ledger</h3>
            <p class="quick-card__desc">Enter term marks, verify averages, and calculate final GPAs.</p>
            <span class="quick-card__link">
                <span>Open Gradebook</span>
                <?= icon('arrow-right', 13) ?>
            </span>
        </a>

        <a class="quick-card quick-card--amber" href="<?= e(url('announcements/index.php')) ?>">
            <div class="quick-card__icon-wrap">
                <?= icon('announcement', 18) ?>
            </div>
            <h3 class="quick-card__title">Campus Bulletins</h3>
            <p class="quick-card__desc">Draft, broadcast, and archive notices for faculty & students.</p>
            <span class="quick-card__link">
                <span>Read Notices</span>
                <?= icon('arrow-right', 13) ?>
            </span>
        </a>
    </section>

    <!-- Main Workspace (2-Column Grid: Tables & Charts on left, Widgets & Feeds on right) -->
    <div class="dash-workspace">
        <!-- Left Main Column -->
        <div class="dash-workspace__left">

            <!-- Enrollment and academic performance overview -->
            <section class="dash-card">
                <div class="dash-card__header">
                    <div>
                        <h2 class="dash-card__title">Performance Overview</h2>
                        <p class="dash-card__sub">Monthly enrollment activity and overall pass rate</p>
                    </div>
                    <span class="chart-period"><?= e($year) ?> · <?= e($semester) ?></span>
                </div>
                <div class="dash-card__body performance-body">
                    <div class="performance-summary">
                        <span class="performance-summary__label">Overall pass rate</span>
                        <strong><?= e(number_format($passRate, 1)) ?><small>%</small></strong>
                        <span class="performance-summary__note"><i aria-hidden="true"></i> Grades A–C · <?= e(number_format($gradeTotal)) ?> marks</span>
                    </div>
                    <?php render_area_chart($monthlyEnrollments, 'Monthly enrollment activity'); ?>
                </div>
            </section>

            <!-- Retain the academic-year trend from the previous dashboard. -->
            <section class="dash-card">
                <div class="dash-card__header">
                    <div>
                        <h2 class="dash-card__title">Enrollment Activity</h2>
                        <p class="dash-card__sub">Registrations across academic years</p>
                    </div>
                    <span class="chart-period"><?= e((string) count($trend)) ?> academic years</span>
                </div>
                <div class="dash-card__body">
                    <p class="dash-chart-value"><?= e(format_number($aYearTotal)) ?></p>
                    <p class="dash-chart-meta">
                        <?php if ($isCurrentYear): ?>
                            <span class="stat__delta stat__delta--info"><?= e($currentYearLabel) ?> in progress</span>
                        <?php elseif ($growth !== null && $growth > 0): ?>
                            <span class="stat__delta stat__delta--up"><?= icon('trending', 10) ?><?= e(number_format($growth, 1)) ?>%</span>
                        <?php elseif ($growth !== null && $growth < 0): ?>
                            <span class="stat__delta stat__delta--down"><?= e(number_format($growth, 1)) ?>%</span>
                        <?php endif; ?>
                        total enrollments recorded
                    </p>
                    <?php render_line_chart($trend, ['empty' => 'Enrollments will appear here as they are recorded.']); ?>
                </div>
            </section>

            <!-- Latest Enrollments Table (like Recent Transactions in reference) -->
            <section class="dash-card">
                <div class="dash-card__header">
                    <div>
                        <h2 class="dash-card__title">Recent Enrollments</h2>
                        <p class="dash-card__sub">Latest course registration activity</p>
                    </div>
                    <a class="dash-card__action" href="<?= e(url('enrollments/index.php')) ?>">
                        <span>View all</span>
                        <?= icon('arrow-right', 13) ?>
                    </a>
                </div>
                <div class="dash-card__body dash-card__body--flush">
                    <?php if ($recentEnrollments === []): ?>
                        <?php render_empty_state('enrollment', 'No enrollments recorded', 'Recent enrollments will show here.'); ?>
                    <?php else: ?>
                        <div class="tablewrap tablewrap--borderless">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">Student</th>
                                        <th scope="col">Course Details</th>
                                        <th scope="col">Term</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recentEnrollments as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="table__person">
                                                <?= render_avatar($row['first_name'], $row['last_name'], url('students/view.php?id=' . urlencode((string) $row['student_no']))) ?>
                                                <div class="table__persontext">
                                                    <a class="table__name" href="<?= e(url('students/view.php?id=' . urlencode($row['student_no']))) ?>">
                                                        <?= e(full_name($row['first_name'], $row['last_name'])) ?>
                                                    </a>
                                                    <span class="table__sub"><?= e($row['student_no']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cellstack">
                                                <strong><?= e($row['course_code']) ?></strong>
                                                <span><?= e($row['course_name']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cellstack">
                                                <span><?= e($row['academic_year']) ?></span>
                                                <small class="text-3"><?= e($row['semester']) ?></small>
                                            </div>
                                        </td>
                                        <td><?= badge($row['status']) ?></td>
                                        <td class="nowrap text-3"><?= e(format_db_date($row['enrollment_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Most Recently Registered Students -->
            <section class="dash-card">
                <div class="dash-card__header">
                    <div>
                        <h2 class="dash-card__title">Most Recently Registered</h2>
                        <p class="dash-card__sub">Latest additions to the university student body</p>
                    </div>
                    <a class="dash-card__action" href="<?= e(url('students/index.php')) ?>">
                        <span>All students</span>
                        <?= icon('arrow-right', 13) ?>
                    </a>
                </div>
                <div class="dash-card__body dash-card__body--flush">
                    <div class="tablewrap tablewrap--borderless">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Student ID</th>
                                    <th scope="col">Intake Year</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="actions">File</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentStudents as $row): ?>
                                <tr>
                                    <td>
                                        <div class="table__person">
                                            <?= render_avatar($row['first_name'], $row['last_name']) ?>
                                            <div class="table__persontext">
                                                <a class="table__name" href="<?= e(url('students/view.php?id=' . urlencode($row['student_no']))) ?>">
                                                    <?= e(full_name($row['first_name'], $row['last_name'])) ?>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code class="code-pill"><?= e($row['student_no']) ?></code></td>
                                    <td><?= e((string) $row['enrollment_year']) ?></td>
                                    <td><?= badge($row['status']) ?></td>
                                    <td class="actions">
                                        <a class="btn btn--quiet btn--sm" href="<?= e(url('students/view.php?id=' . urlencode($row['student_no']))) ?>">
                                            <span>Open</span>
                                            <?= icon('arrow-right', 12) ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <!-- Right Aside Column -->
        <aside class="dash-workspace__right">

            <!-- University Card Mockup (Inspired directly by the Pacbuck Card in the reference!) -->
            <div class="campus-card-widget">
                <div class="campus-card-widget__card">
                    <div class="campus-card-widget__top">
                        <span class="campus-card-widget__chip"></span>
                        <span class="campus-card-widget__brand">CAMPUS MANAGER</span>
                    </div>
                    <div class="campus-card-widget__number">•••• •••• •••• <?= e(substr((string) ($user['id'] ?? '1000'), -4)) ?></div>
                    <div class="campus-card-widget__bottom">
                        <div>
                            <span class="campus-card-widget__label">OFFICIAL INTAKE</span>
                            <span class="campus-card-widget__holder"><?= e($user['full_name'] ?? 'Registrar Staff') ?></span>
                        </div>
                        <div class="campus-card-widget__badge"><?= icon('shield', 14) ?> Active</div>
                    </div>
                </div>
                <div class="campus-card-widget__meta">
                    <h4>Student Identification Card</h4>
                    <p>Digital verification credential with encrypted academic hash.</p>
                    <a class="btn btn--sm btn--primary btn--block" href="<?= e(url('students/index.php')) ?>">
                        <?= icon('student', 15) ?>
                        <span>Manage Student Cards</span>
                    </a>
                </div>
            </div>

            <!-- Active Students by Faculty -->
            <section class="dash-card">
                <div class="dash-card__header">
                    <div>
                        <h2 class="dash-card__title">Active by Faculty</h2>
                        <p class="dash-card__sub">Enrollment distribution</p>
                    </div>
                    <a class="dash-card__action" href="<?= e(url('faculties/index.php')) ?>">View</a>
                </div>
                <div class="dash-card__body">
                    <?php
                    render_bar_chart($facultyChart, [
                        'max'   => $maxFaculty,
                        'noun'  => 'student',
                        'empty' => 'No faculties recorded.',
                    ]);
                    ?>
                </div>
            </section>

            <section class="dash-card">
                <div class="dash-card__header">
                    <div>
                        <h2 class="dash-card__title">Student Distribution</h2>
                        <p class="dash-card__sub">Active students across faculties</p>
                    </div>
                </div>
                <div class="dash-card__body">
                    <?php render_donut_chart($facultyDonut, [
                        'center' => format_number($totals['students_active']),
                        'label'  => 'active students',
                        'empty'  => 'No active students have been recorded yet.',
                    ]); ?>
                </div>
            </section>

            <!-- Monthly columns reuse the dashboard's fetched enrollment totals. -->
            <section class="dash-card">
                <div class="dash-card__header">
                    <div>
                        <h2 class="dash-card__title">Enrollment Volume</h2>
                        <p class="dash-card__sub">Recent monthly activity</p>
                    </div>
                    <span class="chart-period">Last 8 months</span>
                </div>
                <div class="dash-card__body">
                    <p class="column-chart__total"><?= e(number_format((int) $totals['enrollments'])) ?><span>enrollments recorded</span></p>
                    <?php render_area_chart($monthlyEnrollments, 'Monthly enrollment volume'); ?>
                </div>
            </section>

            <!-- Grade Distribution -->
            <section class="dash-card">
                <div class="dash-card__header">
                    <div>
                        <h2 class="dash-card__title">Grade Distribution</h2>
                        <p class="dash-card__sub">All recorded marks</p>
                    </div>
                    <a class="dash-card__action" href="<?= e(url('grades/index.php')) ?>">Ledger</a>
                </div>
                <div class="dash-card__body">
                    <?php render_grade_donut($distribution, $gradeTotal, $passRate); ?>

                    <div class="dash-divider"></div>

                    <?php
                    render_figures([
                        ['value' => number_format($gradeAverage, 2), 'label' => 'Average mark'],
                        ['value' => $passRate . '%', 'label' => 'Pass rate (A to C)'],
                        ['value' => number_format($gradeTotal), 'label' => 'Marks recorded'],
                    ]);
                    ?>
                </div>
            </section>

            <!-- Latest Published Announcements -->
            <section class="dash-card">
                <div class="dash-card__header">
                    <div>
                        <h2 class="dash-card__title">Campus Notices</h2>
                        <p class="dash-card__sub">Published announcements</p>
                    </div>
                    <a class="dash-card__action" href="<?= e(url('announcements/index.php')) ?>">All</a>
                </div>
                <div class="dash-card__body">
                    <?php if ($recentAnnouncements === []): ?>
                        <?php render_empty_state('announcement', 'Nothing published', 'Published notices appear here.'); ?>
                    <?php else: ?>
                        <div class="notices-timeline">
                            <?php foreach ($recentAnnouncements as $row): ?>
                                <article class="notices-item">
                                    <div class="notices-item__indicator"></div>
                                    <div class="notices-item__content">
                                        <h4 class="notices-item__title">
                                            <a href="<?= e(url('announcements/view.php?id=' . (int) $row['id'])) ?>"><?= e($row['title']) ?></a>
                                        </h4>
                                        <p class="notices-item__meta">
                                            <?= e(time_ago($row['published_at'])) ?> &middot; <?= e($row['full_name']) ?>
                                        </p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="dash-card">
                <div class="dash-card__header">
                    <div>
                        <h2 class="dash-card__title">This Month</h2>
                        <p class="dash-card__sub">Enrollment activity calendar</p>
                    </div>
                </div>
                <div class="dash-card__body">
                    <div class="month-overview">
                        <span class="month-overview__date"><strong><?= e(date('j')) ?></strong><span><?= e(date('D')) ?></span></span>
                        <span class="month-overview__summary"><strong><?= e((string) count($eventDays)) ?> enrollment dates</strong><span>Current month</span></span>
                    </div>
                    <?php render_mini_calendar((int) date('Y'), (int) date('n'), $eventDays); ?>
                </div>
            </section>

            <!-- Help / Support Card (Matching "Need Help? 24/7" in Pacbuck Reference) -->
            <div class="help-card">
                <div class="help-card__icon"><?= icon('headphones', 22) ?></div>
                <h4 class="help-card__title">Need Registrar Support?</h4>
                <p class="help-card__sub">Academic operations, grade disputes, and database records support is active.</p>
                <a class="btn btn--block btn--sm btn--primary" href="mailto:registrar@hagmah.edu">
                    <?= icon('mail', 15) ?>
                    <span>Contact Registrar Desk</span>
                </a>
            </div>

        </aside>
    </div>

</div>

<?php layout_end(['scripts' => ['assets/js/charts.js']]); ?>
