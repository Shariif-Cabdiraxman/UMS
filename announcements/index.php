<?php
/**
 * Announcements — the list.
 *
 * Notices about registration windows, deadlines and office hours. The author
 * is the signed-in user who wrote it; the published time is set the moment a
 * draft becomes live. Archived notices stay in the ledger so the history of
 * what was communicated is never lost.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'announcements/index.php';

// ---------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('announcements.manage');

    if (input('action') === 'delete') {
        delete_record('announcements', (int) input_int('delete_id', 0), 'Announcement', $path);
    }
}

// ---------------------------------------------------------------------
// List state
// ---------------------------------------------------------------------

$search = query_string('q');
$status = query_string('status');

$sort = sort_state([
    'title'       => 'title',
    'status'      => 'status',
    'author'      => 'author_name',
    'published'   => 'published_at',
    'created'     => 'created_at',
], 'created');

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(a.`title` LIKE ? OR a.`content` LIKE ?)';
    $like    = like_param($search);
    array_push($params, $like, $like);
    $types .= 'ss';
}

if ($status !== '') {
    $where[]  = 'a.`status` = ?';
    $params[] = $status;
    $types   .= 's';
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$total = (int) db_value(
    'SELECT COUNT(*)
       FROM `announcements` a
       LEFT JOIN `users` u ON u.`id` = a.`author_id`
       ' . $whereSql,
    $types,
    $params
);

$page  = paginate($total, current_page_number(), current_page_size());
$types .= 'ii';

$rows = db_all(
    'SELECT a.`id`, a.`title`, a.`status`, a.`published_at`, a.`created_at`, a.`updated_at`,
            u.`id` AS `author_id`, u.`full_name` AS `author_name`
       FROM `announcements` a
       LEFT JOIN `users` u ON u.`id` = a.`author_id`
       ' . $whereSql . '
      ORDER BY ' . $sort['sql'] . '
      LIMIT ? OFFSET ?',
    $types,
    array_merge($params, [$page['per_page'], $page['offset']])
);

$canManage = can('announcements.manage');
$statuses = ['' => 'All statuses'] + announcement_status_options();

layout_start('Announcements', 'announcements');

render_page_head([
    'eyebrow'  => 'Communications',
    'title'    => 'Announcements',
    'subtitle' => 'Notices shown on the dashboard and the notices board.',
    'meta'     => e(format_number($total)) . ' ' . ($total === 1 ? 'announcement' : 'announcements') . ' issued',
    'actions'  => $canManage
        ? '<a class="btn btn--primary" href="' . e(url('announcements/form.php')) . '">' . icon('plus', 16) . '<span>New announcement</span></a>'
        : '',
]);

render_readonly_notice('announcements.manage', 'announcement');

render_list_toolbar([
    'action'      => $path,
    'placeholder' => 'Search titles and body text…',
    'filters'     => filter_select('status', 'Filter by status', $statuses, $status, 'All statuses'),
]);

if ($rows === []) {
    echo '<div class="card">';
    render_empty_state(
        'announcements',
        ($search === '' && $status === '')
            ? 'No announcements yet'
            : 'No announcements match those filters',
        ($search === '' && $status === '')
            ? 'Publish a notice about a deadline or a registration window when one comes up.'
            : 'Try a different word, or clear the filters to see every announcement.',
        ($canManage && $search === '' && $status === '')
            ? '<a class="btn btn--primary" href="' . e(url('announcements/form.php')) . '">' . icon('plus', 16) . '<span>Write the first announcement</span></a>'
            : ''
    );
    echo '</div>';
} else {
    ?>
    <div class="stackgap">
        <?php foreach ($rows as $row): ?>
            <article class="card">
                <div class="card__body">
                    <p style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:.6rem">
                        <?= badge($row['status']) ?>
                        <span class="text-sm text-3">
                            <?php if ($row['status'] === 'published'): ?>
                                Published <?= e(format_datetime($row['published_at'])) ?>
                            <?php elseif ($row['status'] === 'archived'): ?>
                                Archived
                            <?php elseif ($row['published_at'] !== null): ?>
                                <?= icon('edit', 13) ?> Draft, first published <?= e(format_date($row['published_at'])) ?>
                            <?php else: ?>
                                Draft since <?= e(format_date($row['created_at'])) ?>
                            <?php endif; ?>
                            <?php if ($row['author_id'] !== null): ?>
                                &middot; <?= icon('shield', 13) ?> <?= e($row['author_name']) ?>
                            <?php endif; ?>
                        </span>
                    </p>
                    <h3 class="card__title">
                        <a href="<?= e(url('announcements/view.php?id=' . (int) $row['id'])) ?>"><?= e($row['title']) ?></a>
                    </h3>
                    <div class="formactions" style="margin-top:.9rem">
                        <a class="btn btn--quiet btn--sm" href="<?= e(url('announcements/view.php?id=' . (int) $row['id'])) ?>">Read</a>
                        <?php if ($canManage): ?>
                            <a class="btn btn--quiet btn--sm" href="<?= e(url('announcements/form.php?id=' . (int) $row['id'])) ?>">Edit</a>
                            <?= render_delete_form($path, (int) $row['id'], 'Delete this announcement') ?>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
    render_list_footer($page, $path, ['noun' => 'announcement']);
}

layout_end();