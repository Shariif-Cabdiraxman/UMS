<?php
/**
 * The phone's module index — "All".
 *
 * The bottom bar can only hold four destinations, and the application has nine
 * modules. This is the rest of them: the phone's answer to the desktop rail.
 *
 * It is a list of links and nothing more. Every destination is the ordinary
 * module screen, so opening a faculty from here arrives at the same faculty
 * list a desktop browser would, with the same permissions applied.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/mobile.php';

require_login();

// This screen belongs to the phone shell: it is a full page of destinations in
// place of the bottom bar. A desktop visitor who types the address is sent to
// the dashboard, where the same nine modules are in the sidebar.
if (!wants_mobile()) {
    redirect('dashboard.php');
}

$user = current_user();

// Anything this role may not change is marked rather than hidden: knowing a
// section exists and is read-only is more useful than a gap in the list.
$modules = mobile_modules();
$readOnly = [];

foreach ($modules as $module) {
    if (!can($module['write'])) {
        $readOnly[] = $module['label'];
    }
}

// The three things a registrar reaches for most often, offered first because
// on a phone the list is a scroll rather than a glance.
$quickLinks = [];

foreach ($modules as $module) {
    if (in_array($module['key'], ['students', 'grades', 'enrollments'], true)) {
        $quickLinks[] = $module;
    }
}

$firstName = explode(' ', (string) ($user['full_name'] ?? ''))[0];

layout_start('Everything', 'modules', [
    'eyebrow' => humanize((string) ($user['role'] ?? '')),
]);

if ($firstName !== ''): ?>
    <section class="mhero">
        <h2 class="mhero__title">Everything, <?= e($firstName) ?>.</h2>
        <p class="mhero__text">
            <?= e((string) count($modules)) ?> sections of the university record. Pick one to work in it.
        </p>
    </section>
<?php endif; ?>

<?php if ($quickLinks !== []): ?>
    <section class="msection" aria-labelledby="mquick">
        <div class="msection__head">
            <h2 class="msection__title" id="mquick">Most used</h2>
        </div>
        <div class="mhero__actions">
            <?php foreach ($quickLinks as $module): ?>
                <a class="mquick" href="<?= e(url($module['href'])) ?>">
                    <?= icon($module['icon'], 17) ?><span><?= e($module['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($readOnly !== []): ?>
    <p class="mnote">
        <?= icon('lock', 14) ?>
        <span>Read-only for a <?= e(humanize((string) ($user['role'] ?? ''))) ?>:
            <?= e(implode(', ', $readOnly)) ?>.</span>
    </p>
<?php endif; ?>

<section class="msection" aria-labelledby="mmodules">
    <div class="msection__head">
        <h2 class="msection__title" id="mmodules">All sections</h2>
    </div>

    <div class="mgrid">
        <?php foreach ($modules as $module): ?>
            <a class="mgriditem<?= can($module['write']) ? '' : ' is-locked' ?>"
               href="<?= e(url($module['href'])) ?>">
                <span class="mgriditem__icon"><?= icon($module['icon'], 19) ?></span>
                <span class="mgriditem__label"><?= e($module['label']) ?></span>
                <?php if (!can($module['write'])): ?>
                    <span class="mgriditem__lock" aria-label="Read-only"><?= icon('lock', 13) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php layout_end(); ?>
