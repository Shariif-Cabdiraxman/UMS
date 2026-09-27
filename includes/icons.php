<?php
/**
 * Icon set.
 *
 * Drawn for this project as plain inline SVG on a shared 24-unit grid with a
 * single stroke weight, so every icon has the same optical weight at every
 * size. They inherit `currentColor`, which means an icon is always the same
 * colour as the text it sits next to and needs no theming.
 *
 * Icons are decorative: they are marked aria-hidden and the surrounding link
 * or button always carries its own label.
 */

declare(strict_types=1);

const ICONS = [
    // --- navigation -------------------------------------------------
    'dashboard' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="4.5" rx="1.5"/><rect x="13.5" y="11" width="7" height="9.5" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/>',
    'student' => '<circle cx="12" cy="8" r="3.6"/><path d="M4.8 20.2c0-3.9 3.2-6.3 7.2-6.3s7.2 2.4 7.2 6.3"/>',
    'faculty' => '<path d="M3 9.4 12 4l9 5.4"/><path d="M5.6 9.9v8.2M9.8 9.9v8.2M14.2 9.9v8.2M18.4 9.9v8.2"/><path d="M3.6 18.1h16.8M2.4 20.6h19.2"/>',
    'department' => '<rect x="9" y="3" width="6" height="4.4" rx="1.2"/><rect x="2.6" y="15" width="6" height="4.4" rx="1.2"/><rect x="15.4" y="15" width="6" height="4.4" rx="1.2"/><path d="M12 7.4v3.9M5.6 15v-3.7h12.8V15"/>',
    'lecturer' => '<circle cx="9.2" cy="8.4" r="3"/><path d="M3.6 19.4c0-3.3 2.5-5.3 5.6-5.3s5.6 2 5.6 5.3"/><path d="M15.4 6.1a3 3 0 0 1 0 5.7"/><path d="M16.4 14.4c2.4.7 4 2.4 4 5"/>',
    'course' => '<path d="M12 6.4C10.4 5.1 8.4 4.5 5.5 4.5H3.5v13.1h2c2.9 0 4.9.6 6.5 1.9 1.6-1.3 3.6-1.9 6.5-1.9h2V4.5h-2c-2.9 0-4.9.6-6.5 1.9Z"/><path d="M12 6.4v13.1"/>',
    'enrollment' => '<rect x="8.8" y="2.6" width="6.4" height="3.9" rx="1.3"/><path d="M15.9 4.3h1.6A2.5 2.5 0 0 1 20 6.8V19a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6.8a2.5 2.5 0 0 1 2.5-2.5h1.6"/><path d="m8.7 12.3 2.1 2.1 4.4-4.4"/>',
    'grades' => '<path d="M3.5 20.4h17"/><rect x="5" y="12.2" width="3.2" height="5.4" rx="1"/><rect x="10.4" y="8.2" width="3.2" height="9.4" rx="1"/><rect x="15.8" y="4.6" width="3.2" height="13" rx="1"/>',
    'announcement' => '<rect x="3.2" y="4.2" width="17.6" height="13.6" rx="1.8"/><path d="M7.2 8.6h6.5M7.2 12.2h9.5M7.2 15.2h4.5"/>',
    'logout' => '<path d="M14.5 7.6V5.2a1.7 1.7 0 0 0-1.7-1.7H5.7A1.7 1.7 0 0 0 4 5.2v13.6a1.7 1.7 0 0 0 1.7 1.7h7.1a1.7 1.7 0 0 0 1.7-1.7v-2.4"/><path d="M9.6 12H21"/><path d="m17.8 8.6 3.4 3.4-3.4 3.4"/>',

    // --- actions ----------------------------------------------------
    'search' => '<circle cx="10.8" cy="10.8" r="6.3"/><path d="m15.4 15.4 4.6 4.6"/>',
    'plus' => '<path d="M12 5.4v13.2M5.4 12h13.2"/>',
    'edit' => '<path d="M4.5 19.5h3.2L18.4 8.8a1.9 1.9 0 0 0 0-2.7l-.5-.5a1.9 1.9 0 0 0-2.7 0L4.5 16.3v3.2Z"/><path d="m14.3 7.1 2.6 2.6"/>',
    'trash' => '<path d="M4.8 6.8h14.4"/><path d="M9.5 6.8V5.2a1.5 1.5 0 0 1 1.5-1.5h2a1.5 1.5 0 0 1 1.5 1.5v1.6"/><path d="m6.6 6.8.8 12.2a1.6 1.6 0 0 0 1.6 1.5h6a1.6 1.6 0 0 0 1.6-1.5l.8-12.2"/><path d="M10.4 10.2v6M13.6 10.2v6"/>',
    'eye' => '<path d="M2.6 12S6.1 5.8 12 5.8 21.4 12 21.4 12 17.9 18.2 12 18.2 2.6 12 2.6 12Z"/><circle cx="12" cy="12" r="2.9"/>',
    'filter' => '<path d="M3.6 5.4h16.8L14 13.3v5.4l-4 2v-7.4L3.6 5.4Z"/>',
    'sort' => '<path d="M7 4.6v14.8M7 4.6 4.1 7.5M7 4.6l2.9 2.9"/><path d="M17 19.4V4.6M17 19.4l-2.9-2.9M17 19.4l2.9-2.9"/>',
    'refresh' => '<path d="M20 11.6a8 8 0 1 0-1.9 6.6"/><path d="M20.2 4.6v7h-7"/>',
    'download' => '<path d="M12 3.6v11.4"/><path d="m7.6 10.6 4.4 4.4 4.4-4.4"/><path d="M4 18.4v.6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-.6"/>',
    'print' => '<path d="M7 9V4.4h10V9"/><rect x="3.6" y="9" width="16.8" height="7.4" rx="2"/><path d="M7 14h10v6.6H7z"/>',
    'external' => '<path d="M13.4 4.6h6v6"/><path d="M19.4 4.6 11 13"/><path d="M18 14v4.5a1.8 1.8 0 0 1-1.8 1.8H5.3a1.8 1.8 0 0 1-1.8-1.8V7.8a1.8 1.8 0 0 1 1.8-1.8H9.8"/>',
    'copy' => '<rect x="8.4" y="8.4" width="11.2" height="11.2" rx="1.8"/><path d="M15.6 5.6V5a1.6 1.6 0 0 0-1.6-1.6H5.6A1.6 1.6 0 0 0 4 5v8.4a1.6 1.6 0 0 0 1.6 1.6h.6"/>',

    // --- direction --------------------------------------------------
    'chevron-left' => '<path d="m14.6 5.4-7 6.6 7 6.6"/>',
    'chevron-right' => '<path d="m9.4 5.4 7 6.6-7 6.6"/>',
    'chevron-down' => '<path d="m5.4 9 6.6 7 6.6-7"/>',
    'chevron-up' => '<path d="m5.4 15 6.6-7 6.6 7"/>',
    'arrow-left' => '<path d="M20 12H4.6M10.6 5.6 4 12l6.6 6.4"/>',
    'arrow-right' => '<path d="M4 12h15.4M13.4 5.6 20 12l-6.6 6.4"/>',
    'menu' => '<path d="M3.5 7h17M3.5 12h17M3.5 17h17"/>',
    'external-mark' => '<path d="M18 13.4v5.2a2 2 0 0 1-2 2H5.6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5.2"/>',

    // --- status and meta --------------------------------------------
    'check' => '<path d="m5 12.6 4.6 4.5L19 7.6"/>',
    'x' => '<path d="m6.2 6.2 11.6 11.6M17.8 6.2 6.2 17.8"/>',
    'alert' => '<path d="M10.6 3.9 2.5 18.2a1.6 1.6 0 0 0 1.4 2.4h16.2a1.6 1.6 0 0 0 1.4-2.4L13.4 3.9a1.6 1.6 0 0 0-2.8 0Z"/><path d="M12 9.2v4.4"/><circle cx="12" cy="17.1" r="1" fill="currentColor" stroke="none"/>',
    'info' => '<circle cx="12" cy="12" r="8.8"/><path d="M12 11.2v5.2"/><circle cx="12" cy="7.9" r="1" fill="currentColor" stroke="none"/>',
    'lock' => '<rect x="4.5" y="10.2" width="15" height="9.8" rx="2"/><path d="M8.2 10.2V7.6a3.8 3.8 0 0 1 7.6 0v2.6"/>',
    'shield' => '<path d="M12 3 5 5.8v5.4c0 4.5 2.9 7.9 7 9 4.1-1.1 7-4.5 7-9V5.8L12 3Z"/><path d="m9 12 2.2 2.2L15.3 10"/>',
    'clock' => '<circle cx="12" cy="12" r="8.8"/><path d="M12 7v5.3l3.4 2"/>',
    'trending' => '<path d="m3.6 16.6 5.4-5.4 3.5 3.5 7.9-7.9"/><path d="M15.4 6.8h5v5"/>',
    'inbox' => '<path d="M3.6 13.4h4.1l1.4 2.7h5.8l1.4-2.7h4.1"/><path d="M6 4.6h12l3 8.8v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4l3-8.8Z"/>',
    'document' => '<path d="M13.4 3.6H6.8A1.8 1.8 0 0 0 5 5.4v13.2a1.8 1.8 0 0 0 1.8 1.8h10.4a1.8 1.8 0 0 0 1.8-1.8V9.2L13.4 3.6Z"/><path d="M13.2 3.7v5.5h5.4"/><path d="M8.6 13.2h6.8M8.6 16.4h4.4"/>',
    'award' => '<circle cx="12" cy="8.8" r="5.4"/><path d="m8.4 13.4-1.2 6.8 4.8-2.6 4.8 2.6-1.2-6.8"/>',
    'mail' => '<rect x="2.8" y="5" width="18.4" height="14" rx="2"/><path d="m3.4 6.9 8.6 6 8.6-6"/>',
    'phone' => '<path d="M8.1 3.8H5.4A2 2 0 0 0 3.4 6c.4 8 6.6 14.2 14.6 14.6a2 2 0 0 0 2-2v-2.7a1.5 1.5 0 0 0-1.2-1.5l-3.3-.6a1.5 1.5 0 0 0-1.5.6l-.8 1.3a12.8 12.8 0 0 1-5.3-5.3l1.3-.8a1.5 1.5 0 0 0 .6-1.5l-.6-3.3a1.5 1.5 0 0 0-1.5-1.2Z"/>',
    'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 9.9h17M8.2 3.2v3.6M15.8 3.2v3.6"/>',
    'grid' => '<rect x="3.5" y="3.5" width="17" height="17" rx="2"/><path d="M9.2 3.5v17M14.8 3.5v17M3.5 9.2h17M3.5 14.8h17"/>',
    'sun' => '<circle cx="12" cy="12" r="4.2"/><path d="M12 2.8v2.4M12 18.8v2.4M2.8 12h2.4M18.8 12h2.4M5.5 5.5l1.7 1.7M16.8 16.8l1.7 1.7M18.5 5.5l-1.7 1.7M7.2 16.8l-1.7 1.7"/>',
    'moon' => '<path d="M20 14.2A8.4 8.4 0 0 1 9.8 4 8.4 8.4 0 1 0 20 14.2Z"/>',
];

/**
 * Render an icon.
 *
 * @param string $name  Key from the ICONS map above.
 * @param int    $size  Pixel size for both dimensions.
 * @param string $class Extra classes on the <svg> element.
 */
function icon(string $name, int $size = 16, string $class = ''): string
{
    if (!isset(ICONS[$name])) {
        return '';
    }

    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '')
        . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none"'
        . ' stroke="currentColor" stroke-width="1.5" stroke-linecap="round"'
        . ' stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . ICONS[$name] . '</svg>';
}
