<?php
/**
 * Enrollments — create and edit.
 *
 * One file for both, because the two screens differ only in whether a record
 * was loaded. Splitting them would mean two forms to keep in step.
 *
 * An enrollment can be pre-filled from a student record (by student number)
 * or from a course record by passing ?student= or ?course= on the URL.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path      = 'enrollments/index.php';
$canManage = can('enrollments.manage');

$id     = (int) query_int('id');
$isEdit = $id > 0;

// Pre-fill from ?course= always, and from ?student= when it carries a student
// number (the way a student is addressed everywhere in this application).
$prefillCourse = (int) query_int('course');

$prefillStudent = 0;
$studentHandle  = query_string('student');
if ($studentHandle !== '') {
    $byNo = is_numeric($studentHandle) ? null : find_student_by_no($studentHandle);
    $prefillStudent = $byNo !== null ? (int) $byNo['id'] : (int) $studentHandle;
}

$record = [
    'id'              => 0,
    'student_id'      => $prefillStudent,
    'course_id'       => $prefillCourse,
    'academic_year'   => current_academic_year(),
    'semester'        => current_semester(),
    'enrollment_date' => date('Y-m-d'),
    'status'          => 'enrolled',
    'created_at'      => null,
];

if ($isEdit) {
    $found = db_row('SELECT * FROM `enrollments` WHERE `id` = ?', 'i', [$id]);

    if ($found === null) {
        not_found('That enrollment does not exist. It may have been deleted.');
    }

    foreach ($record as $key => $unused) {
        if (array_key_exists($key, $found) && $found[$key] !== null) {
            $record[$key] = $found[$key];
        }
    }
    $record['student_id'] = (int) $record['student_id'];
    $record['course_id']  = (int) $record['course_id'];
}

// ---------------------------------------------------------------------
// Save
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('enrollments.manage');

    // The write target is taken from the record the URL resolved, never from a
    // posted field, so a crafted POST cannot aim the update elsewhere.
    $id         = $isEdit ? (int) $record['id'] : 0;
    $isEdit     = $id > 0;
    $data       = collect_input(['student_id', 'course_id']);
    $data['id'] = $id;

    $yearOptions = academic_years();

    $errors = validate($data, [
        'student_id'      => 'required|exists:students,id',
        'course_id'       => 'required|exists:courses,id',
        'academic_year'   => 'required|in:' . implode(',', $yearOptions),
        'semester'        => 'required|in:' . implode(',', semesters()),
        'enrollment_date' => 'required|date',
        'status'          => 'required|in:' . implode(',', array_keys(enrollment_status_options())),
    ], [
        'student_id'      => 'Student',
        'course_id'       => 'Course',
        'academic_year'   => 'Academic year',
        'semester'        => 'Semester',
        'enrollment_date' => 'Enrollment date',
        'status'          => 'Status',
    ]);

    if ($errors !== []) {
        $record = array_merge($record, $data);
        redirect_back_with_errors('enrollments/form.php' . ($id > 0 ? '?id=' . $id : ''), $errors, $data, 'form');
    }

    $studentId = (int) $data['student_id'];
    $courseId  = (int) $data['course_id'];

    // The same student cannot be registered for the same course twice within
    // one semester of one academic year. The database enforces this with the
    // composite unique key, but a specific message is nicer than a constraint
    // error.
    $existingId = db_value(
        'SELECT `id` FROM `enrollments`
          WHERE `student_id` = ? AND `course_id` = ? AND `academic_year` = ? AND `semester` = ?
          LIMIT 1',
        'iiss',
        [$studentId, $courseId, (string) $data['academic_year'], (string) $data['semester']]
    );

    if ($existingId !== null && (int) $existingId !== $id) {
        $record = array_merge($record, $data);
        redirect_back_with_errors(
            'enrollments/form.php' . ($id > 0 ? '?id=' . $id : ''),
            ['academic_year' => 'This student has already been enrolled for that course in that term.'],
            $data,
            'form'
        );
    }

    // Students only take courses offered by their own department, so the two
    // screens never tell visitors a different story.
    $studentDepartment = db_value('SELECT `department_id` FROM `students` WHERE `id` = ?', 'i', [$studentId]);
    $courseDepartment  = db_value('SELECT `department_id` FROM `courses` WHERE `id` = ?', 'i', [$courseId]);

    if ($studentDepartment === null || $courseDepartment === null || (int) $studentDepartment !== (int) $courseDepartment) {
        $record = array_merge($record, $data);
        redirect_back_with_errors(
            'enrollments/form.php' . ($id > 0 ? '?id=' . $id : ''),
            ['course_id' => 'That course is offered by a different department to the student\'s. Students enroll in courses from their own department.'],
            $data,
            'form'
        );
    }

    $values = [
        $studentId,
        $courseId,
        (string) $data['academic_year'],
        (string) $data['semester'],
        (string) $data['enrollment_date'],
        (string) $data['status'],
    ];

    if ($id > 0) {
        db_execute(
            'UPDATE `enrollments`
                SET `student_id` = ?, `course_id` = ?, `academic_year` = ?, `semester` = ?,
                    `enrollment_date` = ?, `status` = ?
              WHERE `id` = ?',
            'iissssi',
            array_merge($values, [$id])
        );

        flash_set('success', 'Enrollment updated.');
        redirect('enrollments/view.php?id=' . $id);
    }

    $newId = db_insert(
        'INSERT INTO `enrollments` (`student_id`, `course_id`, `academic_year`, `semester`,
                                    `enrollment_date`, `status`)
              VALUES (?, ?, ?, ?, ?, ?)',
        'iissss',
        $values
    );

    flash_set('success', 'Enrollment added.');
    redirect('enrollments/view.php?id=' . $newId);
}

if (!$canManage) {
    require_permission('enrollments.manage');
}

$students  = student_options(null, false);
$courses   = course_options(null, null, false);
$yearOptions = academic_years();

layout_start($isEdit ? 'Edit enrollment' : 'Add enrollment', 'enrollments');

render_page_head([
    'eyebrow'  => 'Academic record',
    'title'    => $isEdit ? 'Edit enrollment' : 'Add an enrollment',
    'subtitle' => $isEdit
        ? 'Changes apply to the registration and everything recorded against it.'
        : 'Register one student for one course in one term.',
    'actions'  => '<a class="btn btn--quiet" href="' . e(url($path)) . '">' . icon('arrow-left', 15) . '<span>Back to list</span></a>',
]);

$cancelTo = $isEdit ? 'enrollments/view.php?id=' . $id : $path;
?>

<form class="card" method="post" action="<?= e(url('enrollments/form.php' . ($isEdit ? '?id=' . $id : ''))) ?>" novalidate data-once>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e((string) $id) ?>">
    <?php endif; ?>

    <div class="card__body">
        <div class="formgrid">
            <?= select_field('student_id', 'Student', $students, [
                'value'    => (string) $record['student_id'],
                'required' => true,
                'wide'     => true,
                'hint'     => 'The student being registered. Students enroll in courses from their own department.',
            ]) ?>

            <?= select_field('course_id', 'Course', $courses, [
                'value'    => (string) $record['course_id'],
                'required' => true,
                'wide'     => true,
            ]) ?>

            <?= select_field('academic_year', 'Academic year', $yearOptions, [
                'value'    => (string) $record['academic_year'],
                'required' => true,
                'hint'     => 'The teaching year, written as 2026/2027.',
            ]) ?>

            <?= select_field('semester', 'Semester', semesters(), [
                'value'    => (string) $record['semester'],
                'required' => true,
            ]) ?>

            <?= text_field('enrollment_date', 'Enrollment date', [
                'value'    => (string) $record['enrollment_date'],
                'type'     => 'date',
                'required' => true,
            ]) ?>

            <?= select_field('status', 'Status', enrollment_status_options(), [
                'value'    => (string) $record['status'],
                'required' => true,
            ]) ?>
        </div>
    </div>

    <div class="card__body" style="border-top:1px solid var(--border)">
        <div class="formactions">
            <button class="btn btn--primary" type="submit">
                <?= icon('check', 16) ?><span><?= $isEdit ? 'Save changes' : 'Add enrollment' ?></span>
            </button>
            <a class="btn btn--quiet" href="<?= e(url($cancelTo)) ?>">Cancel</a>
            <?php if ($isEdit): ?>
                <span class="formactions__spacer"></span>
                <span class="text-3 text-xs">Created <?= e(format_datetime($record['created_at'])) ?></span>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php layout_end(); ?>