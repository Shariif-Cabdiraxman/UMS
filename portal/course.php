<?php
/**
 * Student portal — one course on the signed-in student's record.
 *
 * The course id is accepted from the URL, but it is only used together with
 * the session student id. A course the student is not registered for is a
 * 404, not another student's record.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/portal.php';

$student   = require_student();
$studentId = (int) $student['id'];
$courseId  = query_int('id');

if ($courseId < 1) {
    not_found('No course was requested. Open a course from My courses.');
}

$course = student_course($studentId, $courseId);

if ($course === null) {
    not_found('That course is not part of your record.');
}

$grades = db_all(
    'SELECT `id`, `assessment`, `marks`, `letter_grade`, `updated_at`
       FROM `grades`
      WHERE `enrollment_id` = ?
      ORDER BY `assessment`',
    'i',
    [(int) $course['id']]
);

layout_start($course['course_code'], 'courses');

render_page_head([
    'eyebrow'  => $course['course_code'],
    'title'    => $course['course_name'],
    'subtitle' => $course['academic_year'] . ' · ' . $course['semester'],
    'meta'     => e((string) $course['credit_hours']) . ' credit hours · '
        . badge($course['status']),
    'actions'  => '<a class="btn btn--quiet" href="' . e(url('portal/courses.php')) . '">'
        . icon('arrow-left', 15) . '<span>Back to my courses</span></a>',
]);
?>

<div class="split split--wide">
    <div class="stackgap">
        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Course details</h2>
                    <p class="panel__sub">Your registration for this course</p>
                </div>
            </div>
            <div class="panel__body">
                <?php render_deflist([
                    ['term' => 'Course code', 'desc' => '<span class="table__id">' . e($course['course_code']) . '</span>'],
                    ['term' => 'Course name', 'desc' => e($course['course_name'])],
                    ['term' => 'Department', 'desc' => e($course['department_name'])],
                    ['term' => 'Lecturer', 'desc' => e($course['lecturer_name'] ?? 'Not assigned')],
                    ['term' => 'Credit hours', 'desc' => e((string) $course['credit_hours'])],
                    ['term' => 'Term', 'desc' => e($course['academic_year']) . ' · ' . e($course['semester'])],
                    ['term' => 'Enrollment status', 'desc' => badge($course['status'])],
                    ['term' => 'Registered on', 'desc' => e(format_db_date($course['enrollment_date']))],
                ]); ?>
            </div>
        </section>

        <?php if ($course['description'] !== null && trim((string) $course['description']) !== ''): ?>
            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title">Description</h2>
                    </div>
                </div>
                <div class="panel__body">
                    <div class="prose"><?= nl2br(e((string) $course['description'])) ?></div>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <div class="stackgap">
        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Marks</h2>
                    <p class="panel__sub">Assessments recorded for this course</p>
                </div>
            </div>
            <div class="panel__body panel__body--flush">
                <?php if ($grades === []): ?>
                    <?php render_empty_state('grades', 'No marks yet', 'Marks appear here after they are recorded.'); ?>
                <?php else: ?>
                    <div class="tablewrap" style="border:0;box-shadow:none;border-radius:0">
                        <table class="table">
                            <thead>
                                <tr>
                                    <?= th('Assessment') ?>
                                    <?= th('Marks', 'num') ?>
                                    <?= th('Grade', 'num') ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($grades as $grade): ?>
                                <tr>
                                    <td class="text-2"><?= e($grade['assessment']) ?></td>
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

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Course average</h2>
                </div>
            </div>
            <div class="panel__body">
                <?php if ($course['average_marks'] !== null): ?>
                    <p class="stat__value"><?= e(format_marks($course['average_marks'])) ?></p>
                    <p class="text-2 text-sm"><?= grade_badge(grade_letter((float) $course['average_marks'])) ?> current standing</p>
                <?php else: ?>
                    <p class="text-2 text-sm">No marks have been recorded for this course yet.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php layout_end(); ?>
