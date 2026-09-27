<?php
/**
 * The bar across the top of the shell.
 *
 * Carries only what belongs here: a way back into the navigation on small
 * screens, where you are, the theme control, and the account menu. Search and
 * filters live with the list they apply to, not here, because a global search
 * across eight different schemas would have to be a separate feature.
 */

declare(strict_types=1);

/** The human name of the current section, used in the bar and on the page. */
function current_section_label(string $active): string
{
    foreach (navigation_groups() as $group) {
        foreach ($group['items'] as $item) {
            if ($item['key'] === $active) {
                return $item['label'];
            }
        }
    }

    return 'Dashboard';
}

function render_topbar(string $active = ''): void
{
    $user    = current_user();
    $section = current_section_label($active);
    ?>
    <header class="topbar">
        <button class="topbar__menu" type="button" data-sidebar-open aria-label="Open navigation" aria-controls="sidebar">
            <?= icon('menu', 19) ?>
        </button>

        <p class="topbar__section"><?= e($section) ?></p>

        <div class="topbar__spacer"></div>

        <button class="iconbtn iconbtn--ghost" type="button" data-theme-toggle
                aria-label="Switch between light and dark" title="Switch between light and dark">
            <span class="iconbtn__sun"><?= icon('sun', 17) ?></span>
            <span class="iconbtn__moon"><?= icon('moon', 17) ?></span>
        </button>

        <details class="usermenu">
            <summary class="usermenu__trigger">
                <?= render_avatar(
                    mb_substr((string) ($user['full_name'] ?? ''), 0, 1),
                    mb_substr(explode(' ', (string) ($user['full_name'] ?? ''))[1] ?? '', 0, 1)
                ) ?>
                <span class="usermenu__name"><?= e($user['full_name'] ?? '') ?></span>
                <?= icon('chevron-down', 14) ?>
            </summary>
            <div class="usermenu__panel">
                <div class="usermenu__identity">
                    <p class="usermenu__full"><?= e($user['full_name'] ?? '') ?></p>
                    <p class="usermenu__mail"><?= e($user['email'] ?? '') ?></p>
                    <p class="usermenu__badge"><?= badge(humanize($user['role'] ?? '')) ?></p>
                </div>
                <a class="usermenu__item" href="<?= e(url('dashboard.php')) ?>">
                    <?= icon('dashboard', 15) ?><span>Dashboard</span>
                </a>
                <?php if (can('users.manage')): ?>
                    <a class="usermenu__item" href="<?= e(url('users/index.php')) ?>">
                        <?= icon('shield', 15) ?><span>User accounts</span>
                    </a>
                <?php endif; ?>
                <a class="usermenu__item usermenu__item--danger" href="<?= e(url('auth/logout.php')) ?>">
                    <?= icon('logout', 15) ?><span>Sign out</span>
                </a>
            </div>
        </details>
    </header>
    <?php
}
