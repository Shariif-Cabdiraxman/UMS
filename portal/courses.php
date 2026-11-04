<?php
/**
 * Student portal — every course on the signed-in student's record.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/portal.php';

$student     = require_student();
$enrollments = student_enrollments((int) $student['id']);

$groups = [];
foreach ($enrollments as $enrollment) {
    $key = $enrollment['academic_year'] . '|' . $enrollment['semester'];
    $groups[$key][] = $enrollment;
}

layout_start('My courses', 'courses');

render_page_head([
    'eyebrow'  => 'Academic record',
    'title'    => 'My courses',
    'subtitle' => 'Every course registered against student ' . $student['student_no'] . '.',
    'meta'     => e(format_number(count($enrollments))) . ' '
        . (count($enrollments) === 1 ? 'registration' : 'registrations'),
    'actions'  => '<a class="btn btn--quiet" href="' . e(url('portal/grades.php')) . '">'
        . icon('grades', 15) . '<span>My grades</span></a>',
]);
?>

<div class="stackgap">
    <?php if ($groups === []): ?>
        <section class="panel">
            <div class="panel__body">
                <?php render_empty_state('course', 'No courses yet', 'Courses appear here once the registrar registers you for a term.'); ?>
            </div>
        </section>
    <?php else: ?>
        <?php foreach ($groups as $group): ?>
            <?php $first = $group[0]; ?>
            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title"><?= e($first['academic_year']) ?> · <?= e($first['semester']) ?></h2>
                        <p class="panel__sub"><?= e(format_number(count($group))) ?> <?= count($group) === 1 ? 'course' : 'courses' ?></p>
                    </div>
                </div>
                <div class="panel__body panel__body--flush">
                    <div class="tablewrap" style="border:0;box-shadow:none;border-radius:0">
                        <table class="table">
                            <thead>
                                <tr>
                                    <?= th('Course') ?>
                                    <?= th('Lecturer') ?>
                                    <?= th('Credits', 'num') ?>
                                    <?= th('Status') ?>
                                    <?= th('Average', 'num') ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($group as $course): ?>
                                <tr>
                                    <td>
                                        <a class="table__name" href="<?= e(url('portal/course.php?id=' . (int) $course['course_id'])) ?>">
                                            <?= e($course['course_code']) ?>
                                        </a>
                                        <span class="table__sub"><?= e($course['course_name']) ?></span>
                                    </td>
                                    <td class="text-2"><?= e($course['lecturer_name'] ?? 'Not assigned') ?></td>
                                    <td class="num"><?= e((string) $course['credit_hours']) ?></td>
                                    <td><?= badge($course['status']) ?></td>
                                    <td class="num">
                                        <?php if ($course['average_marks'] !== null): ?>
                                            <?= e(format_marks($course['average_marks'])) ?>
                                            <?= grade_badge(grade_letter((float) $course['average_marks'])) ?>
                                        <?php else: ?>
                                            <span class="text-3">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php layout_end(); ?>
