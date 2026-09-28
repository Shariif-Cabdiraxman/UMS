<?php
/**
 * Departments — create and edit.
 *
 * One file for both, because the two screens differ only in whether a record
 * was loaded. Splitting them would mean two forms to keep in step.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path      = 'departments/index.php';
$canManage = can('departments.manage');

$id     = (int) query_int('id');
$isEdit = $id > 0;

$record = [
    'id'          => 0,
    'code'        => '',
    'name'        => '',
    'description' => '',
    'faculty_id'  => '',
    'head_id'     => '',
    'created_at'  => null,
];

if ($isEdit) {
    $found = db_row('SELECT * FROM `departments` WHERE `id` = ?', 'i', [$id]);

    if ($found === null) {
        not_found('That department does not exist. It may have been deleted.');
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
    guard_write('departments.manage');

    $id          = (int) input_int('id', 0);
    $isEdit      = $id > 0;
    $data        = collect_input(['faculty_id', 'head_id']);
    $data['id']  = $id;

    $errors = validate($data, [
        'code'        => 'required|max:20|unique:departments,code,{id}',
        'name'        => 'required|max:150',
        'faculty_id'  => 'required|exists:faculties,id',
        'head_id'     => 'nullable|exists:lecturers,id',
        'description' => 'nullable|max:2000',
    ], [
        'code'        => 'Code',
        'name'        => 'Department name',
        'faculty_id'  => 'Faculty',
        'head_id'     => 'Head of department',
        'description' => 'Description',
    ]);

    // Normalise the code so that dep-sci and DEP-SCI cannot both exist. The
    // format is checked here rather than with a regex rule so the value has
    // already been folded to upper case by the time it is validated.
    $code = strtoupper((string) ($data['code'] ?? ''));

    if ($code !== '' && preg_match('/^[A-Z0-9][A-Z0-9\-\/]*$/', $code) !== 1) {
        $errors['code'] = 'Use letters, numbers, hyphens or slashes only.';
    }

    $data['code'] = $code;

    if ($errors !== []) {
        $record = array_merge($record, $data);
        redirect_back_with_errors('departments/form.php' . ($id > 0 ? '?id=' . $id : ''), $errors, $data, 'form');
    }

    $facultyId = (int) $data['faculty_id'];
    $headId    = ($data['head_id'] ?? '') === '' ? null : (int) $data['head_id'];

    // A head of department must work inside the faculty that owns this
    // department, otherwise the two screens would tell visitors different
    // stories. The lecturer is reached through their own department, so the
    // faculty being compared is the one that employs them.
    if ($headId !== null) {
        $headFaculty = db_value(
            'SELECT d.`faculty_id`
               FROM `lecturers` l
               JOIN `departments` d ON d.`id` = l.`department_id`
              WHERE l.`id` = ?
              LIMIT 1',
            'i',
            [$headId]
        );

        if ($headFaculty === null || (int) $headFaculty !== $facultyId) {
            $record = array_merge($record, $data);
            redirect_back_with_errors(
                'departments/form.php' . ($id > 0 ? '?id=' . $id : ''),
                ['head_id' => 'That lecturer works in a different faculty, so cannot head this department.'],
                $data,
                'form'
            );
        }
    }

    $values = [
        $code,
        (string) $data['name'],
        ($data['description'] ?? '') === '' ? null : $data['description'],
        $facultyId,
        $headId,
    ];

    if ($id > 0) {
        db_execute(
            'UPDATE `departments`
                SET `code` = ?, `name` = ?, `description` = ?, `faculty_id` = ?, `head_id` = ?
              WHERE `id` = ?',
            'sssisi',
            array_merge($values, [$id])
        );

        flash_set('success', 'Department updated.');
        redirect('departments/view.php?id=' . $id);
    }

    $newId = db_insert(
        'INSERT INTO `departments` (`code`, `name`, `description`, `faculty_id`, `head_id`)
              VALUES (?, ?, ?, ?, ?)',
        'ssssi',
        $values
    );

    flash_set('success', 'Department added.');
    redirect('departments/view.php?id=' . $newId);
}

if (!$canManage) {
    require_permission('departments.manage');
}

$faculties = faculty_options(false);
$facultyId = (int) $record['faculty_id'] > 0 ? (int) $record['faculty_id'] : null;
// lecturer_options() takes a department id, not a faculty id. On create there is
// no department yet, so the head picker offers the full staff list.
$departmentId = $id > 0 ? $id : null;

layout_start($isEdit ? 'Edit department' : 'Add department', 'departments');

render_page_head([
    'eyebrow'  => 'Academic structure',
    'title'    => $isEdit ? 'Edit department' : 'Add a department',
    'subtitle' => $isEdit
        ? 'Changes apply across every course, lecturer and student record underneath it.'
        : 'A department sits inside a faculty and carries the courses and staff beneath it.',
    'actions'  => '<a class="btn btn--quiet" href="' . e(url($path)) . '">' . icon('arrow-left', 15) . '<span>Back to list</span></a>',
]);

$cancelTo = $isEdit ? 'departments/view.php?id=' . $id : $path;
?>

<form class="card" method="post" action="<?= e(url('departments/form.php' . ($isEdit ? '?id=' . $id : ''))) ?>" novalidate data-once>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e((string) $id) ?>">
    <?php endif; ?>

    <div class="card__body">
        <div class="formgrid">
            <?= text_field('code', 'Code', [
                'value'       => (string) $record['code'],
                'required'    => true,
                'maxlength'   => 20,
                'placeholder' => 'DEP-CS',
                'hint'        => 'Short identifier used on reports. Letters, numbers, hyphens.',
            ]) ?>

            <?= text_field('name', 'Department name', [
                'value'     => (string) $record['name'],
                'required'  => true,
                'maxlength' => 150,
                'hint'      => 'For example: Department of Computer Science',
            ]) ?>

            <?= select_field('faculty_id', 'Faculty', $faculties, [
                'value'    => (string) $record['faculty_id'],
                'required' => true,
                'wide'     => true,
                'hint'     => 'The faculty this department belongs to.',
            ]) ?>

            <?= select_field('head_id', 'Head of department', lecturer_options($departmentId, true), [
                'value' => (string) $record['head_id'],
                'wide'  => true,
                'hint'  => 'Optional. The list narrows to the staff of this department.',
            ]) ?>

            <?= textarea_field('description', 'Description', [
                'value'       => (string) $record['description'],
                'rows'        => 4,
                'maxlength'   => 2000,
                'wide'        => true,
                'placeholder' => 'What this department covers, and anything a new reader should know.',
            ]) ?>
        </div>
    </div>

    <div class="card__body" style="border-top:1px solid var(--border)">
        <div class="formactions">
            <button class="btn btn--primary" type="submit">
                <?= icon('check', 16) ?><span><?= $isEdit ? 'Save changes' : 'Add department' ?></span>
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
