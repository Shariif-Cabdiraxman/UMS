<?php
/**
 * Student portal — assessment-level marks.
 *
 * The transcript is grouped by term and course so the reader can see both
 * the individual assessments and the average for each course.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/portal.php';

$student   = require_student();
$studentId = (int) $student['id'];
$grades    = student_grades($studentId);
$summary   = student_grade_summary($studentId);

$terms = [];
foreach ($grades as $grade) {
    $termKey   = $grade['academic_year'] . '|' . $grade['semester'];
    $courseKey = (int) $grade['course_id'];

    if (!isset($terms[$termKey])) {
        $terms[$termKey] = [
            'academic_year' => $grade['academic_year'],
            'semester'      => $grade['semester'],
            'courses'       => [],
        ];
    }

    if (!isset($terms[$termKey]['courses'][$courseKey])) {
        $terms[$termKey]['courses'][$courseKey] = [
            'course_code' => $grade['course_code'],
            'course_name' => $grade['course_name'],
            'credit_hours' => $grade['credit_hours'],
            'rows'        => [],
        ];
    }

    $terms[$termKey]['courses'][$courseKey]['rows'][] = $grade;
}

$courseCount = 0;
foreach ($terms as $term) {
    $courseCount += count($term['courses']);
}

layout_start('My grades', 'grades');

render_page_head([
    'eyebrow'  => 'Academic record',
    'title'    => 'My grades',
    'subtitle' => 'Every assessment recorded against your enrolments.',
    'meta'     => e(format_number($summary['grade_count'])) . ' marks · '
        . e(format_number($courseCount)) . ' ' . ($courseCount === 1 ? 'course' : 'courses'),
    'actions'  => '<a class="btn btn--quiet" href="' . e(url('portal/courses.php')) . '">'
        . icon('course', 15) . '<span>My courses</span></a>',
]);
?>

<div class="stackgap">
    <section class="stats stats--3" aria-label="Grade summary">
        <article class="stat">
            <div class="stat__top">
                <span class="stat__icon"><?= icon('grades', 16) ?></span>
                <p class="stat__label">Marks recorded</p>
            </div>
            <p class="stat__value"><?= e(format_number($summary['grade_count'])) ?></p>
            <p class="stat__foot">Across every assessment</p>
        </article>
        <article class="stat">
            <div class="stat__top">
                <span class="stat__icon stat__icon--blue"><?= icon('award', 16) ?></span>
                <p class="stat__label">Overall average</p>
            </div>
            <p class="stat__value">
                <?= $summary['average'] !== null ? e(format_marks($summary['average'])) : '—' ?>
            </p>
            <p class="stat__foot">
                <?php if ($summary['average'] !== null): ?>
                    <?= grade_badge(grade_letter((float) $summary['average'])) ?>
                    <span>current standing</span>
                <?php else: ?>
                    <span>No marks yet</span>
                <?php endif; ?>
            </p>
        </article>
        <article class="stat">
            <div class="stat__top">
                <span class="stat__icon stat__icon--peach"><?= icon('course', 16) ?></span>
                <p class="stat__label">Courses with marks</p>
            </div>
            <p class="stat__value"><?= e(format_number($courseCount)) ?></p>
            <p class="stat__foot">Current and completed registrations</p>
        </article>
    </section>

    <?php if ($terms === []): ?>
        <section class="panel">
            <div class="panel__body">
                <?php render_empty_state('grades', 'No marks yet', 'Marks appear here after the registrar records them.'); ?>
            </div>
        </section>
    <?php else: ?>
        <?php foreach ($terms as $term): ?>
            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title"><?= e($term['academic_year']) ?> · <?= e($term['semester']) ?></h2>
                        <p class="panel__sub"><?= e(format_number(count($term['courses']))) ?> <?= count($term['courses']) === 1 ? 'course' : 'courses' ?></p>
                    </div>
                </div>

                <?php foreach ($term['courses'] as $course): ?>
                    <?php
                    $rows    = $course['rows'];
                    $total   = 0.0;
                    foreach ($rows as $row) {
                        $total += (float) $row['marks'];
                    }
                    $average = $rows !== [] ? round($total / count($rows), 2) : null;
                    ?>
                    <div class="panel__body panel__body--flush">
                        <div class="recordhead" style="margin:1rem 1.25rem 0">
                            <span class="recordhead__mark"><?= icon('course', 20) ?></span>
                            <div class="recordhead__text">
                                <h3 class="recordhead__title">
                                    <a href="<?= e(url('portal/course.php?id=' . (int) $rows[0]['course_id'])) ?>">
                                        <?= e($course['course_code']) ?>
                                    </a>
                                </h3>
                                <p class="recordhead__meta">
                                    <span><?= e($course['course_name']) ?></span>
                                    <span><?= e((string) $course['credit_hours']) ?> credits</span>
                                    <?php if ($average !== null): ?>
                                        <span>average <?= e(format_marks($average)) ?></span>
                                        <span><?= grade_badge(grade_letter($average)) ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <div class="tablewrap" style="border:0;box-shadow:none;border-radius:0">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <?= th('Assessment') ?>
                                        <?= th('Marks', 'num') ?>
                                        <?= th('Grade', 'num') ?>
                                        <?= th('Last updated') ?>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td class="text-2"><?= e($row['assessment']) ?></td>
                                        <td class="num"><?= e(format_marks($row['marks'])) ?></td>
                                        <td class="num"><?= grade_badge($row['letter_grade']) ?></td>
                                        <td class="text-3"><?= e(format_datetime($row['updated_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php layout_end(); ?>
