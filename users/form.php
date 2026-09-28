<?php
/**
 * Users — create and edit.
 *
 * One file for both. Passwords are hashed with the default algorithm and
 * never stored or echoed back; on edit the field is optional and left alone
 * when blank. Only an administrator creates accounts, so the role picker is
 * here rather than in the sign-up screen (there is no sign-up screen).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();
require_permission('users.manage');

$path = 'users/index.php';

$id     = (int) query_int('id');
$isEdit = $id > 0;
$me     = current_user();

$record = [
    'id'         => 0,
    'full_name'  => '',
    'username'   => '',
    'email'      => '',
    'role'       => 'registrar',
    'status'     => 'active',
    'created_at' => null,
];

if ($isEdit) {
    $found = db_row('SELECT * FROM `users` WHERE `id` = ?', 'i', [$id]);

    if ($found === null) {
        not_found('That account does not exist. It may have been deleted.');
    }

    foreach ($record as $key => $unused) {
        if (array_key_exists($key, $found) && $found[$key] !== null) {
            $record[$key] = $found[$key];
        }
    }
    $record['id'] = (int) $record['id'];
}

// ---------------------------------------------------------------------
// Save
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('users.manage');

    $id     = (int) input_int('id', 0);
    $isEdit = $id > 0;

    $data       = collect_input();
    $password   = trim((string) input('password'));
    $confirm    = trim((string) input('password_confirm'));
    $data['id'] = $id;

    $rules = [
        'full_name' => 'required|string|max:100',
        'username'  => 'required|string|min:3|max:30|regex:/^[a-zA-Z][a-zA-Z0-9_\.]*$/|unique:users,username,{id}',
        'email'     => 'required|email|max:120|unique:users,email,{id}',
        'role'      => 'required|in:admin,registrar',
        'status'    => 'required|in:active,inactive',
    ];

    if (!$isEdit) {
        $rules['password'] = 'required|min:8|max:100';
    } elseif ($password !== '') {
        $rules['password'] = 'min:8|max:100';
    }

    $errors = validate($data, $rules, [
        'full_name' => 'Full name',
        'username'  => 'Username',
        'email'     => 'Email',
        'role'      => 'Role',
        'status'    => 'Status',
        'password'  => 'Password',
    ]);

    if ($errors === [] && $password !== '') {
        $errors = validate(['password_confirm' => $confirm], ['password_confirm' => 'required'], ['password_confirm' => 'Confirm password']);
        if ($confirm !== $password) {
            $errors['password_confirm'] = 'The confirmation does not match the password.';
        }
    }

    if ($errors !== []) {
        redirect_back_with_errors('users/form.php' . ($id > 0 ? '?id=' . $id : ''), $errors, $data, 'form');
    }

    $values = [
        (string) $data['full_name'],
        (string) $data['username'],
        mb_strtolower((string) $data['email']),
        (string) $data['role'],
        (string) $data['status'],
    ];

    if ($isEdit) {
        if ($password !== '') {
            db_execute(
                'UPDATE `users`
                    SET `full_name` = ?, `username` = ?, `email` = ?, `role` = ?, `status` = ?,
                        `password_hash` = ?
                  WHERE `id` = ?',
                'ssssssi',
                array_merge($values, [password_hash($password, PASSWORD_DEFAULT), $id])
            );
        } else {
            db_execute(
                'UPDATE `users`
                    SET `full_name` = ?, `username` = ?, `email` = ?, `role` = ?, `status` = ?
                  WHERE `id` = ?',
                'sssssi',
                array_merge($values, [$id])
            );
        }

        flash_set('success', 'Account updated.');
        redirect('users/view.php?id=' . $id);
    }

    $newId = db_insert(
        'INSERT INTO `users` (`full_name`, `username`, `email`, `role`, `status`, `password_hash`)
              VALUES (?, ?, ?, ?, ?, ?)',
        'ssssss',
        array_merge($values, [password_hash($password, PASSWORD_DEFAULT)])
    );

    flash_set('success', 'Account created.');
    redirect('users/view.php?id=' . $newId);
}

$roles   = role_options();
$statuses = user_status_options();

layout_start($isEdit ? 'Edit user' : 'Add a user', 'users');

render_page_head([
    'eyebrow'  => 'Access control',
    'title'    => $isEdit ? 'Edit account' : 'Add an account',
    'subtitle' => $isEdit
        ? 'Changes take effect on their next sign-in.'
        : 'Give the new person a username, an email and a password to sign in with.',
    'actions'  => '<a class="btn btn--quiet" href="' . e(url($path)) . '">' . icon('arrow-left', 15) . '<span>Back to list</span></a>',
]);

$cancelTo = $isEdit ? 'users/view.php?id=' . $id : $path;
?>

<form class="card" method="post" action="<?= e(url('users/form.php' . ($isEdit ? '?id=' . $id : ''))) ?>" novalidate data-once>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e((string) $id) ?>">
    <?php endif; ?>

    <div class="card__body">
        <div class="formgrid">
            <?= text_field('full_name', 'Full name', [
                'value'       => (string) $record['full_name'],
                'required'    => true,
                'maxlength'   => '100',
                'placeholder' => 'e.g. Mariyam Hassan',
                'autocomplete' => 'name',
            ]) ?>

            <?= text_field('username', 'Username', [
                'value'         => (string) $record['username'],
                'required'      => true,
                'maxlength'     => '30',
                'placeholder'   => 'e.g. mariyam.hassan',
                'autocomplete'  => 'username',
                'hint'          => 'Letters and digits, starting with a letter. Used to sign in.',
            ]) ?>

            <?= text_field('email', 'Email', [
                'value'        => (string) $record['email'],
                'type'         => 'email',
                'required'     => true,
                'maxlength'    => '120',
                'autocomplete' => 'email',
            ]) ?>

            <?= select_field('role', 'Role', $roles, [
                'value'    => (string) $record['role'],
                'required' => true,
                'hint'     => 'Administrators manage accounts and everything else; registrars manage academic records.',
            ]) ?>

            <?= select_field('status', 'Status', $statuses, [
                'value'    => (string) $record['status'],
                'required' => true,
                'hint'     => 'Inactive accounts cannot sign in.',
            ]) ?>

            <?php if ($isEdit): ?>
                <?= text_field('password', 'New password', [
                    'type'        => 'password',
                    'maxlength'   => '100',
                    'autocomplete' => 'new-password',
                    'hint'        => 'Leave blank to keep the current password.',
                ]) ?>

                <?= text_field('password_confirm', 'Confirm new password', [
                    'type'         => 'password',
                    'maxlength'    => '100',
                    'autocomplete' => 'new-password',
                    'hint'         => 'Type the new password again.',
                ]) ?>
            <?php else: ?>
                <?= text_field('password', 'Password', [
                    'type'         => 'password',
                    'required'     => true,
                    'maxlength'    => '100',
                    'autocomplete' => 'new-password',
                    'hint'         => 'At least 8 characters. The password is stored hashed and can never be read back.',
                ]) ?>

                <?= text_field('password_confirm', 'Confirm password', [
                    'type'         => 'password',
                    'required'     => true,
                    'maxlength'    => '100',
                    'autocomplete' => 'new-password',
                ]) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card__body" style="border-top:1px solid var(--border)">
        <div class="formactions">
            <button class="btn btn--primary" type="submit">
                <?= icon('check', 16) ?><span><?= $isEdit ? 'Save changes' : 'Create account' ?></span>
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