<?php
/**
 * Students — create and edit.
 *
 * One file for both, because the two screens differ only in whether a record
 * was loaded. A student is addressed by their student number, so the edit
 * form is reached by ?id=HU26043 rather than by a numeric id; the numeric id
 * is resolved here and used only inside the save query.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path      = 'students/index.php';
$canManage = can('students.manage');

$handle  = query_string('id');
$isEdit  = $handle !== '';

$record = [
    'id'              => 0,
    'student_no'      => '',
    'first_name'      => '',
    'last_name'       => '',
    'gender'          => '',
    'date_of_birth'   => '',
    'email'           => '',
    'phone'           => '',
    'faculty_id'      => '',
    'department_id'   => '',
    'enrollment_year' => (string) date('Y'),
    'enrollment_date' => '',
    'status'          => 'active',
    'created_at'      => null,
];

if ($isEdit) {
    $found = find_student_by_no($handle);

    if ($found === null) {
        not_found('That student does not exist. They may have been deleted.');
    }

    foreach ($record as $key => $unused) {
        if (array_key_exists($key, $found) && $found[$key] !== null) {
            $record[$key] = $found[$key];
        }
    }
}

// ---------------------------------------------------------------------
// Save
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('students.manage');

    // The write target is taken from the record the URL resolved, never from a
    // posted field. Otherwise a crafted POST could aim the update at an
    // unrelated student, or turn the create form into an update.
    $id         = $isEdit ? (int) $record['id'] : 0;
    $isEdit     = $id > 0;
    $data       = collect_input(['faculty_id', 'department_id', 'enrollment_year']);
    $data['id'] = $id;

    $errors = validate($data, [
        'student_no'      => 'required|max:20|unique:students,student_no,{id}',
        'first_name'      => 'required|max:60',
        'last_name'       => 'required|max:60',
        'gender'          => 'required|in:' . implode(',', array_keys(gender_options())),
        'date_of_birth'   => 'required|date|past',
        'email'           => 'required|email|max:150|unique:students,email,{id}',
        'phone'           => 'nullable|max:30',
        'faculty_id'      => 'required|exists:faculties,id',
        'department_id'   => 'required|exists:departments,id',
        'enrollment_year' => 'required|int|min:2000|max:2100',
        'enrollment_date' => 'required|date',
        'status'          => 'required|in:' . implode(',', array_keys(student_status_options())),
    ], [
        'student_no'      => 'Student number',
        'first_name'      => 'First name',
        'last_name'       => 'Last name',
        'gender'          => 'Gender',
        'date_of_birth'   => 'Date of birth',
        'email'           => 'Email',
        'phone'           => 'Phone',
        'faculty_id'      => 'Faculty',
        'department_id'   => 'Department',
        'enrollment_year' => 'Enrollment year',
        'enrollment_date' => 'Enrollment date',
        'status'          => 'Status',
    ]);

    // Normalise the student number so that hu-25042 and HU25042 cannot both
    // exist. The format is checked here rather than with a regex rule so the
    // value has already been folded to upper case by the time it is used.
    $studentNo = strtoupper((string) ($data['student_no'] ?? ''));

    if ($studentNo !== '' && preg_match('/^[A-Z0-9][A-Z0-9\-]*$/', $studentNo) !== 1) {
        $errors['student_no'] = 'Use letters, numbers and hyphens only.';
    }

    $data['student_no'] = $studentNo;

    if ($errors !== []) {
        $record = array_merge($record, $data);
        redirect_back_with_errors(
            'students/form.php' . ($isEdit ? '?id=' . urlencode((string) $record['student_no']) : ''),
            $errors,
            $data,
            'form'
        );
    }

    $facultyId      = (int) $data['faculty_id'];
    $departmentId   = (int) $data['department_id'];
    $enrollmentYear = (int) $data['enrollment_year'];

    // A department belongs to exactly one faculty, so the programme a student
    // is put in has to line up with the faculty they are registered in.
    $departmentFaculty = db_value('SELECT `faculty_id` FROM `departments` WHERE `id` = ?', 'i', [$departmentId]);

    if ($departmentFaculty === null || (int) $departmentFaculty !== $facultyId) {
        $record = array_merge($record, $data);
        redirect_back_with_errors(
            'students/form.php' . ($isEdit ? '?id=' . urlencode((string) $record['student_no']) : ''),
            ['department_id' => 'That department sits inside a different faculty, so cannot be chosen together with this faculty.'],
            $data,
            'form'
        );
    }

    $values = [
        $studentNo,
        (string) $data['first_name'],
        (string) $data['last_name'],
        (string) $data['gender'],
        (string) $data['date_of_birth'],
        (string) $data['email'],
        ($data['phone'] ?? '') === '' ? null : $data['phone'],
        $facultyId,
        $departmentId,
        $enrollmentYear,
        (string) $data['enrollment_date'],
        (string) $data['status'],
    ];

    if ($isEdit) {
        db_execute(
            'UPDATE `students`
                SET `student_no` = ?, `first_name` = ?, `last_name` = ?, `gender` = ?, `date_of_birth` = ?,
                    `email` = ?, `phone` = ?, `faculty_id` = ?, `department_id` = ?,
                    `enrollment_year` = ?, `enrollment_date` = ?, `status` = ?
              WHERE `id` = ?',
            'sssssssiiissi',
            array_merge($values, [$id])
        );

        flash_set('success', 'Student updated.');
        redirect('students/view.php?id=' . urlencode($studentNo));
    }

    $newId = db_insert(
        'INSERT INTO `students` (`student_no`, `first_name`, `last_name`, `gender`, `date_of_birth`,
                                 `email`, `phone`, `faculty_id`, `department_id`,
                                 `enrollment_year`, `enrollment_date`, `status`)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'sssssssiiiss',
        $values
    );

    flash_set('success', 'Student added.');
    redirect('students/view.php?id=' . urlencode($studentNo));
}

if (!$canManage) {
    require_permission('students.manage');
}

$faculties    = faculty_options(false);
$currentFaculty = (int) $record['faculty_id'] > 0 ? (int) $record['faculty_id'] : null;
$yearOptions  = enrollment_years();

layout_start($isEdit ? 'Edit student' : 'Add student', 'students');

render_page_head([
    'eyebrow'  => 'Student body',
    'title'    => $isEdit ? 'Edit student' : 'Add a student',
    'subtitle' => $isEdit
        ? 'Changes apply to every enrolment recorded against this student.'
        : 'A student belongs to one faculty and one department.',
    'actions'  => '<a class="btn btn--quiet" href="' . e(url($path)) . '">' . icon('arrow-left', 15) . '<span>Back to list</span></a>',
]);

$cancelTo = $isEdit ? 'students/view.php?id=' . urlencode((string) $record['student_no']) : $path;
?>

<form class="card" method="post" action="<?= e(url('students/form.php' . ($isEdit ? '?id=' . urlencode((string) $record['student_no']) : ''))) ?>" novalidate data-once>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e((string) $record['id']) ?>">
    <?php endif; ?>

    <div class="card__body">
        <div class="formgrid">
            <?= text_field('student_no', 'Student number', [
                'value'       => (string) $record['student_no'],
                'required'    => true,
                'maxlength'   => 20,
                'placeholder' => 'HU26049',
                'hint'        => 'Reference printed on the identity card. Letters, numbers, hyphens.',
            ]) ?>

            <?= text_field('first_name', 'First name', [
                'value'     => (string) $record['first_name'],
                'required'  => true,
                'maxlength' => 60,
            ]) ?>

            <?= text_field('last_name', 'Last name', [
                'value'     => (string) $record['last_name'],
                'required'  => true,
                'maxlength' => 60,
                'hint'      => 'Family name, used for the list ordering.',
            ]) ?>

            <?= select_field('gender', 'Gender', gender_options(), [
                'value'    => (string) $record['gender'],
                'required' => true,
            ]) ?>

            <?= text_field('date_of_birth', 'Date of birth', [
                'value'    => (string) $record['date_of_birth'],
                'type'     => 'date',
                'required' => true,
            ]) ?>

            <?= text_field('email', 'Email', [
                'value'        => (string) $record['email'],
                'type'         => 'email',
                'required'     => true,
                'maxlength'    => 150,
                'autocomplete' => 'email',
                'placeholder'  => 'name@hagmah.edu',
                'hint'         => 'Used once; no other student or lecturer may hold it.',
            ]) ?>

            <?= text_field('phone', 'Phone', [
                'value'       => (string) $record['phone'],
                'maxlength'   => 30,
                'placeholder' => '+960 7700 0000',
            ]) ?>

            <?= select_field('faculty_id', 'Faculty', $faculties, [
                'value'    => (string) $record['faculty_id'],
                'required' => true,
                'wide'     => true,
                'hint'     => 'The faculty the student is registered under.',
            ]) ?>

            <?= select_field('department_id', 'Department', department_options($currentFaculty, false), [
                'value'    => (string) $record['department_id'],
                'required' => true,
                'wide'     => true,
                'hint'     => 'The department the student is admitted to. Narrows to the chosen faculty.',
            ]) ?>

            <?= select_field('enrollment_year', 'Enrollment year', $yearOptions, [
                'value'    => (string) $record['enrollment_year'],
                'required' => true,
                'hint'     => 'The first year the student registered.',
            ]) ?>

            <?= text_field('enrollment_date', 'Enrollment date', [
                'value'    => (string) $record['enrollment_date'],
                'type'     => 'date',
                'required' => true,
                'hint'     => 'The exact registration date, used for the enrolment trend.',
            ]) ?>

            <?= select_field('status', 'Status', student_status_options(), [
                'value'    => (string) $record['status'],
                'required' => true,
            ]) ?>
        </div>
    </div>

    <div class="card__body" style="border-top:1px solid var(--border)">
        <div class="formactions">
            <button class="btn btn--primary" type="submit">
                <?= icon('check', 16) ?><span><?= $isEdit ? 'Save changes' : 'Add student' ?></span>
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