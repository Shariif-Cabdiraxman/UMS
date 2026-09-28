<?php
/**
 * Announcements — a single notice, as a student would read it.
 *
 * The content is plain text that came out of a textarea; it is escaped and
 * line breaks are honoured, so nothing a user typed can execute.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$path = 'announcements/index.php';
$id   = query_int('id');

if ($id < 1) {
    not_found('No announcement was requested. Open an announcement from the list to read it.');
}

$announcement = db_row(
    'SELECT a.`id`, a.`title`, a.`content`, a.`status`, a.`published_at`,
            a.`created_at`, a.`updated_at`, a.`author_id`,
            u.`full_name` AS `author_name`, u.`role` AS `author_role`
       FROM `announcements` a
       LEFT JOIN `users` u ON u.`id` = a.`author_id`
      WHERE a.`id` = ?
      LIMIT 1',
    'i',
    [$id]
);

if ($announcement === null) {
    not_found('That announcement does not exist. It may have been deleted.');
}

$canManage = can('announcements.manage');

layout_start('Announcement', 'announcements');

render_page_head([
    'eyebrow'  => 'Communications',
    'title'    => $announcement['title'],
    'subtitle' => 'Announcement #' . $id,
    'meta'     => 'Created ' . e(format_datetime($announcement['created_at']))
        . ' &middot; last changed ' . e(format_datetime($announcement['updated_at'])),
    'actions'  => record_actions($path, $canManage ? 'announcements/form.php?id=' . $id : ''),
]);

render_readonly_notice('announcements.manage', 'announcement');

$isPublished = $announcement['status'] === 'published';
?>

<article class="card mb-3">
    <div class="card__body">
        <p style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
            <?= badge($announcement['status']) ?>
            <?php if ($isPublished): ?>
                <span class="text-sm text-3">
                    Published <?= e(format_datetime($announcement['published_at'])) ?>
                </span>
            <?php endif; ?>
            <?php if ($announcement['author_id'] !== null): ?>
                <span class="text-sm text-3">
                    By <?= e($announcement['author_name']) ?>
                    <?= $announcement['author_role'] === 'admin' ? '· Administrator' : '' ?>
                </span>
            <?php endif; ?>
        </p>

        <h1 class="card__title" style="font-size:1.45rem;line-height:1.35;margin-bottom:1rem"><?= e($announcement['title']) ?></h1>

        <div class="prose">
            <?= nl2br(e((string) $announcement['content'])) ?>
        </div>
    </div>
</article>

<?php if ($canManage): ?>
    <p class="text-sm text-3">
        Editing this announcement keeps the original author and first-published date.
        <a class="table__name" href="<?= e(url('announcements/form.php?id=' . $id)) ?>">Edit announcement</a>
    </p>
<?php endif; ?>

<?php layout_end(); ?>