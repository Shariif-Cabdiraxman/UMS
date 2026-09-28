<?php
/**
 * Lecturers — create and edit.
 *
 * One file for both, because the two screens differ only in whether a record
 * was loaded. Splitting them would mean two forms to keep in step.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path      = 'lecturers/index.php';
$canManage = can('lecturers.manage');

$id     = (int) query_int('id');
$isEdit = $id > 0;

$record = [
    'id'              => 0,
    'staff_no'        => '',
    'first_name'      => '',
    'last_name'       => '',
    'email'           => '',
    'phone'           => '',
    'department_id'   => '',
    'specialization'  => '',
    'status'          => 'active',
    'created_at'      => null,
];

if ($isEdit) {
    $found = db_row('SELECT * FROM `lecturers` WHERE `id` = ?', 'i', [$id]);

    if ($found === null) {
        not_found('That lecturer does not exist. They may have been deleted.');
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
    guard_write('lecturers.manage');

    $id         = (int) input_int('id', 0);
    $isEdit     = $id > 0;
    $data       = collect_input(['department_id']);
    $data['id'] = $id;

    $errors = validate($data, [
        'staff_no'       => 'required|max:20|unique:lecturers,staff_no,{id}',
        'first_name'     => 'required|max:60',
        'last_name'      => 'required|max:60',
        'email'          => 'required|email|max:150|unique:lecturers,email,{id}',
        'phone'          => 'nullable|max:30',
        'department_id'  => 'required|exists:departments,id',
        'specialization' => 'nullable|max:150',
        'status'         => 'required|in:active,on_leave,inactive',
    ], [
        'staff_no'       => 'Staff number',
        'first_name'     => 'First name',
        'last_name'      => 'Last name',
        'email'          => 'Email',
        'phone'          => 'Phone',
        'department_id'  => 'Department',
        'specialization' => 'Specialism',
        'status'         => 'Status',
    ]);

    // Normalise the staff number so that lg-001 and LG-001 cannot both exist.
    $staffNo = strtoupper((string) ($data['staff_no'] ?? ''));

    if ($staffNo !== '' && preg_match('/^[A-Z0-9][A-Z0-9\-]*$/', $staffNo) !== 1) {
        $errors['staff_no'] = 'Use letters, numbers and hyphens only.';
    }

    $data['staff_no'] = $staffNo;

    if ($errors !== []) {
        $record = array_merge($record, $data);
        redirect_back_with_errors('lecturers/form.php' . ($id > 0 ? '?id=' . $id : ''), $errors, $data, 'form');
    }

    $departmentId = (int) $data['department_id'];

    $values = [
        $staffNo,
        (string) $data['first_name'],
        (string) $data['last_name'],
        (string) $data['email'],
        ($data['phone'] ?? '') === '' ? null : $data['phone'],
        $departmentId,
        ($data['specialization'] ?? '') === '' ? null : $data['specialization'],
        (string) $data['status'],
    ];

    if ($id > 0) {
        db_execute(
            'UPDATE `lecturers`
                SET `staff_no` = ?, `first_name` = ?, `last_name` = ?, `email` = ?, `phone` = ?,
                    `department_id` = ?, `specialization` = ?, `status` = ?
              WHERE `id` = ?',
            'sssssissi',
            array_merge($values, [$id])
        );

        flash_set('success', 'Lecturer updated.');
        redirect('lecturers/view.php?id=' . $id);
    }

    $newId = db_insert(
        'INSERT INTO `lecturers` (`staff_no`, `first_name`, `last_name`, `email`, `phone`,
                                  `department_id`, `specialization`, `status`)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        'sssssiss',
        $values
    );

    flash_set('success', 'Lecturer added.');
    redirect('lecturers/view.php?id=' . $newId);
}

if (!$canManage) {
    require_permission('lecturers.manage');
}

$departments = department_options(null, false);

layout_start($isEdit ? 'Edit lecturer' : 'Add lecturer', 'lecturers');

render_page_head([
    'eyebrow'  => 'Academic staff',
    'title'    => $isEdit ? 'Edit lecturer' : 'Add a lecturer',
    'subtitle' => $isEdit
        ? 'Changes apply to the courses this lecturer is assigned to.'
        : 'A lecturer belongs to exactly one academic department.',
    'actions'  => '<a class="btn btn--quiet" href="' . e(url($path)) . '">' . icon('arrow-left', 15) . '<span>Back to list</span></a>',
]);

$cancelTo = $isEdit ? 'lecturers/view.php?id=' . $id : $path;
?>

<form class="card" method="post" action="<?= e(url('lecturers/form.php' . ($isEdit ? '?id=' . $id : ''))) ?>" novalidate data-once>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e((string) $id) ?>">
    <?php endif; ?>

    <div class="card__body">
        <div class="formgrid">
            <?= text_field('staff_no', 'Staff number', [
                'value'       => (string) $record['staff_no'],
                'required'    => true,
                'maxlength'   => 20,
                'placeholder' => 'LG0020',
                'hint'        => 'Reference used in timetables and reports. Letters, numbers, hyphens.',
            ]) ?>

            <?= text_field('first_name', 'First name', [
                'value'     => (string) $record['first_name'],
                'required'  => true,
                'maxlength' => 60,
                'hint'      => 'Given name, as printed on the payroll records.',
            ]) ?>

            <?= text_field('last_name', 'Last name', [
                'value'     => (string) $record['last_name'],
                'required'  => true,
                'maxlength' => 60,
                'hint'      => 'Family name, used for the list ordering.',
            ]) ?>

            <?= text_field('email', 'Email', [
                'value'       => (string) $record['email'],
                'type'        => 'email',
                'required'    => true,
                'maxlength'   => 150,
                'autocomplete' => 'email',
                'placeholder' => 'name@hagmah.edu',
                'hint'        => 'Used once; no other lecturer or student may hold it.',
            ]) ?>

            <?= text_field('phone', 'Phone', [
                'value'       => (string) $record['phone'],
                'maxlength'   => 30,
                'placeholder' => '+960 7700 0000',
                'hint'        => 'Optional; a mobile number in international format.',
            ]) ?>

            <?= select_field('status', 'Status', lecturer_status_options(), [
                'value'    => (string) $record['status'],
                'required' => true,
                'hint'     => 'Set to on leave or inactive to flag the teaching record.',
            ]) ?>

            <?= select_field('department_id', 'Department', $departments, [
                'value'    => (string) $record['department_id'],
                'required' => true,
                'wide'     => true,
                'hint'     => 'The department this lecturer is employed by.',
            ]) ?>

            <?= text_field('specialization', 'Specialism', [
                'value'     => (string) $record['specialization'],
                'maxlength' => 150,
                'wide'      => true,
                'hint'      => 'Their teaching and research focus, for example "Distributed Systems".',
            ]) ?>
        </div>
    </div>

    <div class="card__body" style="border-top:1px solid var(--border)">
        <div class="formactions">
            <button class="btn btn--primary" type="submit">
                <?= icon('check', 16) ?><span><?= $isEdit ? 'Save changes' : 'Add lecturer' ?></span>
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