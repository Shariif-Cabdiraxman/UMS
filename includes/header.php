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
 * @param array{description?:string,bodyClass?:string,scripts?:array<int,string>} $options
 */
function render_document_head(string $title, array $options = []): void
{
    $fullTitle = $title . ' · ' . APP_NAME;
    $bodyClass = $options['bodyClass'] ?? '';
    ?>
    <!doctype html>
    <html lang="en" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="<?= e($options['description'] ?? (APP_NAME . ' — ' . APP_TAGLINE)) ?>">
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#1b3a5c">
        <title><?= e($fullTitle) ?></title>

        <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
        <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">

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
    <a class="skiplink" href="#content">Skip to main content</a>
    <?php
}

/**
 * The head of the sign-in page, which has no sidebar or top bar.
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
        <meta name="theme-color" content="#1b3a5c">
        <meta name="robots" content="noindex">
        <title><?= e($fullTitle) ?></title>
        <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
        <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
        <script src="<?= e(url('assets/js/theme.js')) ?>"></script>
    </head>
    <body class="app app--bare">
    <?php
}

/** Close the document. */
function render_document_foot(array $options = []): void
{
    $scripts = $options['scripts'] ?? ['assets/js/app.js'];
    ?>
        <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
    <?php foreach ($scripts as $script): ?>
        <?php if ($script !== 'assets/js/app.js'): ?>
            <script src="<?= e(url($script)) ?>" defer></script>
        <?php endif; ?>
    <?php endforeach; ?>
    </body>
    </html>
    <?php
}
