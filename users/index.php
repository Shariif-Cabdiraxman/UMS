<?php
/**
 * Users — the list.
 *
 * The people who can sign in. Kept deliberately small: there are few user
 * accounts in a department, and the screen exists to see who holds which
 * role and when they last signed in.
 *
 * Only administrators reach this page. An account cannot delete itself, an
 * active administrator cannot be deleted while they are the last one, and a
 * user who authored announcements stays because the notices depend on them.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();
require_permission('users.manage');

$path = 'users/index.php';

// ---------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('users.manage');

    if (input('action') === 'delete') {
        $targetId = (int) input_int('delete_id', 0);
        $me       = current_user();

        if ($targetId === (int) $me['id']) {
            flash_set('error', 'You cannot delete your own account while signed in. Ask another administrator.');
            redirect($path);
        }

        $target = $targetId > 0 ? db_row(
            'SELECT `role`, `status` FROM `users` WHERE `id` = ? LIMIT 1',
            'i',
            [$targetId]
        ) : null;

        if ($target === null) {
            flash_set('error', 'That account does not exist. It may have been deleted already.');
            redirect($path);
        }

        if ($target['role'] === 'admin' && $target['status'] === 'active') {
            $otherAdmins = (int) db_value(
                'SELECT COUNT(*) FROM `users` WHERE `role` = \'admin\' AND `status` = \'active\' AND `id` <> ?',
                'i',
                [$targetId]
            );

            if ($otherAdmins === 0) {
                flash_set('error', 'That is the last active administrator. Create another administrator before removing it.');
                redirect($path);
            }
        }

        $authored = (int) db_value('SELECT COUNT(*) FROM `announcements` WHERE `author_id` = ?', 'i', [$targetId]);

        if ($authored > 0) {
            flash_set(
                'error',
                e(format_number($authored)) . ' announcement' . ($authored === 1 ? ' is' : 's are') . ' by this user. Transfer or delete those notices before removing the account.'
            );
            redirect($path);
        }

        delete_record('users', $targetId, 'User', $path);
    }
}

// ---------------------------------------------------------------------
// List state
// ---------------------------------------------------------------------

$search = query_string('q');
$role   = query_string('role');
$status = query_string('status');

$sort = sort_state([
    'name'       => 'full_name',
    'username'   => 'username',
    'email'      => 'email',
    'role'       => 'role',
    'status'     => 'status',
    'last_login' => 'last_login_at',
], 'name');

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(u.`full_name` LIKE ? OR u.`username` LIKE ? OR u.`email` LIKE ?)';
    $like    = like_param($search);
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}

if ($role !== '') {
    $where[]  = 'u.`role` = ?';
    $params[] = $role;
    $types   .= 's';
}

if ($status !== '') {
    $where[]  = 'u.`status` = ?';
    $params[] = $status;
    $types   .= 's';
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$total = (int) db_value('SELECT COUNT(*) FROM `users` u ' . $whereSql, $types, $params);

$page  = paginate($total, current_page_number(), current_page_size());
$types .= 'ii';

$rows = db_all(
    'SELECT u.`id`, u.`username`, u.`email`, u.`full_name`, u.`role`, u.`status`,
            u.`last_login_at`, u.`created_at`,
            (SELECT COUNT(*) FROM `announcements` a WHERE a.`author_id` = u.`id`) AS `announcement_count`
       FROM `users` u
       ' . $whereSql . '
      ORDER BY ' . $sort['sql'] . '
      LIMIT ? OFFSET ?',
    $types,
    array_merge($params, [$page['per_page'], $page['offset']])
);

$me       = current_user();
$myId     = (int) $me['id'];
$roles    = ['' => 'All roles'] + role_options();
$statuses = ['' => 'All statuses'] + user_status_options();

layout_start('Users', 'users');

render_page_head([
    'eyebrow'  => 'Access control',
    'title'    => 'User accounts',
    'subtitle' => 'The accounts that can sign in to this system.',
    'meta'     => e(format_number($total)) . ' ' . ($total === 1 ? 'account' : 'accounts') . ' registered',
    'actions'  => '<a class="btn btn--primary" href="' . e(url('users/form.php')) . '">' . icon('plus', 16) . '<span>Add user</span></a>',
]);

render_list_toolbar([
    'action'      => $path,
    'placeholder' => 'Search by name, username or email…',
    'filters'     => filter_select('role', 'Filter by role', $roles, $role, 'All roles')
        . filter_select('status', 'Filter by status', $statuses, $status, 'All statuses'),
]);

if ($rows === []) {
    echo '<div class="card">';
    render_empty_state(
        'users',
        'No accounts match those filters',
        'Administrators and registrars are listed here. Try a different word, or clear the filters.',
        ''
    );
    echo '</div>';
} else {
    ?>
    <div class="tablewrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">User</th>
                    <?= th_sort('username', 'Username', $sort) ?>
                    <?= th_sort('role', 'Role', $sort) ?>
                    <?= th_sort('status', 'Status', $sort) ?>
                    <?= th_sort('last_login', 'Last sign-in', $sort) ?>
                    <th class="num" scope="col">Notices</th>
                    <th scope="col" class="actions">Record</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <?= render_avatar(
                            mb_substr((string) $row['full_name'], 0, 1),
                            mb_substr(explode(' ', (string) $row['full_name'])[1] ?? '', 0, 1),
                            'users/view.php?id=' . (int) $row['id']
                        ) ?>
                        <span class="table__ident">
                            <a class="strong" href="<?= e(url('users/view.php?id=' . (int) $row['id'])) ?>"><?= e($row['full_name']) ?></a>
                            <span class="table__sub"><?= e($row['email']) ?></span>
                        </span>
                    </td>
                    <td><span class="table__id"><?= e($row['username']) ?></span></td>
                    <td><?= role_badge($row['role']) ?></td>
                    <td><?= badge($row['status']) ?></td>
                    <td class="text-3"><?= $row['last_login_at'] !== null
                        ? e(format_datetime($row['last_login_at']))
                        : 'Never' ?></td>
                    <td class="num"><?= e(format_number((int) $row['announcement_count'])) ?></td>
                    <td class="actions">
                        <a class="btn btn--sm" href="<?= e(url('users/view.php?id=' . (int) $row['id'])) ?>">Open</a>
                        <a class="iconbtn" href="<?= e(url('users/form.php?id=' . (int) $row['id'])) ?>"
                           title="Edit user" aria-label="Edit the account of <?= e($row['full_name']) ?>">
                            <?= icon('edit', 15) ?><span class="sr-only">Edit</span>
                        </a>
                        <?php if ((int) $row['id'] !== $myId): ?>
                            <?= render_delete_form($path, (int) $row['id'], 'Delete this user account') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render_list_footer($page, $path, ['noun' => 'user']);
}

layout_end();