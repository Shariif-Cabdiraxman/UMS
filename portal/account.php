<?php
/**
 * Student portal — account settings.
 *
 * The student can change their own password. The account identifier and
 * status are managed by the registrar.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/portal.php';

$student   = require_student();
$studentId = (int) $student['id'];
$account   = student_account($studentId);

$errors = [];

if (is_post() && $account !== null) {
    verify_csrf();

    $current = (string) input('current_password', '');
    $new     = (string) input('new_password', '');
    $confirm = (string) input('confirm_password', '');

    if ($current === '') {
        $errors['current_password'] = 'Enter your current password.';
    } elseif (!password_verify($current, $account['password_hash'] ?? '')) {
        $errors['current_password'] = 'That is not your current password.';
    }

    if (strlen($new) < 8) {
        $errors['new_password'] = 'Use at least 8 characters.';
    }

    if ($confirm === '' || $new !== $confirm) {
        $errors['confirm_password'] = 'The two new passwords do not match.';
    }

    if ($errors === []) {
        db_execute(
            'UPDATE `student_accounts` SET `password_hash` = ? WHERE `student_id` = ?',
            'si',
            [password_hash($new, PASSWORD_DEFAULT), $studentId]
        );

        session_regenerate_id(true);
        flash_set('success', 'Your password has been changed.');
        redirect('portal/account.php');
    }
}

layout_start('Account settings', 'account');

render_page_head([
    'eyebrow'  => 'Student portal',
    'title'    => 'Account settings',
    'subtitle' => 'The sign-in attached to your student record.',
    'meta'     => 'Signed in as ' . e($student['student_no']),
    'actions'  => '<a class="btn btn--quiet" href="' . e(url('portal/dashboard.php')) . '">'
        . icon('arrow-left', 15) . '<span>Back to dashboard</span></a>',
]);
?>

<div class="split split--wide">
    <div class="stackgap">
        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Account details</h2>
                    <p class="panel__sub">Held by the registrar&rsquo;s office</p>
                </div>
            </div>
            <div class="panel__body">
                <?php if ($account === null): ?>
                    <p class="text-2 text-sm">No portal account is attached to this student record.</p>
                <?php else: ?>
                    <?php render_deflist([
                        ['term' => 'Student number', 'desc' => '<span class="table__id">' . e($student['student_no']) . '</span>'],
                        ['term' => 'Email', 'desc' => '<a class="strong" href="mailto:' . e($student['email']) . '">' . e($student['email']) . '</a>'],
                        ['term' => 'Status', 'desc' => badge($account['status'])],
                        ['term' => 'Last sign-in', 'desc' => e(format_datetime($account['last_login_at']))],
                        ['term' => 'Issued', 'desc' => e(format_datetime($account['created_at']))],
                        ['term' => 'Session', 'desc' => 'Protected by a one-hour idle timeout'],
                    ]); ?>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($account !== null): ?>
            <section class="panel">
                <div class="panel__head">
                    <div class="panel__titles">
                        <h2 class="panel__title">Change password</h2>
                        <p class="panel__sub">Passwords are stored as one-way hashes</p>
                    </div>
                </div>
                <div class="panel__body">
                    <form method="post" action="<?= e(url('portal/account.php')) ?>" novalidate>
                        <?= csrf_field() ?>

                        <div class="field<?= isset($errors['current_password']) ? ' is-invalid' : '' ?>">
                            <label class="field__label" for="current_password">Current password</label>
                            <div class="field__control">
                                <span class="field__leading"><?= icon('lock', 17) ?></span>
                                <input type="password" id="current_password" name="current_password"
                                       autocomplete="current-password" required
                                       <?= isset($errors['current_password']) ? 'aria-invalid="true" aria-describedby="current-password-error"' : '' ?>>
                            </div>
                            <?php if (isset($errors['current_password'])): ?>
                                <p class="field__error" id="current-password-error"><?= icon('alert', 13) ?><?= e($errors['current_password']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="field<?= isset($errors['new_password']) ? ' is-invalid' : '' ?>">
                            <label class="field__label" for="new_password">New password</label>
                            <div class="field__control">
                                <span class="field__leading"><?= icon('lock', 17) ?></span>
                                <input type="password" id="new_password" name="new_password"
                                       autocomplete="new-password" minlength="8" required
                                       <?= isset($errors['new_password']) ? 'aria-invalid="true" aria-describedby="new-password-error"' : '' ?>>
                            </div>
                            <?php if (isset($errors['new_password'])): ?>
                                <p class="field__error" id="new-password-error"><?= icon('alert', 13) ?><?= e($errors['new_password']) ?></p>
                            <?php else: ?>
                                <p class="field__hint">Use at least 8 characters.</p>
                            <?php endif; ?>
                        </div>

                        <div class="field<?= isset($errors['confirm_password']) ? ' is-invalid' : '' ?>">
                            <label class="field__label" for="confirm_password">Confirm new password</label>
                            <div class="field__control">
                                <span class="field__leading"><?= icon('lock', 17) ?></span>
                                <input type="password" id="confirm_password" name="confirm_password"
                                       autocomplete="new-password" minlength="8" required
                                       <?= isset($errors['confirm_password']) ? 'aria-invalid="true" aria-describedby="confirm-password-error"' : '' ?>>
                            </div>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <p class="field__error" id="confirm-password-error"><?= icon('alert', 13) ?><?= e($errors['confirm_password']) ?></p>
                            <?php endif; ?>
                        </div>

                        <button class="btn btn--primary" type="submit">
                            <?= icon('check', 15) ?><span>Change password</span>
                        </button>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <div class="stackgap">
        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Session</h2>
                </div>
            </div>
            <div class="panel__body">
                <p class="text-2 text-sm">Signing out ends this session on this device.</p>
                <a class="btn btn--danger mt-2" href="<?= e(url('auth/logout.php')) ?>">
                    <?= icon('logout', 15) ?><span>Sign out</span>
                </a>
            </div>
        </section>
    </div>
</div>

<?php layout_end(); ?>
