<?php
/**
 * The bar across the top of the shell.
 *
 * Carries only what belongs here: a way back into the navigation on small
 * screens, where you are, the shared search, notices, theme control, and
 * account menu. Module-specific filters remain with their lists.
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
    $isDashboard = $active === 'dashboard';

    // Keep the notification preview resilient if announcements are unavailable.
    $notices = [];
    try {
        $notices = db_all(
            'SELECT `id`, `title`, `published_at`
               FROM `announcements`
              WHERE `status` = \'published\'
              ORDER BY `published_at` DESC
              LIMIT 3'
        );
    } catch (Throwable $exception) {
        $notices = [];
    }
    ?>
    <header class="topbar">
        <button class="topbar__menu" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false" aria-controls="sidebar" title="Toggle navigation">
            <?= icon('menu', 19) ?>
        </button>

        <div class="topbar__heading">
            <?php if (!$isDashboard): ?>
                <a class="topbar__back" href="<?= e(url('dashboard.php')) ?>" aria-label="Back to dashboard">
                    <?= icon('arrow-left', 18) ?>
                </a>
            <?php endif; ?>
            <div class="topbar__breadcrumbs" aria-label="Breadcrumb">
                <span class="topbar__section"><?= e($section) ?></span>
                <span class="topbar__sub">Registrar workspace</span>
            </div>
        </div>

        <form class="topbar__search" method="get" action="<?= e(url('search.php')) ?>" role="search">
            <?= icon('search', 16) ?>
            <input type="search" name="q" value="<?= e(query_string('q')) ?>"
                   placeholder="Search anything…" aria-label="Search students, staff, courses and notices"
                   autocomplete="off" spellcheck="false" enterkeyhint="search">
            <button class="searchclear" type="button" data-search-clear aria-label="Clear search" title="Clear search">
                <?= icon('x', 13) ?>
            </button>
            <kbd class="searchkbd" aria-hidden="true">Ctrl K</kbd>
        </form>

        <div class="topbar__actions">
            <details class="notifmenu">
                <summary class="topbar__bell" aria-label="Notifications">
                    <?= icon('bell', 16) ?>
                    <?php if ($notices !== []): ?><span class="topbar__dot" aria-hidden="true"></span><?php endif; ?>
                </summary>
                <div class="notifmenu__panel">
                    <div class="notifmenu__head">
                        <p class="notifmenu__title">Notice board</p>
                        <a class="notifmenu__link" href="<?= e(url('announcements/index.php')) ?>">View all</a>
                    </div>
                    <?php if ($notices === []): ?>
                        <p class="notifmenu__meta" style="padding:14px 10px">No published notices yet.</p>
                    <?php else: ?>
                        <?php foreach ($notices as $notice): ?>
                            <a class="notifmenu__item" href="<?= e(url('announcements/view.php?id=' . (int) $notice['id'])) ?>">
                                <span class="notifmenu__icon"><?= icon('announcement', 14) ?></span>
                                <span class="notifmenu__text">
                                    <span class="notifmenu__name"><?= e($notice['title']) ?></span>
                                    <span class="notifmenu__meta"><?= e(time_ago($notice['published_at'])) ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </details>

            <button class="iconbtn iconbtn--ghost" type="button" data-theme-toggle
                    aria-label="Switch between light and dark" title="Switch between light and dark">
                <span class="iconbtn__sun"><?= icon('sun', 17) ?></span>
                <span class="iconbtn__moon"><?= icon('moon', 17) ?></span>
            </button>

            <details class="usermenu">
                <summary class="usermenu__trigger">
                    <span class="usermenu__avatar-wrap">
                        <?= render_avatar(
                            (string) ($user['full_name'] ?? ''),
                            ''
                        ) ?>
                    </span>
                    <span class="usermenu__meta">
                        <span class="usermenu__name"><?= e($user['full_name'] ?? '') ?></span>
                        <span class="usermenu__sub"><?= e(humanize($user['role'] ?? 'Staff')) ?></span>
                    </span>
                    <span class="usermenu__chevron"><?= icon('chevron-down', 13) ?></span>
                </summary>
                <div class="usermenu__panel">
                    <div class="usermenu__identity">
                        <p class="usermenu__full"><?= e($user['full_name'] ?? '') ?></p>
                        <p class="usermenu__mail"><?= e($user['email'] ?? '') ?></p>
                        <div class="usermenu__badge"><?= badge(humanize($user['role'] ?? '')) ?></div>
                    </div>
                    <div class="usermenu__menu">
                        <a class="usermenu__item" href="<?= e(url('dashboard.php')) ?>">
                            <?= icon('dashboard', 15) ?><span>Dashboard</span>
                        </a>
                        <a class="usermenu__item" href="<?= e(url('account.php')) ?>">
                            <?= icon('settings', 15) ?><span>Account settings</span>
                        </a>
                        <a class="usermenu__item" href="<?= e(url('students/index.php')) ?>">
                            <?= icon('student', 15) ?><span>Student Records</span>
                        </a>
                        <a class="usermenu__item" href="<?= e(url('courses/index.php')) ?>">
                            <?= icon('course', 15) ?><span>Course Catalog</span>
                        </a>
                        <?php if (can('users.manage')): ?>
                            <a class="usermenu__item" href="<?= e(url('users/index.php')) ?>">
                                <?= icon('shield', 15) ?><span>User Accounts</span>
                            </a>
                        <?php endif; ?>
                        <div class="usermenu__divider"></div>
                        <a class="usermenu__item usermenu__item--danger" href="<?= e(url('auth/logout.php')) ?>">
                            <?= icon('logout', 15) ?><span>Sign out</span>
                        </a>
                    </div>
                </div>
            </details>
        </div>
    </header>
    <?php
}
