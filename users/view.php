<?php
/**
 * Users — a single account.
 *
 * What an administrator needs to see about one person: identity, role,
 * account state, the last sign-in, and the notices they authored.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();
require_permission('users.manage');

$path = 'users/index.php';
$id   = query_int('id');

if ($id < 1) {
    not_found('No account was requested. Open an account from the list to see its details.');
}

$user = db_row(
    'SELECT u.`id`, u.`username`, u.`email`, u.`full_name`, u.`role`, u.`status`,
            u.`last_login_at`, u.`created_at`, u.`updated_at`
       FROM `users` u
      WHERE u.`id` = ?
      LIMIT 1',
    'i',
    [$id]
);

if ($user === null) {
    not_found('That account does not exist. It may have been deleted.');
}

$announcementCount = (int) db_value('SELECT COUNT(*) FROM `announcements` WHERE `author_id` = ?', 'i', [$id]);

$announcements = db_all(
    'SELECT `id`, `title`, `status`, `published_at`
       FROM `announcements`
      WHERE `author_id` = ?
      ORDER BY `created_at` DESC
      LIMIT 8',
    'i',
    [$id]
);

$me = current_user();

$noticeItems = [];
foreach ($announcements as $notice) {
    $noticeItems[] = [
        'label' => $notice['title'],
        'meta'  => $notice['published_at'] !== null
            ? 'Published ' . e(format_datetime($notice['published_at']))
            : e(humanize($notice['status'])),
        'href'  => url('announcements/view.php?id=' . (int) $notice['id']),
        'trail' => badge($notice['status']),
    ];
}

layout_start('User account', 'users');

render_page_head([
    'eyebrow'  => 'Access control',
    'title'    => $user['full_name'],
    'subtitle' => 'Account #' . $id,
    'meta'     => 'Created ' . e(format_datetime($user['created_at']))
        . ' &middot; last changed ' . e(format_datetime($user['updated_at'])),
    'actions'  => '<a class="btn btn--primary" href="' . e(url('users/form.php?id=' . $id)) . '">'
        . icon('edit', 15) . '<span>Edit account</span></a>'
        . '<a class="btn btn--quiet" href="' . e(url($path)) . '">' . icon('arrow-left', 15) . '<span>Back to list</span></a>',
]);

$first = mb_substr((string) $user['full_name'], 0, 1);
$last  = mb_substr(explode(' ', (string) $user['full_name'])[1] ?? '', 0, 1);
?>

<section class="recordhead">
    <?= render_avatar($first, $last) ?>
    <div class="recordhead__text">
        <h2 class="recordhead__title"><?= e($user['full_name']) ?>
            <?= (int) $user['id'] === (int) $me['id'] ? badge('active', 'This is you') : '' ?>
        </h2>
        <p class="recordhead__meta">
            <span class="table__id"><?= e($user['username']) ?></span>
            <span><?= e($user['email']) ?></span>
            <?= role_badge($user['role']) ?>
            <?= badge($user['status']) ?>
        </p>
    </div>
</section>

<div class="split split--wide mb-3">

    <div class="stackgap">

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Account</h2>
                    <p class="panel__sub">What this account can do and how it is used</p>
                </div>
            </div>
            <div class="panel__body">
                <?php render_deflist([
                    ['term' => 'Full name', 'desc' => e($user['full_name'])],
                    ['term' => 'Email', 'desc' => e($user['email'])],
                    ['term' => 'Signed in as', 'desc' => role_badge($user['role'])],
                    ['term' => 'Status', 'desc' => badge($user['status'])],
                    ['term' => 'Last sign-in', 'desc' => $user['last_login_at'] !== null
                        ? e(format_datetime($user['last_login_at']))
                        : '<span class="text-3">This account has never signed in</span>'],
                    ['term' => 'Created', 'desc' => e(format_datetime($user['created_at']))],
                ]); ?>
            </div>
        </section>

        <?php if ((int) $user['id'] !== (int) $me['id']): ?>
            <section class="panel">
                <div class="panel__body" style="display:flow-root">
                    <h3 class="panel__title" style="font-size:.9rem;margin-bottom:.35rem">Remove this account</h3>
                    <p class="text-sm text-3" style="margin-bottom:.75rem">
                        Deleting is permanent. An account that authored announcements must keep those notices
                        or have them reassigned first. You cannot delete your own account while signed in.
                    </p>
                    <div class="formactions">
                        <?= render_delete_form($path, (int) $user['id'], 'Delete this user account') ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

    </div>

    <div class="stackgap">

        <section class="panel">
            <div class="panel__head">
                <div class="panel__titles">
                    <h2 class="panel__title">Notices authored</h2>
                    <p class="panel__sub">
                        <?= e(format_number($announcementCount)) ?> announcement<?= $announcementCount === 1 ? '' : 's' ?> with this byline
                    </p>
                </div>
            </div>
            <div class="panel__body">
                <?php render_related_list(
                    $noticeItems,
                    'This account has not written any announcements yet.',
                    'announcements'
                ); ?>
            </div>
        </section>

    </div>
</div>

<?php layout_end(); ?>