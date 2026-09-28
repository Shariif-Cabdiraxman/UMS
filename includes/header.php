<?php
/**
 * The <head> of every signed-in page.
 */

declare(strict_types=1);

/**
 * The institutional mark: a shield holding an open book.
 *
 * Drawn inline rather than loaded as an image so it always matches the text
 * colour beside it, in both themes, with no extra request.
 */
function brand_mark(int $size = 28, string $class = ''): string
{
    return '<svg class="brandmark' . ($class !== '' ? ' ' . e($class) : '') . '" width="' . $size . '" height="' . $size
        . '" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.5"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<path d="M16 2.6 4.2 6.6v8.9c0 7.3 4.9 12.4 11.8 14 6.9-1.6 11.8-6.7 11.8-14V6.6L16 2.6Z"/>'
        . '<path d="M16 10.6c-1.4-1.2-3.1-1.7-5.4-1.7H8.7v9.4h1.5c2.3 0 4 .5 5.4 1.6 1.4-1.1 3.1-1.6 5.4-1.6h1.5V8.9h-1.9c-2.3 0-4 .5-5.6 1.7Z"/>'
        . '<path d="M16 10.6v9.3"/>'
        . '</svg>';
}

/**
 * One head renderer for every signed-in page, whatever the shell.
 *
 * The mobile shell asks for the same document as the desktop one and adds to
 * it: a second stylesheet, the viewport that allows the display cutout, the
 * installable-app links, and a different id for its main region. Keeping that
 * here rather than in a second copy of the head means the two shells cannot
 * drift apart on the things that matter, such as the theme script that has to
 * run before the first paint.
 *
 * @param array{
 *     description?:string,
 *     bodyClass?:string,
 *     scripts?:array<int,string>,
 *     styles?:array<int,string>,
 *     viewport?:string,
 *     mainId?:string,
 *     head?:string
 * } $options
 */
function render_document_head(string $title, array $options = []): void
{
    $fullTitle = $title . ' · ' . APP_NAME;
    $bodyClass = $options['bodyClass'] ?? '';
    $mainId = (string) ($options['mainId'] ?? 'content');
    ?>
    <!doctype html>
    <html lang="en" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="<?= e((string) ($options['viewport'] ?? 'width=device-width, initial-scale=1')) ?>">
        <meta name="description" content="<?= e($options['description'] ?? (APP_NAME . ' — ' . APP_TAGLINE)) ?>">
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#111111">
        <title><?= e($fullTitle) ?></title>

        <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
        <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
        <?php foreach ((array) ($options['styles'] ?? []) as $style): ?>
            <link rel="stylesheet" href="<?= e(url((string) $style)) ?>">
        <?php endforeach; ?>
        <?php if (!empty($options['head'])): ?>
            <?= $options['head'] ?>
        <?php endif; ?>

        <!--
            Blocking rather than deferred on purpose: this has to run before
            the first paint, otherwise a dark-mode user sees a white flash. It
            is the only script in the document not marked defer, which is why
            the Content-Security-Policy can stay at script-src 'self' with no
            inline exceptions.
        -->
        <script src="<?= e(url('assets/js/theme.js')) ?>"></script>
    </head>
    <body class="app <?= e($bodyClass) ?>">
    <a class="skiplink" href="#<?= e($mainId) ?>">Skip to main content</a>
    <?php
}

/**
 * The head of the sign-in page, which has no sidebar or top bar.
 *
 * @param array $options bodyClass, meta
 */
function render_standalone_head(string $title, array $options = []): void
{
    $fullTitle = $title === '' ? APP_NAME . ' — ' . APP_TAGLINE : $title . ' · ' . APP_NAME;
    ?>
    <!doctype html>
    <html lang="en" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#111111">
        <meta name="robots" content="noindex">
        <title><?= e($fullTitle) ?></title>
        <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
        <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
        <?php if (!empty($options['meta'])): ?>
            <meta name="description" content="<?= e((string) $options['meta']) ?>">
        <?php endif; ?>
        <script src="<?= e(url('assets/js/theme.js')) ?>"></script>
    </head>
    <body class="app app--bare<?= !empty($options['bodyClass']) ? ' ' . e((string) $options['bodyClass']) : '' ?>">
    <?php
}

/**
 * Close the document.
 *
 * @param array $options scripts — extra script paths, relative to the app root.
 */
function render_document_foot(array $options = []): void
{
    // app.js always loads; anything extra follows it.
    $scripts = $options['scripts'] ?? [];
    ?>
    <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
    <?php foreach ($scripts as $script): ?>
        <script src="<?= e(url((string) $script)) ?>" defer></script>
    <?php endforeach; ?>
    </body>
    </html>
    <?php
}
