<?php
/**
 * Student portal — overview.
 *
 * Everything on this page belongs to the signed-in student. The record is
 * loaded from the session id, never from a query parameter.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/portal.php';

$student   = require_student();
$studentId = (int) $student['id'];
$programme = student_programme($student);
$name      = full_name($student['first_name'], $student['last_name']);

$currentCourses = student_enrollments($studentId, current_academic_year(), current_semester());
$summary        = student_grade_summary($studentId);
$completed      = student_completed_credits($studentId);
$recentGrades   = array_slice(student_grades($studentId), 0, 6);
$notices        = student_notices(3);

layout_start('My dashboard', 'dashboard');

render_page_head([
    'eyebrow'  => $programme['faculty_name'] ?? 'Student portal',
    'title'    => 'Welcome, ' . $student['first_name'],
    'subtitle' => 'Your academic record at a glance.',
    'meta'     => 'Student ' . e($student['student_no'])
        . ' &middot; ' . e($programme['department_name'] ?? 'No department')
        . ' &middot; ' . badge($student['status']),
    'actions'  => '<a class="btn btn--quiet" href="' . e(url('portal/profile.php')) . '">'
        . icon('student', 15) . '<span>My full profile</span></a>',
]);
?>

<div class="stackgap">
    <section class="recordhead">
        <span class="recordhead__mark"><?= icon('student', 26) ?></span>
        <div class="recordhead__text">
            <h2 class="recordhead__title"><?= e($name) ?></h2>
            <p class="recordhead__meta">
                <span><?= e($student['student_no']) ?></span>
                <span><?= e($programme['department_name'] ?? 'No department') ?></span>
                <span><?= e($programme['faculty_name'] ?? 'No faculty') ?></span>
                <span><?= badge($student['status']) ?></span>
            </p>
        </div>
    </section>

    <section class="stats stats--3" aria-label="Academic summary">
        <article class="stat">
            <div class="stat__top">
                <span class="stat__icon"><?= icon('course', 16) ?></span>
                <p class="stat__label">This term</p>
            </div>
            <p class="stat__value"><?= e(format_number(count($currentCourses))) ?></p>
            <p class="stat__foot"><?= e(current_academic_year()) ?> · <?= e(current_semester()) ?></p>
        </article>
        <article class="stat">
            <div class="stat__top">
                <span class="stat__icon stat__icon--blue"><?= icon('award', 16) ?></span>
                <p class="stat__label">Credits completed</p>
            </div>
            <p class="stat__value"><?= e(format_number($completed)) ?></p>
            <p class="stat__foot">Across completed courses</p>
        </article>
        <article class="stat">
            <div class="stat__top">
                <span class="stat__icon stat__icon--peach"><?= icon('grades', 16) ?></span>
                <p class="stat__label">Average mark</p>
            </div>
            <p class="stat__value">
                <?= $summary['average'] !== null ? e(format_marks($summary['average'])) : '—' ?>
            </p>
            <p class="stat__foot">
                <?php if ($summary['average'] !== null): ?>
                    <?= grade_badge(grade_letter((float) $summary['average'])) ?>
                    <span><?= e(format_number($summary['grade_count'])) ?> marks recorded</span>
                <?php else: ?>
                    <span>No marks yet</span>
                <?php endif; ?>
            </p>
        </article>
    </section>

    <div class="split split--wide">
        <div class="stackgap">
            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title">Current courses</h2>
                        <p class="panel__sub"><?= e(current_academic_year()) ?> · <?= e(current_semester()) ?></p>
                    </div>
                    <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('portal/courses.php')) ?>">All courses</a>
                </div>
                <div class="panel__body">
                    <?php
                    $courseItems = [];
                    foreach ($currentCourses as $course) {
                        $courseItems[] = [
                            'label' => $course['course_name'],
                            'meta'  => $course['course_code'] . ' · ' . $course['credit_hours'] . ' credits'
                                . ($course['lecturer_name'] !== null ? ' · ' . $course['lecturer_name'] : '')
                                . ($course['average_marks'] !== null ? ' · avg ' . format_marks($course['average_marks']) : ''),
                            'href'  => url('portal/course.php?id=' . (int) $course['course_id']),
                            'trail' => badge($course['status']),
                        ];
                    }
                    render_related_list(
                        $courseItems,
                        'No courses are registered for the current term. Open My courses to see your full history.',
                        'course'
                    );
                    ?>
                </div>
            </section>

            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title">Recent marks</h2>
                        <p class="panel__sub">The latest assessments recorded on your record</p>
                    </div>
                    <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('portal/grades.php')) ?>">All grades</a>
                </div>
                <div class="panel__body panel__body--flush">
                    <?php if ($recentGrades === []): ?>
                        <?php render_empty_state('grades', 'No marks yet', 'Marks appear here after the registrar records them.', ''); ?>
                    <?php else: ?>
                        <div class="tablewrap" style="border:0;box-shadow:none;border-radius:0">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <?= th('Course') ?>
                                        <?= th('Assessment') ?>
                                        <?= th('Marks', 'num') ?>
                                        <?= th('Grade', 'num') ?>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recentGrades as $grade): ?>
                                    <tr>
                                        <td>
                                            <a class="table__name" href="<?= e(url('portal/course.php?id=' . (int) $grade['course_id'])) ?>">
                                                <?= e($grade['course_code']) ?>
                                            </a>
                                            <span class="table__sub"><?= e($grade['academic_year']) ?> · <?= e($grade['semester']) ?></span>
                                        </td>
                                        <td class="text-3"><?= e($grade['assessment']) ?></td>
                                        <td class="num"><?= e(format_marks($grade['marks'])) ?></td>
                                        <td class="num"><?= grade_badge($grade['letter_grade']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="stackgap">
            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title">Programme</h2>
                        <p class="panel__sub">Your registered programme</p>
                    </div>
                </div>
                <div class="panel__body">
                    <?php render_deflist([
                        ['term' => 'Faculty', 'desc' => e($programme['faculty_name'] ?? 'Not assigned')],
                        ['term' => 'Department', 'desc' => e($programme['department_name'] ?? 'Not assigned')],
                        ['term' => 'Enrolled', 'desc' => e((string) $student['enrollment_year']) . ' · ' . e(format_db_date($student['enrollment_date']))],
                        ['term' => 'Status', 'desc' => badge($student['status'])],
                    ]); ?>
                </div>
            </section>

            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title">Latest notices</h2>
                        <p class="panel__sub">Published by the registrar&rsquo;s office</p>
                    </div>
                    <a class="panel__action btn btn--quiet btn--sm" href="<?= e(url('portal/announcements.php')) ?>">View all</a>
                </div>
                <div class="panel__body">
                    <?php
                    $noticeItems = [];
                    foreach ($notices as $notice) {
                        $noticeItems[] = [
                            'label' => $notice['title'],
                            'meta'  => truncate((string) $notice['content'], 90),
                            'href'  => url('portal/announcement.php?id=' . (int) $notice['id']),
                            'trail' => e(time_ago($notice['published_at'])),
                        ];
                    }
                    render_related_list(
                        $noticeItems,
                        'No notices have been published yet.',
                        'announcement'
                    );
                    ?>
                </div>
            </section>
        </div>
    </div>
</div>

<?php layout_end(); ?>
