<?php
/**
 * The mobile shell.
 *
 * A phone has no room for a persistent navigation rail and no pointer to
 * hover with, so the same screens are dressed differently rather than
 * rebuilt: navigation moves to a fixed bar along the bottom, within thumb
 * reach, the list toolbar collapses to a search box, and the save action on a
 * form follows the reader down the page.
 *
 * The important property is what this file does *not* do. It holds no queries,
 * no validation and no permission checks. Every screen it wraps is the same
 * page, with the same session, the same authorisation and the same SQL as the
 * desktop version, because it is the same PHP file rendering through a
 * different shell. A record saved on a phone is the record the desktop
 * application shows, and there is no second implementation to fall out of step.
 *
 * The rule: this file may draw, but it must never decide. Anything that
 * decides whether a request is allowed lives in auth.php, and anything that
 * touches a row goes through the shared helpers.
 */

declare(strict_types=1);

require_once __DIR__ . '/list_query.php';

// =====================================================================
// Choosing the shell
// =====================================================================

/**
 * Should this request be answered with the mobile shell?
 *
 * The explicit `?view=mobile` and `?view=desktop` parameters always win, and
 * they stick: the choice is remembered in the session, so following a link
 * from one shell to the other does not bounce the reader back. The desktop
 * link on a phone is therefore a real mode change rather than a link that
 * happens to look right once.
 *
 * With nothing chosen, a narrow screen is the signal. A phone-width viewport
 * is a proxy for a phone, and the user agent is the fallback for a request
 * that cannot report one.
 */
function wants_mobile(): bool
{
    static $decided = null;

    if ($decided !== null) {
        return $decided;
    }

    $forced = strtolower(query_string('view'));
    $active = session_status() === PHP_SESSION_ACTIVE;

    if ($forced === 'mobile' || $forced === 'desktop') {
        if ($active) {
            $_SESSION['ums_view'] = $forced;
        }
    }

    $choice = $active ? ($_SESSION['ums_view'] ?? '') : '';

    if ($choice === 'mobile') {
        $decided = true;
    } elseif ($choice === 'desktop') {
        $decided = false;
    } else {
        $decided = is_mobile_device();
    }

    return $decided;
}

/** The width of the browser window, when the page has been able to report it. */
function client_viewport_width(): ?int
{
    $cookie = $_COOKIE['ums_viewport'] ?? '';

    if (is_string($cookie) && preg_match('/^(\d{2,5})$/', $cookie, $match) === 1) {
        return (int) $match[1];
    }

    return null;
}

/**
 * Does this look like a phone?
 *
 * Kept deliberately small: a measured width, or the common phone tokens. A
 * long list of user agent patterns ages badly, and the interface is designed
 * to work at any width, so this only decides which shell to start with.
 */
function is_mobile_device(): bool
{
    $width = client_viewport_width();

    if ($width !== null) {
        return $width <= 820;
    }

    $agent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    if ($agent === '') {
        return false;
    }

    if (strpos($agent, 'ipad') !== false || strpos($agent, 'tablet') !== false) {
        // A tablet has room for the full shell.
        return strpos($agent, 'ipad') !== false && strpos($agent, 'desktop') === false;
    }

    foreach (['iphone', 'ipod', 'android', 'windows phone', 'blackberry', 'opera mini', 'opera mobi'] as $token) {
        if (strpos($agent, $token) !== false) {
            return true;
        }
    }

    return false;
}

// =====================================================================
// Navigation
// =====================================================================

/**
 * The destinations in the bottom bar.
 *
 * Four items is what a thumb reaches without stretching: where the reader
 * started, the people they most often look up, the results they most often
 * record, and everything else. The fourth is a list rather than a bar, so it
 * can grow with the application without crowding out the three that matter.
 *
 * @return array<int,array{key:string,label:string,icon:string,href:string}>
 */
function mobile_sections(): array
{
    return [
        ['key' => 'dashboard', 'label' => 'Home',    'icon' => 'dashboard', 'href' => 'dashboard.php'],
        ['key' => 'students',  'label' => 'People',  'icon' => 'student',   'href' => 'students/index.php'],
        ['key' => 'results',   'label' => 'Results', 'icon' => 'grades',    'href' => 'grades/index.php'],
        ['key' => 'modules',   'label' => 'All',     'icon' => 'grid',      'href' => 'm/index.php'],
    ];
}

/**
 * Which bottom-bar item, if any, describes the screen being drawn.
 *
 * Most screens have no item of their own, and marking one anyway would be a
 * lie about where the reader is, so an unmatched key highlights nothing.
 */
function mobile_active_section(string $key): string
{
    $map = [
        'dashboard' => 'dashboard',
        'students'  => 'students',
        'grades'    => 'results',
        'modules'   => 'modules',
    ];

    return $map[$key] ?? '';
}

/**
 * Every module, for the phone's module index.
 *
 * The hrefs are the ordinary module screens: the phone version of a faculty
 * list is the faculty list. The permission is listed so the index can mark
 * what this role cannot change, which is a display choice only — each screen
 * still enforces its own.
 *
 * @return array<int,array{key:string,label:string,icon:string,href:string,write:string}>
 */
