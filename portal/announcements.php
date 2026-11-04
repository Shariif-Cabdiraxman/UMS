<?php
/**
 * Student portal — published notices.
 *
 * Draft and archived announcements are never listed here.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/portal.php';

$student = require_student();
$notices = student_notices();

layout_start('Notices', 'announcements');

render_page_head([
    'eyebrow'  => 'Communication',
    'title'    => 'Notice board',
    'subtitle' => 'Announcements published by the registrar&rsquo;s office.',
    'meta'     => e(format_number(count($notices))) . ' published '
        . (count($notices) === 1 ? 'notice' : 'notices'),
]);
?>

<div class="stackgap">
    <?php if ($notices === []): ?>
        <section class="panel">
            <div class="panel__body">
                <?php render_empty_state('announcement', 'No notices yet', 'Published announcements appear here.'); ?>
            </div>
        </section>
    <?php else: ?>
        <section class="panel">
            <div class="panel__body panel__body--flush">
                <ul class="relatedlist">
                    <?php foreach ($notices as $notice): ?>
                        <li class="relatedlist__item">
                            <a class="relatedlist__link" href="<?= e(url('portal/announcement.php?id=' . (int) $notice['id'])) ?>">
                                <span class="relatedlist__label"><?= e($notice['title']) ?></span>
                                <span class="relatedlist__meta"><?= e(truncate((string) $notice['content'], 120)) ?></span>
                            </a>
                            <span class="relatedlist__trail"><?= e(format_db_date($notice['published_at'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php layout_end(); ?>
