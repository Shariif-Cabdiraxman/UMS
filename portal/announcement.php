<?php
/**
 * Student portal — one published notice.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/portal.php';

require_student();

$id = query_int('id');

if ($id < 1) {
    not_found('No notice was requested. Open a notice from the notice board.');
}

$notice = student_notice($id);

if ($notice === null) {
    not_found('That notice is not available. It may have been removed or unpublished.');
}

layout_start('Notice', 'announcements');

render_page_head([
    'eyebrow'  => 'Notice board',
    'title'    => $notice['title'],
    'subtitle' => 'Published ' . format_datetime($notice['published_at'])
        . ($notice['author_name'] !== null ? ' by ' . $notice['author_name'] : ''),
    'actions'  => '<a class="btn btn--quiet" href="' . e(url('portal/announcements.php')) . '">'
        . icon('arrow-left', 15) . '<span>Back to notices</span></a>',
]);
?>

<article class="card mb-3">
    <div class="card__body">
        <p style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
            <?= badge('published') ?>
            <span class="text-sm text-3">Published <?= e(format_datetime($notice['published_at'])) ?></span>
        </p>

        <h1 class="card__title" style="font-size:1.45rem;line-height:1.35;margin-bottom:1rem"><?= e($notice['title']) ?></h1>

        <div class="prose">
            <?= nl2br(e((string) $notice['content'])) ?>
        </div>
    </div>
</article>

<?php layout_end(); ?>
