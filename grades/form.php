<?php
/**
 * Grades — record and edit.
 *
 * One file for both, because the two screens differ only in whether a record
 * was loaded. Splitting them would mean two forms to keep in step.
 *
 * The letter grade is never typed: it is derived from the marks by the
 * thresholds in grade_letter(), so a user cannot record a B for the same
 * marks that the application everywhere else counts as a C.
 *
 * The enrollment the mark belongs to can be narrowed by student, course,
 * year and semester with the filter selects at the top; they only limit
 * what the enrollment list offers.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path      = 'grades/index.php';
$canManage = can('grades.manage');

$id     = (int) query_int('id');
$isEdit = $id > 0;

$record = [
    'id'            => 0,
    'enrollment_id' => (int) query_int('enrollment'),
    'assessment'    => '',
    'marks'         => '',
    'letter_grade'  => '',
    'created_at'    => null,
];

if ($isEdit) {
    $found = db_row('SELECT * FROM `grades` WHERE `id` = ?', 'i', [$id]);

    if ($found === null) {
        not_found('That grade does not exist. It may have been deleted.');
    }

    foreach ($record as $key => $unused) {
        if (array_key_exists($key, $found) && $found[$key] !== null) {
            $record[$key] = $found[$key];
        }
    }
    $record['enrollment_id'] = (int) $record['enrollment_id'];
}

// ---------------------------------------------------------------------
// Enrollment narrowing (create screen): the filters only guide the picker.
// ---------------------------------------------------------------------

$filter = array_map('trim', [
    'student'  => is_post() ? (string) input('filter_student') : query_string('filter_student'),
    'course'   => is_post() ? (string) input('filter_course') : query_string('filter_course'),
    'year'     => is_post() ? (string) input('filter_year') : query_string('filter_year'),
    'semester' => is_post() ? (string) input('filter_semester') : query_string('filter_semester'),
]);

$rows = db_all(
    'SELECT DISTINCT e.`academic_year`
       FROM `enrollments` e
      ORDER BY e.`academic_year` DESC'
);
$years = array_combine(array_map('strval', array_column($rows, 'academic_year')), array_column($rows, 'academic_year'));

// ---------------------------------------------------------------------
// Save
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('grades.manage');

    $id     = (int) input_int('id', 0);
    $isEdit = $id > 0;
    $data   = collect_input(['enrollment_id'], ['marks']);
    $data['id'] = $id;

    $errors = validate($data, [
        'enrollment_id' => 'required|exists:enrollments,id',
        'assessment'    => 'required|in:' . implode(',', array_keys(assessment_options())),
        'marks'         => 'required|numeric|min:0|max:100',
    ], [
        'enrollment_id' => 'Enrollment',
        'assessment'    => 'Assessment',
        'marks'         => 'Marks',
    ]);

    if ($errors !== []) {
        $record = array_merge($record, $data);
        redirect_back_with_errors('grades/form.php' . ($id > 0 ? '?id=' . $id : ''), $errors, $data, 'form');
    }

    $enrollmentId  = (int) $data['enrollment_id'];
    $assessment    = (string) $data['assessment'];
    $marks         = (float) $data['marks'];
    $letter        = grade_letter($marks);

    // One mark per assessment per enrollment. A second mark for the same
    // assessment would otherwise stack silently; flag it with a message.
    $existingId = db_value(
        'SELECT `id` FROM `grades`
          WHERE `enrollment_id` = ? AND `assessment` = ?
          LIMIT 1',
        'is',
        [$enrollmentId, $assessment]
    );

    if ($existingId !== null && (int) $existingId !== $id) {
        $record = array_merge($record, $data);
        redirect_back_with_errors(
            'grades/form.php' . ($id > 0 ? '?id=' . $id : ''),
            ['assessment' => 'That assessment already has a mark for this enrollment. Edit the existing grade to change it.'],
            $data,
            'form'
        );
    }

    if ($id > 0) {
        db_execute(
            'UPDATE `grades`
                SET `enrollment_id` = ?, `assessment` = ?, `marks` = ?, `letter_grade` = ?
              WHERE `id` = ?',
            'isdsi',
            [$enrollmentId, $assessment, $marks, $letter, $id]
        );

        flash_set('success', 'Grade updated.');
        redirect('grades/view.php?id=' . $id);
    }

    $newId = db_insert(
        'INSERT INTO `grades` (`enrollment_id`, `assessment`, `marks`, `letter_grade`)
              VALUES (?, ?, ?, ?)',
        'isds',
        [$enrollmentId, $assessment, $marks, $letter]
    );

    flash_set('success', 'Grade recorded.');
    redirect('grades/view.php?id=' . $newId);
}

if (!$canManage) {
    require_permission('grades.manage');
}

$students  = student_options(null, false);
$courses   = course_options(null, null, false);
$semesters = semesters();
$assessments = assessment_options();

$markHints = '90–100 → A, 80–89 → B, 70–79 → C, 60–69 → D, below 60 → F.';

layout_start($isEdit ? 'Edit grade' : 'Add a grade', 'grades');

render_page_head([
    'eyebrow'  => 'Academic record',
    'title'    => $isEdit ? 'Edit grade' : 'Record a grade',
    'subtitle' => $isEdit
        ? 'Changing the marks recalculates the letter grade. The grade history keeps a record through the audit timestamps.'
        : 'Marks for one assessment of one enrollment. The letter grade is derived from the marks.',
    'actions'  => '<a class="btn btn--quiet" href="' . e(url($path)) . '">' . icon('arrow-left', 15) . '<span>Back to list</span></a>',
]);

$cancelTo = $isEdit ? 'grades/view.php?id=' . $id : $path;
?>

<form class="card" method="post" action="<?= e(url('grades/form.php' . ($isEdit ? '?id=' . $id : ''))) ?>" novalidate data-once>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e((string) $id) ?>">
    <?php endif; ?>

    <?php if (!$isEdit): ?>
        <div class="card__body" style="border-bottom:1px solid var(--border)">
            <div class="panel__titles" style="margin-bottom:.65rem">
                <h2 class="panel__title" style="font-size:.8rem">Find the enrollment</h2>
                <p class="panel__sub">These filters only narrow what the enrollment list offers; the record itself is just the three fields below.</p>
            </div>
            <div class="formgrid">
                <?= select_field('filter_student', 'Student', $students, [
                    'value' => $filter['student'],
                    'wide'  => true,
                ]) ?>
                <?= select_field('filter_course', 'Course', $courses, [
                    'value' => $filter['course'],
                    'wide'  => true,
                ]) ?>
                <?= select_field('filter_year', 'Year', $years, [
                    'value' => $filter['year'],
                ]) ?>
                <?= select_field('filter_semester', 'Semester', $semesters, [
                    'value' => $filter['semester'],
                ]) ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card__body">
        <div class="formgrid">
            <?= select_field('enrollment_id', 'Enrollment', enrollment_options(
                $filter['student'] !== '' ? (int) $filter['student'] : null,
                $filter['course'] !== '' ? (int) $filter['course'] : null,
                $filter['year'] !== '' ? $filter['year'] : null,
                $filter['semester'] !== '' ? $filter['semester'] : null,
                false
            ), [
                'value'    => (string) $record['enrollment_id'],
                'required' => true,
                'wide'     => true,
                'hint'     => 'The registration the mark belongs to, shown as <em>course — student (year, semester)</em>.',
            ]) ?>

            <?= select_field('assessment', 'Assessment', $assessments, [
                'value'    => (string) $record['assessment'],
                'required' => true,
            ]) ?>

            <?= text_field('marks', 'Marks', [
                'value'    => (string) $record['marks'],
                'type'     => 'number',
                'step'     => '0.5',
                'min'      => '0',
                'max'      => '100',
                'required' => true,
                'hint'     => 'From 0 to 100. Letter grade: ' . $markHints . '.',
            ]) ?>

            <?php if ($record['letter_grade'] !== ''): ?>
                <div class="lg-span2">
                    <label class="field__label">Stored letter grade</label>
                    <div class="field__value"><?= grade_badge($record['letter_grade']) ?>
                        <span class="text-3 text-sm">(recomputed from marks when you save)</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card__body" style="border-top:1px solid var(--border)">
        <div class="formactions">
            <button class="btn btn--primary" type="submit">
                <?= icon('check', 16) ?><span><?= $isEdit ? 'Save changes' : 'Record grade' ?></span>
            </button>
            <a class="btn btn--quiet" href="<?= e(url($cancelTo)) ?>">Cancel</a>
            <?php if ($isEdit): ?>
                <span class="formactions__spacer"></span>
                <span class="text-3 text-xs">Recorded <?= e(format_datetime($record['created_at'])) ?></span>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php layout_end(); ?>