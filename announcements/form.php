<?php
/**
 * Announcements — write and edit.
 *
 * One file for both. The author is fixed at creation to the signed-in user,
 * and stays fixed even if somebody else edits the notice, so the byline is
 * always whoever committed the text to the ledger first. The published time
 * is set once, at the moment a notice first becomes live.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path      = 'announcements/index.php';
$canManage = can('announcements.manage');

$id     = (int) query_int('id');
$isEdit = $id > 0;

$me = current_user();

$record = [
    'id'           => 0,
    'title'        => '',
    'content'      => '',
    'status'       => 'draft',
    'author_id'    => (int) $me['id'],
    'author_name'  => (string) $me['full_name'],
    'published_at' => null,
    'created_at'   => null,
];

if ($isEdit) {
    $found = db_row(
        'SELECT a.`id`, a.`title`, a.`content`, a.`status`, a.`author_id`, a.`published_at`,
                a.`created_at`, a.`updated_at`,
                u.`full_name` AS `author_name`
           FROM `announcements` a
           LEFT JOIN `users` u ON u.`id` = a.`author_id`
          WHERE a.`id` = ?
          LIMIT 1',
        'i',
        [$id]
    );

    if ($found === null) {
        not_found('That announcement does not exist. It may have been deleted.');
    }

    foreach ($record as $key => $unused) {
        if ($key === 'author_id' || $key === 'author_name') {
            continue;
        }
        if (array_key_exists($key, $found) && $found[$key] !== null) {
            $record[$key] = $found[$key];
        }
    }
    $record['author_id']   = (int) $found['author_id'];
    $record['author_name'] = (string) $found['author_name'];
}

// ---------------------------------------------------------------------
// Save
// ---------------------------------------------------------------------

if (is_post()) {
    guard_write('announcements.manage');

    $id     = (int) input_int('id', 0);
    $isEdit = $id > 0;
    $data   = collect_input();
    $data['id'] = $id;

    $errors = validate($data, [
        'title'   => 'required|string|max:180',
        'content' => 'required|string|max:10000',
        'status'  => 'required|in:' . implode(',', array_keys(announcement_status_options())),
    ], [
        'title'   => 'Title',
        'content' => 'Content',
        'status'  => 'Status',
    ]);

    if ($errors !== []) {
        redirect_back_with_errors('announcements/form.php' . ($id > 0 ? '?id=' . $id : ''), $errors, $data, 'form');
    }

    // A first publication stamps the time; later republishing from archived
    // keeps the original, so the notice board never lies about when a notice
    // went up.
    if (!$isEdit) {
        $authorId    = (int) $me['id'];
        $publishedAt = $data['status'] === 'published' ? date('Y-m-d H:i:s') : null;
    } else {
        $previous    = db_row('SELECT `status`, `author_id`, `published_at` FROM `announcements` WHERE `id` = ?', 'i', [$id]);
        $authorId    = (int) ($previous['author_id'] ?? $me['id']);
        $publishedAt = $previous !== null && $previous['published_at'] !== null
            ? (string) $previous['published_at']
            : ($data['status'] === 'published' ? date('Y-m-d H:i:s') : null);
    }

    if ($id > 0) {
        db_execute(
            'UPDATE `announcements`
                SET `title` = ?, `content` = ?, `status` = ?,
                    `published_at` = ?, `updated_at` = NOW()
              WHERE `id` = ?',
            'ssssi',
            [(string) $data['title'], (string) $data['content'], (string) $data['status'], $publishedAt, $id]
        );

        flash_set('success', 'Announcement updated.');
        redirect('announcements/view.php?id=' . $id);
    }

    $newId = db_insert(
        'INSERT INTO `announcements` (`title`, `content`, `status`, `author_id`, `published_at`)
              VALUES (?, ?, ?, ?, ?)',
        'sssis',
        [(string) $data['title'], (string) $data['content'], (string) $data['status'], $authorId, $publishedAt]
    );

    flash_set('success', 'Announcement saved.');
    redirect('announcements/view.php?id=' . $newId);
}

if (!$canManage) {
    require_permission('announcements.manage');
}

$statuses = announcement_status_options();

layout_start($isEdit ? 'Edit announcement' : 'New announcement', 'announcements');

render_page_head([
    'eyebrow'  => 'Communications',
    'title'    => $isEdit ? 'Edit announcement' : 'Write an announcement',
    'subtitle' => 'Notices appear on the dashboard and on the notices board.',
    'actions'  => '<a class="btn btn--quiet" href="' . e(url($path)) . '">' . icon('arrow-left', 15) . '<span>Back to list</span></a>',
]);

$cancelTo = $isEdit ? 'announcements/view.php?id=' . $id : $path;
?>

<form class="card" method="post" action="<?= e(url('announcements/form.php' . ($isEdit ? '?id=' . $id : ''))) ?>" novalidate data-once>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e((string) $id) ?>">
    <?php endif; ?>

    <div class="card__body">
        <div class="formgrid">
            <?= text_field('title', 'Title', [
                'value'       => (string) $record['title'],
                'required'    => true,
                'maxlength'   => '180',
                'placeholder' => 'e.g. 2026/2027 Semester 1 registration is now open',
                'wide'        => true,
            ]) ?>

            <?= select_field('status', 'Status', $statuses, [
                'value'    => (string) $record['status'],
                'required' => true,
                'hint'     => 'Published notices are seen by students; drafts and archives are kept for the record.',
            ]) ?>

            <?= textarea_field('content', 'Content', [
                'value'       => (string) $record['content'],
                'required'    => true,
                'rows'        => '10',
                'maxlength'   => '10000',
                'placeholder' => 'What students need to know, including dates, places and what to bring.',
                'wide'        => true,
            ]) ?>
        </div>
    </div>

    <div class="card__body" style="border-top:1px solid var(--border)">
        <div class="formactions">
            <button class="btn btn--primary" type="submit">
                <?= icon('check', 16) ?><span><?= $isEdit ? 'Save changes' : ($record['status'] === 'published' ? 'Publish announcement' : 'Save announcement') ?></span>
            </button>
            <a class="btn btn--quiet" href="<?= e(url($cancelTo)) ?>">Cancel</a>
            <span class="formactions__spacer"></span>
            <span class="text-3 text-xs">
                By <?= e($record['author_name']) ?>
                <?php if ($record['published_at'] !== null): ?>
                    &middot; first published <?= e(format_datetime($record['published_at'])) ?>
                <?php endif; ?>
                <?php if ($record['created_at'] !== null): ?>
                    &middot; created <?= e(format_date($record['created_at'])) ?>
                <?php endif; ?>
            </span>
        </div>
    </div>
</form>

<?php layout_end(); ?>