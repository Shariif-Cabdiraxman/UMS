<?php
/**
 * The left navigation rail.
 *
 * The groups follow the shape of the institution rather than the shape of the
 * database: who is here, what the structure is, what is being taught, and
 * what has been announced. Administrators also get a section for user
 * accounts, which registrars never see.
 */

declare(strict_types=1);

/**
 * The navigation tree, filtered for the signed-in role.
 *
 * Each item that can be written to carries the permission that governs it, so
 * the read-only marker is derived from the same source of truth as the
 * server-side check rather than from a hard-coded list of roles.
 *
 * @return array<int,array{label:string,items:array<int,array<string,mixed>>}>
 */
function navigation_groups(): array
{
    $groups = [
        [
            'label' => 'Overview',
            'items' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => 'dashboard.php'],
            ],
        ],
        [
            'label' => 'People',
            'items' => [
                ['key' => 'students', 'label' => 'Students', 'icon' => 'student', 'href' => 'students/index.php', 'count' => 'students', 'manage' => 'students.manage'],
                ['key' => 'lecturers', 'label' => 'Lecturers', 'icon' => 'lecturer', 'href' => 'lecturers/index.php', 'count' => 'lecturers', 'manage' => 'lecturers.manage'],
            ],
        ],
        [
            'label' => 'Structure',
            'items' => [
                ['key' => 'faculties', 'label' => 'Faculties', 'icon' => 'faculty', 'href' => 'faculties/index.php', 'count' => 'faculties', 'manage' => 'faculties.manage'],
                ['key' => 'departments', 'label' => 'Departments', 'icon' => 'department', 'href' => 'departments/index.php', 'count' => 'departments', 'manage' => 'departments.manage'],
            ],
        ],
        [
            'label' => 'Academic',
            'items' => [
                ['key' => 'courses', 'label' => 'Courses', 'icon' => 'course', 'href' => 'courses/index.php', 'count' => 'courses', 'manage' => 'courses.manage'],
                ['key' => 'enrollments', 'label' => 'Enrollments', 'icon' => 'enrollment', 'href' => 'enrollments/index.php', 'count' => 'enrollments', 'manage' => 'enrollments.manage'],
                ['key' => 'grades', 'label' => 'Grades', 'icon' => 'grades', 'href' => 'grades/index.php', 'manage' => 'grades.manage'],
            ],
        ],
        [
            'label' => 'Communication',
            'items' => [
                ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'announcement', 'href' => 'announcements/index.php', 'count' => 'announcements', 'manage' => 'announcements.manage'],
            ],
        ],
    ];

    if (can('users.manage')) {
        $groups[] = [
            'label' => 'Administration',
            'items' => [
                ['key' => 'users', 'label' => 'User accounts', 'icon' => 'shield', 'href' => 'users/index.php', 'count' => 'users', 'manage' => 'users.manage'],
            ],
        ];
    }

    return $groups;
}

/**
 * Live counts for the navigation, in a single query.
 *
 * Counted on every page load so the numbers are never stale, but as one
 * grouped query rather than one per module.
 *
 * @return array<string,int>
 */
function navigation_counts(): array
{
    $counts = [
        'students' => 0, 'lecturers' => 0, 'faculties' => 0, 'departments' => 0,
        'courses' => 0, 'enrollments' => 0, 'announcements' => 0, 'users' => 0,
    ];

    try {
        $row = db_row(
            'SELECT
                (SELECT COUNT(*) FROM `students`)                      AS `students`,
                (SELECT COUNT(*) FROM `lecturers`)                      AS `lecturers`,
                (SELECT COUNT(*) FROM `faculties`)                      AS `faculties`,
                (SELECT COUNT(*) FROM `departments`)                    AS `departments`,
                (SELECT COUNT(*) FROM `courses`)                        AS `courses`,
                (SELECT COUNT(*) FROM `enrollments`)                    AS `enrollments`,
                (SELECT COUNT(*) FROM `announcements` WHERE `status` = \'published\') AS `announcements`,
                (SELECT COUNT(*) FROM `users` WHERE `status` = \'active\')           AS `users`'
        );
    } catch (Throwable $exception) {
        // A count is never worth breaking a page for.
        return $counts;
    }

    if ($row !== null) {
        foreach ($counts as $key => $unused) {
            $counts[$key] = (int) ($row[$key] ?? 0);
        }
    }

    return $counts;
}

/**
 * @param string $active Key of the current module, used to mark the link.
 */
function render_sidebar(string $active = ''): void
{
    $counts = navigation_counts();
    $user   = current_user();
    ?>
    <div class="shell">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar__brand">
                <a class="brand" href="<?= e(url('dashboard.php')) ?>">
                    <span class="brand__mark"><?= brand_mark(30) ?></span>
                    <span class="brand__text">
                        <span class="brand__name"><?= e(APP_SHORT) ?></span>
                        <span class="brand__sub"><?= e(APP_TAGLINE) ?></span>
                    </span>
                </a>
                <button class="sidebar__close" type="button" data-sidebar-close aria-label="Close navigation">
                    <?= icon('x', 18) ?>
                </button>
            </div>

            <nav class="nav" aria-label="Main">
                <?php foreach (navigation_groups() as $group): ?>
                    <div class="nav__group">
                        <p class="nav__grouplabel"><?= e($group['label']) ?></p>
                        <ul class="nav__list">
                            <?php foreach ($group['items'] as $item): ?>
                                <?php
                                $isActive = $item['key'] === $active;
                                $count    = isset($item['count']) ? ($counts[$item['count']] ?? null) : null;
                                $locked   = isset($item['manage']) && !can($item['manage']);
                                ?>
                                <li>
                                    <a class="nav__link<?= $isActive ? ' is-active' : '' ?>"
                                       href="<?= e(url($item['href'])) ?>"
                                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                                        <span class="nav__icon"><?= icon($item['icon'], 17) ?></span>
                                        <span class="nav__label"><?= e($item['label']) ?></span>
                                        <?php if ($count !== null): ?>
                                            <span class="nav__count"><?= e((string) $count) ?></span>
                                        <?php endif; ?>
                                        <?php if ($locked): ?>
                                            <span class="nav__lock" title="Read-only for your role"><?= icon('lock', 12) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar__foot">
                <div class="signedin">
                    <?= render_avatar(
                        mb_substr((string) ($user['full_name'] ?? ''), 0, 1),
                        mb_substr(explode(' ', (string) ($user['full_name'] ?? ''))[1] ?? '', 0, 1)
                    ) ?>
                    <div class="signedin__text">
                        <p class="signedin__name"><?= e($user['full_name'] ?? '') ?></p>
                        <p class="signedin__role"><?= e(humanize($user['role'] ?? '')) ?></p>
                    </div>
                </div>
                <a class="sidebar__logout" href="<?= e(url('auth/logout.php')) ?>">
                    <?= icon('logout', 15) ?><span>Sign out</span>
                </a>
            </div>
        </aside>

        <div class="scrim" data-sidebar-close hidden></div>
    <?php
}
