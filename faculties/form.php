<?php
/**
 * Faculties — create and edit.
 *
 * One file for both, because the two screens differ only in whether a record
 * was loaded. Splitting them would mean two forms to keep in step.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path      = 'faculties/index.php';
$canManage = can('faculties.manage');

$id     = (int) query_int('id');
$isEdit = $id > 0;

$record = [
    'id'          => 0,
    'code'        => '',
    'name'        => '',
    'description' => '',
    'dean_id'     => '',
    'created_at'  => null,
];

if ($isEdit) {
    $found = db_row('SELECT * FROM `faculties` WHERE `id` = ?', 'i', [$id]);

    if ($found === null) {
        not_found('That faculty does not exist. It may have been deleted.');
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
    guard_write('faculties.manage');

    $id       = (int) input_int('id', 0);
    $isEdit   = $id > 0;
    $data     = collect_input(['dean_id']);
    $data['id'] = $id;

    $errors = validate($data, [
        'code'        => 'required|max:20|unique:faculties,code,{id}',
        'name'        => 'required|max:150',
        'description' => 'nullable|max:2000',
        'dean_id'     => 'nullable|exists:lecturers,id',
    ], [
        'code'        => 'Code',
        'name'        => 'Faculty name',
        'description' => 'Description',
        'dean_id'     => 'Dean',
    ]);

    // Normalise the code so that fct-sci and FCT-SCI cannot both exist. The
    // format is checked here rather than with a regex rule so the value has
    // already been folded to upper case by the time it is validated.
    $code = strtoupper((string) ($data['code'] ?? ''));

    if ($code !== '' && preg_match('/^[A-Z0-9][A-Z0-9\-\/]*$/', $code) !== 1) {
        $errors['code'] = 'Use letters, numbers, hyphens or slashes only.';
    }

    $data['code'] = $code;

    if ($errors !== []) {
        $record = array_merge($record, $data);
        redirect_back_with_errors('faculties/form.php' . ($id > 0 ? '?id=' . $id : ''), $errors, $data, 'form');
    }

    $deanId = ($data['dean_id'] ?? '') === '' ? null : (int) $data['dean_id'];

    $values = [
        $code,
        (string) $data['name'],
        ($data['description'] ?? '') === '' ? null : $data['description'],
        $deanId,
    ];

    if ($id > 0) {
        db_execute(
            'UPDATE `faculties`
                SET `code` = ?, `name` = ?, `description` = ?, `dean_id` = ?
              WHERE `id` = ?',
            'sssis',
            array_merge($values, [$id])
        );

        flash_set('success', 'Faculty updated.');
        redirect('faculties/view.php?id=' . $id);
    }

    $newId = db_insert(
        'INSERT INTO `faculties` (`code`, `name`, `description`, `dean_id`)
              VALUES (?, ?, ?, ?)',
        'sssi',
        $values
    );

    flash_set('success', 'Faculty added.');
    redirect('faculties/view.php?id=' . $newId);
}

if (!$canManage) {
    require_permission('faculties.manage');
}

$deans = lecturer_options(null, true);

layout_start($isEdit ? 'Edit faculty' : 'Add faculty', 'faculties');

render_page_head([
    'eyebrow'  => 'Academic structure',
    'title'    => $isEdit ? 'Edit faculty' : 'Add a faculty',
    'subtitle' => $isEdit
        ? 'Changes apply across every department and student record underneath it.'
        : 'A faculty is the top level of the academic structure.',
    'actions'  => '<a class="btn btn--quiet" href="' . e(url($path)) . '">' . icon('arrow-left', 15) . '<span>Back to list</span></a>',
]);

$cancelTo = $isEdit ? 'faculties/view.php?id=' . $id : $path;
?>

<form class="card" method="post" action="<?= e(url('faculties/form.php' . ($isEdit ? '?id=' . $id : ''))) ?>" novalidate data-once>
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
                'placeholder' => 'FCT-SCI',
                'hint'        => 'Short identifier used on reports. Letters, numbers, hyphens.',
            ]) ?>

            <?= text_field('name', 'Faculty name', [
                'value'     => (string) $record['name'],
                'required'  => true,
                'maxlength' => 150,
                'hint'      => 'For example: Faculty of Science and Technology',
            ]) ?>

            <?= select_field('dean_id', 'Dean', $deans, [
                'value' => (string) $record['dean_id'],
                'wide'  => true,
                'hint'  => 'Optional. Deans are drawn from the lecturer records.',
            ]) ?>

            <?= textarea_field('description', 'Description', [
                'value'       => (string) $record['description'],
                'rows'        => 4,
                'maxlength'   => 2000,
                'wide'        => true,
                'placeholder' => 'What this faculty covers, and anything a new reader should know.',
            ]) ?>
        </div>
    </div>

    <div class="card__body" style="border-top:1px solid var(--border)">
        <div class="formactions">
            <button class="btn btn--primary" type="submit">
                <?= icon('check', 16) ?><span><?= $isEdit ? 'Save changes' : 'Add faculty' ?></span>
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
