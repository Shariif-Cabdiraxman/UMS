<?php
/**
 * The shell.
 *
 * A page is written as:
 *
 *     require_once __DIR__ . '/includes/init.php';
 *     require_login();
 *     require_permission('students.manage');
 *
 *     layout_start('Students', 'students');
 *     render_page_head([...]);
 *     ... page markup ...
 *     layout_end();
 *
 * layout_start() emits everything up to and including the opening <main>, so
 * the page only has to produce its own content. layout_end() closes it and
 * adds the footer and scripts.
 */

declare(strict_types=1);

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/footer.php';

/**
 * Open the page.
 *
 * @param string $title  Browser title suffix, e.g. 'Students'.
 * @param string $active Navigation key so the right link is marked current.
 * @param array  $opts   scripts, bodyClass, description
 */
function layout_start(string $title, string $active = 'dashboard', array $opts = []): void
{
    render_document_head($title, $opts);
    render_sidebar($active);
    render_topbar($active);
    render_flashes();

    // Any validation errors and re-populated values left by the previous
    // request are consumed the first time the page reads them, so there is
    // nothing to clear here.
    ?>
    <main class="content" id="content">
    <?php
}

/** Close the page. */
function layout_end(array $opts = []): void
{
    ?>
    </main>
    <?php
    render_footer();
    render_document_foot($opts);
}
