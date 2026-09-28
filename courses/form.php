<?php
/**
 * Courses — create and edit.
 *
 * One file for both, because the two screens differ only in whether a record
 * was loaded. Splitting them would mean two forms to keep in step.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path      = 'courses/index.php';
$canManage = can('courses.manage');

$id     = (int) query_int('id');
$isEdit = $id > 0;

// A course can be pre-filled from a department, lecturer or course screen.
$record = [
    'id'            => 0,
    'course_code'   => '',
    'course_name'   => '',
    'department_id' => (int) query_int('department') > 0 ? (int) query_int('department') : '',
    'credit_hours'  => '3',
    'semester'      => '',
    'lecturer_id'   => (int) query_int('lecturer') > 0 ? (int) query_int('lecturer') : '',
    'description'   => '',
    'created_at'    => null,
];

if ($isEdit) {
    $found = db_row('SELECT * FROM `courses` WHERE `id` = ?', 'i', [$id]);

    if ($found === null) {
        not_found('That course does not exist. It may have been deleted.');
    }

    foreach ($record as $key => $unused) {
        if ($key === 'department_id' || $key === 'lecturer_id') {
            continue;
        }
        if (array_key_exists($key, $found) && $found[$key] !== null) {
            $record[$key] = $found[$key];
        }
    }
    $record['department_id'] = $found['department_id'] === null ? '' : (int) $found['department_id'];
    $record['lecturer_id']   = $found['lecturer_id'] === null ? '' : (int) $found['lecturer_id'];
}

// ---------------------------------------------------------------------
// Save
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('courses.manage');

    $id         = (int) input_int('id', 0);
    $isEdit     = $id > 0;
    $data       = collect_input(['department_id', 'credit_hours', 'lecturer_id']);
    $data['id'] = $id;

    $errors = validate($data, [
        'course_code'  => 'required|max:20|unique:courses,course_code,{id}',
        'course_name'  => 'required|max:180',
        'department_id' => 'required|exists:departments,id',
        'credit_hours' => 'required|int|min:1|max:6',
        'semester'     => 'required|in:' . implode(',', semesters()),
        'lecturer_id'  => 'nullable|exists:lecturers,id',
        'description'  => 'nullable|max:2000',
    ], [
        'course_code'   => 'Course code',
        'course_name'   => 'Course name',
        'department_id' => 'Department',
        'credit_hours'  => 'Credit hours',
        'semester'      => 'Semester',
        'lecturer_id'   => 'Lecturer',
        'description'   => 'Description',
    ]);

    // Normalise the code so that csc-101 and CSC-101 cannot both exist.
    $courseCode = strtoupper((string) ($data['course_code'] ?? ''));

    if ($courseCode !== '' && preg_match('/^[A-Z0-9][A-Z0-9\-\/]*$/', $courseCode) !== 1) {
        $errors['course_code'] = 'Use letters, numbers, hyphens or slashes only.';
    }

    $data['course_code'] = $courseCode;

    if ($errors !== []) {
        $record = array_merge($record, $data);
        redirect_back_with_errors('courses/form.php' . ($id > 0 ? '?id=' . $id : ''), $errors, $data, 'form');
    }

    $departmentId = (int) $data['department_id'];
    $creditHours  = (int) $data['credit_hours'];
    $lecturerId   = ($data['lecturer_id'] ?? '') === '' ? null : (int) $data['lecturer_id'];

    // The lecturer must belong to the department that owns the course,
    // otherwise the two screens would tell visitors different stories.
    if ($lecturerId !== null) {
        $lecturerDepartment = db_value('SELECT `department_id` FROM `lecturers` WHERE `id` = ?', 'i', [$lecturerId]);

        if ($lecturerDepartment === null || (int) $lecturerDepartment !== $departmentId) {
            $record = array_merge($record, $data);
            redirect_back_with_errors(
                'courses/form.php' . ($id > 0 ? '?id=' . $id : ''),
                ['lecturer_id' => 'That lecturer teaches in a different department, so cannot be assigned to this course.'],
                $data,
                'form'
            );
        }
    }

    $values = [
        $courseCode,
        (string) $data['course_name'],
        $departmentId,
        $creditHours,
        (string) $data['semester'],
        $lecturerId,
        ($data['description'] ?? '') === '' ? null : $data['description'],
    ];

    if ($id > 0) {
        db_execute(
            'UPDATE `courses`
                SET `course_code` = ?, `course_name` = ?, `department_id` = ?, `credit_hours` = ?,
                    `semester` = ?, `lecturer_id` = ?, `description` = ?
              WHERE `id` = ?',
            'ssiisssi',
            array_merge($values, [$id])
        );

        flash_set('success', 'Course updated.');
        redirect('courses/view.php?id=' . $id);
    }

    $newId = db_insert(
        'INSERT INTO `courses` (`course_code`, `course_name`, `department_id`, `credit_hours`,
                                `semester`, `lecturer_id`, `description`)
              VALUES (?, ?, ?, ?, ?, ?, ?)',
        'ssiisss',
        $values
    );

    flash_set('success', 'Course added.');
    redirect('courses/view.php?id=' . $newId);
}

if (!$canManage) {
    require_permission('courses.manage');
}

$departments = department_options(null, false);
$currentDepartment = (int) $record['department_id'] > 0 ? (int) $record['department_id'] : null;

layout_start($isEdit ? 'Edit course' : 'Add course', 'courses');

render_page_head([
    'eyebrow'  => 'Academic record',
    'title'    => $isEdit ? 'Edit course' : 'Add a course',
    'subtitle' => $isEdit
        ? 'Changes apply to every enrolment recorded against this course.'
        : 'A course belongs to one department and is taught in one semester.',
    'actions'  => '<a class="btn btn--quiet" href="' . e(url($path)) . '">' . icon('arrow-left', 15) . '<span>Back to list</span></a>',
]);

$cancelTo = $isEdit ? 'courses/view.php?id=' . $id : $path;
?>

<form class="card" method="post" action="<?= e(url('courses/form.php' . ($isEdit ? '?id=' . $id : ''))) ?>" novalidate data-once>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e((string) $id) ?>">
    <?php endif; ?>

    <div class="card__body">
        <div class="formgrid">
            <?= text_field('course_code', 'Course code', [
                'value'       => (string) $record['course_code'],
                'required'    => true,
                'maxlength'   => 20,
                'placeholder' => 'CSC201',
                'hint'        => 'Short identifier used on timetables. Letters, numbers, hyphens.',
            ]) ?>

            <?= text_field('course_name', 'Course name', [
                'value'     => (string) $record['course_name'],
                'required'  => true,
                'maxlength' => 180,
                'hint'      => 'For example: Database Systems',
            ]) ?>

            <?= select_field('department_id', 'Department', $departments, [
                'value'    => (string) $record['department_id'],
                'required' => true,
                'wide'     => true,
                'hint'     => 'The department that owns and teaches this course.',
            ]) ?>

            <?= text_field('credit_hours', 'Credit hours', [
                'value'     => (string) $record['credit_hours'],
                'type'      => 'number',
                'required'  => true,
                'min'       => 1,
                'max'       => 6,
                'hint'      => 'How many credits the course is worth.',
            ]) ?>

            <?= select_field('semester', 'Semester', semesters(), [
                'value'    => (string) $record['semester'],
                'required' => true,
                'hint'     => 'The semester in which the course is normally taught.',
            ]) ?>

            <?= select_field('lecturer_id', 'Lecturer', lecturer_options($currentDepartment, true, 'No lecturer assigned'), [
                'value' => (string) $record['lecturer_id'],
                'wide'  => true,
                'hint'  => 'Optional. The list narrows to the chosen department.',
            ]) ?>

            <?= textarea_field('description', 'Description', [
                'value'       => (string) $record['description'],
                'rows'        => 4,
                'maxlength'   => 2000,
                'wide'        => true,
                'placeholder' => 'What the course covers, and anything a new reader should know.',
            ]) ?>
        </div>
    </div>

    <div class="card__body" style="border-top:1px solid var(--border)">
        <div class="formactions">
            <button class="btn btn--primary" type="submit">
                <?= icon('check', 16) ?><span><?= $isEdit ? 'Save changes' : 'Add course' ?></span>
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