function mobile_modules(): array
{
    return [
        ['key' => 'faculties',     'label' => 'Faculties',    'icon' => 'faculty',      'href' => 'faculties/index.php',     'write' => 'faculties.manage'],
        ['key' => 'departments',   'label' => 'Departments',  'icon' => 'department',   'href' => 'departments/index.php',   'write' => 'departments.manage'],
        ['key' => 'lecturers',     'label' => 'Lecturers',    'icon' => 'lecturer',     'href' => 'lecturers/index.php',     'write' => 'lecturers.manage'],
        ['key' => 'students',      'label' => 'Students',     'icon' => 'student',      'href' => 'students/index.php',      'write' => 'students.manage'],
        ['key' => 'courses',       'label' => 'Courses',      'icon' => 'course',       'href' => 'courses/index.php',       'write' => 'courses.manage'],
        ['key' => 'enrollments',   'label' => 'Enrolments',   'icon' => 'enrollment',   'href' => 'enrollments/index.php',   'write' => 'enrollments.manage'],
        ['key' => 'grades',        'label' => 'Grades',       'icon' => 'grades',       'href' => 'grades/index.php',        'write' => 'grades.manage'],
        ['key' => 'announcements', 'label' => 'Notices',      'icon' => 'announcement', 'href' => 'announcements/index.php', 'write' => 'announcements.manage'],
        ['key' => 'users',         'label' => 'Accounts',     'icon' => 'shield',       'href' => 'users/index.php',         'write' => 'users.manage'],
    ];
}

// =====================================================================
// The shell
// =====================================================================

/**
 * Open a phone page.
 *
 * Mirrors layout_start() so a screen is written the same way in both shells:
 * it emits the document, the app bar and the flashes, and opens the content
 * region. Only the chrome around the page differs.
 */
function mobile_start(string $title, string $active = 'dashboard', array $options = []): void
{
    $section = mobile_active_section($active);

    $head = '<meta name="mobile-web-app-capable" content="yes">'
        . '<meta name="apple-mobile-web-app-capable" content="yes">'
        . '<meta name="apple-mobile-web-app-title" content="' . e(APP_NAME) . '">'
        . '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">'
        // The service worker lives at the application root rather than in
        // assets/, so the app needs to know its own base to register it.
        . '<meta name="app-base" content="' . e(BASE_URL) . '">'
        . '<link rel="manifest" href="' . e(url('manifest.webmanifest')) . '">';

    render_document_head($title, [
        // viewport-fit=cover is what gives the app bar and the bottom bar the
        // display cutout, so a control is never tucked under a rounded corner
        // or a home indicator.
        'viewport' => 'width=device-width, initial-scale=1, viewport-fit=cover',
        'bodyClass' => 'mobile',
        'mainId'    => 'mcontent',
        'styles'    => ['assets/css/mobile.css'],
        'head'      => $head,
    ]);

    $eyebrow = (string) ($options['eyebrow'] ?? APP_TAGLINE);
    ?>
    <header class="mbar">
        <span class="mbar__mark"><?= brand_mark(24) ?></span>

        <div class="mbar__titles">
            <p class="mbar__eyebrow"><?= e($eyebrow) ?></p>
            <h1 class="mbar__title"><?= e($title) ?></h1>
        </div>

        <button class="mbar__theme iconbtn iconbtn--ghost" type="button" data-theme-toggle
                aria-label="Switch between light and dark">
            <span class="iconbtn__sun"><?= icon('sun', 17) ?></span>
            <span class="iconbtn__moon"><?= icon('moon', 17) ?></span>
        </button>
    </header>

    <?php render_mobile_flashes(); ?>

    <main class="mcontent" id="mcontent" data-mobile-section="<?= e($section) ?>">
    <?php
}

/** Close a phone page: the bottom bar, the out-of-shell links and the scripts. */
function mobile_end(array $options = []): void
{
    $section = mobile_active_section((string) ($options['active'] ?? 'dashboard'));
    $scripts = (array) ($options['scripts'] ?? []);

    // The phone's own behaviour comes after whatever the page asked for, so a
    // page script can rely on the shell being in place.
    $scripts[] = 'assets/js/mobile.js';
    ?>
    </main>

    <nav class="mtabbar" aria-label="Main">
        <?php foreach (mobile_sections() as $item): ?>
            <a class="mtab<?= $item['key'] === $section ? ' is-active' : '' ?>"
               href="<?= e(url($item['href'])) ?>"
               <?= $item['key'] === $section ? ' aria-current="page"' : '' ?>>
                <span class="mtab__icon"><?= icon($item['icon'], 20) ?></span>
                <span class="mtab__label"><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <footer class="mfoot">
        <a class="mfoot__link" href="<?= e(current_script_path() . with_query(['view' => 'desktop'])) ?>">
            <?= icon('external-mark', 15) ?><span>Open the desktop version</span>
        </a>
        <a class="mfoot__link mfoot__link--danger" href="<?= e(url('auth/logout.php')) ?>">
            <?= icon('logout', 15) ?><span>Sign out</span>
        </a>
    </footer>

    <?php render_document_foot(['scripts' => $scripts]); ?>
    <?php
}

/**
 * Flash messages, drawn for a phone.
 *
 * The desktop version floats them over the content; here they sit under the
 * app bar, out of the way of the bottom bar, and are announced politely,
 * because a save on a phone often happens with the screen held rather than in
 * view.
 */
function render_mobile_flashes(): void
{
    $messages = flash_take();

    if ($messages === []) {
        return;
    }
    ?>
    <div class="mflashes" role="status" aria-live="polite">
        <?php foreach ($messages as $message): ?>
            <?php $tone = array_key_exists($message['type'], FLASH_ICONS) ? $message['type'] : 'info'; ?>
            <div class="mflash mflash--<?= e($tone) ?>" data-flash>
                <span class="mflash__icon"><?= icon(FLASH_ICONS[$tone], 15) ?></span>
                <p class="mflash__text"><?= e($message['message']) ?></p>
                <button class="mflash__close" type="button" data-flash-close aria-label="Dismiss">
                    <?= icon('x', 15) ?>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}
