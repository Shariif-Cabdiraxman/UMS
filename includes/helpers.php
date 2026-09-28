<?php
/**
 * Small utility functions used throughout the application.
 *
 * Grouped by purpose:
 *   - output escaping
 *   - URLs and redirects
 *   - flash messages
 *   - reading form input
 *   - formatting (dates, numbers, text)
 *   - status vocabulary shared by every badge in the app
 *   - pagination
 */

declare(strict_types=1);

// =====================================================================
// Output escaping
// =====================================================================

/**
 * Escape a value for safe output inside HTML.
 *
 * Every piece of data that reaches the page goes through this. Using it is
 * what stops a value containing <script> from ever executing.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape a value for use inside a JavaScript string or a data attribute. */
function ejs($value): string
{
    return htmlspecialchars(
        json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?: 'null',
        ENT_QUOTES,
        'UTF-8'
    );
}

// =====================================================================
// URLs and redirects
// =====================================================================

/** Build an absolute URL for a path inside the application. */
function url(string $path = ''): string
{
    if ($path === '') {
        return BASE_URL;
    }

    return BASE_URL . ltrim($path, '/');
}

/** Send the visitor somewhere else and stop. */
function redirect(string $path, int $status = 302): void
{
    $target = (preg_match('#^https?://#i', $path) === 1) ? $path : url($path);
    header('Location: ' . $target, true, $status);
    exit;
}

/**
 * Current query string with some values replaced or removed.
 *
 * Passing null for a key removes it. This is what every sort link, filter
 * form and pagination link uses, so the state of a list survives navigation
 * and can be bookmarked or shared.
 */
function with_query(array $overrides): string
{
    $params = $_GET;

    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    $query = http_build_query($params);

    return $query === '' ? '' : '?' . $query;
}

/** Echo an escaped value only when it matches, for use on form controls. */
function selected($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

// =====================================================================
// Flash messages
//
// A message set on one request and shown on the next. Used to confirm that
// a save or delete worked.
// =====================================================================

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Read and clear all pending flash messages. */
function flash_take(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

// =====================================================================
// Form input
// =====================================================================

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Read a trimmed string from POST.
 *
 * @return string|null Null when the field was not submitted at all, which
 *                     is different from being submitted empty.
 */
function input(string $key, ?string $default = null): ?string
{
    if (!array_key_exists($key, $_POST)) {
        return $default;
    }

    if (is_array($_POST[$key])) {
        return $default;
    }

    $value = trim((string) $_POST[$key]);

    return $value === '' ? ($default === null ? null : '') : $value;
}

/** Read an integer from POST. */
function input_int(string $key, ?int $default = null): ?int
{
    $value = input($key);

    return ($value === null || $value === '') ? $default : (int) $value;
}

/** Read an array of integers from POST, e.g. a multi-select of checkboxes. */
function input_int_array(string $key): array
{
    if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
        return [];
    }

    return array_values(array_unique(array_map('intval', $_POST[$key])));
}

/**
 * The validation errors and re-populated values for the current request.
 *
 * redirect_back_with_errors() parks them in the session; the next request
 * reads them once and they are gone. Reading consumes them, so a refresh of
 * a form that failed validation shows the form again rather than replaying
 * the old errors, and a page that never looks at them does not carry them
 * forward to the one after it.
 *
 * A reference is returned so the cache can be emptied in place.
 */
function &form_state(): array
{
    static $state = null;

    if ($state === null) {
        $state = [
            'errors' => $_SESSION['_errors'] ?? [],
            'old'    => $_SESSION['_old'] ?? [],
        ];

        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    return $state;
}

/** Validation errors for the current request. */
function errors(): array
{
    return form_state()['errors'];
}

function error_for(string $field): ?string
{
    $errors = errors();

    return $errors[$field] ?? null;
}

function has_errors(): bool
{
    return errors() !== [];
}

/** Put a value back into a field after a validation failure. */
function old(string $key, $default = '')
{
    $state = form_state();

    return $state['old'][$key] ?? $default;
}

/**
 * Stash form values and errors for the next request, then bounce back.
 *
 * Validation runs inline, and on failure the person is sent back to the form
 * they were filling in with their input intact rather than onto a blank page.
 *
 * Secrets are stripped centrally rather than by each form, because the values
 * travel through the session and are written back into the HTML. A password
 * that failed validation would otherwise be rendered back into the markup,
 * where it would sit in the page source and in the browser's form history.
 */
function redirect_back_with_errors(string $path, array $errors, array $old, string $anchor = ''): void
{
    $_SESSION['_errors'] = $errors;
    $_SESSION['_old']    = $old;
    unset($_SESSION['_old']['csrf_token'], $_SESSION['_old']['id']);

    foreach (array_keys($_SESSION['_old']) as $key) {
        if (strpos(strtolower($key), 'password') !== false || strpos(strtolower($key), 'confirm') !== false) {
            unset($_SESSION['_old'][$key]);
        }
    }

    redirect($path . ($anchor !== '' ? '#' . $anchor : ''));
}

/** Drop anything left in the session copy. Rarely needed. */
function clear_form_state(): void
{
    $state = &form_state();

    $state['errors'] = [];
    $state['old']    = [];

    unset($_SESSION['_errors'], $_SESSION['_old']);
}

// =====================================================================
// Formatting
// =====================================================================

function format_date(?string $date, string $format = 'j M Y'): string
{
    if ($date === null || $date === '' || strpos($date, '0000-00-00') === 0) {
        return '—';
    }

    $timestamp = strtotime($date);

    return $timestamp === false ? '—' : date($format, $timestamp);
}

function format_datetime(?string $value, string $format = 'j M Y, H:i'): string
{
    return format_date($value, $format);
}

/** "2026-09-27" -> "27 Sep 2026" */
function format_db_date(?string $date): string
{
    return format_date($date, 'j M Y');
}

function format_number($number, int $decimals = 0): string
{
    return number_format((float) $number, $decimals);
}

/** Trim a long string and add an ellipsis. */
function truncate(?string $text, int $length = 80): string
{
    $text = (string) $text;

    if (function_exists('mb_strlen') && mb_strlen($text) > $length) {
        return mb_substr($text, 0, $length - 1) . '…';
    }

    return strlen($text) > $length ? substr($text, 0, $length - 1) . '…' : $text;
}

/** "on_leave" -> "On leave" */
function humanize(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $value = str_replace(['_', '-'], ' ', $value);

    return ucfirst($value);
}

/** Two-letter monogram used in place of a photograph. */
function initials(?string $first, ?string $last): string
{
    $a = $first !== null && $first !== '' ? mb_substr($first, 0, 1) : '';
    $b = $last !== null && $last !== '' ? mb_substr($last, 0, 1) : '';

    return mb_strtoupper($a . $b) ?: '—';
}

/** "Aishath Rasheed" from separate name columns. */
function full_name(?string $first, ?string $last): string
{
    return trim(($first ?? '') . ' ' . ($last ?? '')) ?: '—';
}

// =====================================================================
// Grading scale
// =====================================================================

/**
 * Convert marks into a letter grade.
 *
 * 90-100 = A, 80-89 = B, 70-79 = C, 60-69 = D, below 60 = F.
 * The same thresholds are used by the application and documented in
 * database.sql; the database stores the resulting letter so it can be
 * filtered and charted in SQL.
 */
function grade_letter(float $marks): string
{
    if ($marks >= 90) return 'A';
    if ($marks >= 80) return 'B';
    if ($marks >= 70) return 'C';
    if ($marks >= 60) return 'D';

    return 'F';
}

function format_marks($marks): string
{
    return number_format((float) $marks, 2);
}

// =====================================================================
// Status vocabulary
//
// One registry for the whole application. A status that reads as "green"
// in one module must not read as "amber" in another, otherwise people
// have to reconcile the two every time they move between screens.
// =====================================================================

const STATUS_TONES = [
    // students
    'active'    => 'ok',
    'inactive'  => 'neutral',
    'graduated' => 'accent',
    'suspended' => 'warn',
    // lecturers
    'on_leave'  => 'warn',
    // enrollments
    'enrolled'  => 'info',
    'completed' => 'ok',
    'dropped'   => 'neutral',
    'failed'    => 'danger',
    // announcements
    'published' => 'ok',
    'draft'     => 'warn',
    'archived'  => 'neutral',
    // users
    'admin'     => 'accent',
    'registrar' => 'info',
    // neutral entries
    'male'   => 'neutral',
    'female' => 'neutral',
    'other'  => 'neutral',
];

function status_tone(?string $status): string
{
    return STATUS_TONES[$status ?? ''] ?? 'neutral';
}

/** Escape and humanise a status value. */
function status_label(?string $status): string
{
    return e(humanize($status));
}

/**
 * Render a status badge.
 *
 * The tone drives colour, and each tone also carries a different small
 * mark, so status is never communicated by colour alone.
 */
function badge(?string $status, ?string $overrideLabel = null): string
{
    if ($status === null || $status === '') {
        return '<span class="badge badge--neutral"><span class="badge__mark"></span>—</span>';
    }

    $tone = status_tone($status);
    $label = $overrideLabel ?? humanize($status);

    return '<span class="badge badge--' . $tone . '">'
        . '<span class="badge__mark" aria-hidden="true"></span>'
        . e($label) . '</span>';
}

/** Grade badges are coloured by band, which is the one place colour is load-bearing. */
function grade_badge(?string $letter): string
{
    $letter = ($letter ?? '') !== '' ? $letter : '—';
    $tone = match ($letter) {
        'A' => 'ok',
        'B' => 'info',
        'C' => 'warn',
        'D' => 'warn',
        'F' => 'danger',
        default => 'neutral',
    };

    return '<span class="badge badge--' . $tone . '"><span class="badge__mark" aria-hidden="true"></span>'
        . e($letter) . '</span>';
}

/** User roles as badges, because "Admin" and "Registrar" are more than statuses. */
function role_badge(?string $role): string
{
    if ($role === 'admin') {
        return badge('admin', 'Administrator');
    }

    if ($role === 'registrar') {
        return badge('registrar', 'Registrar');
    }

    return badge($role);
}

// =====================================================================
// Shared vocabularies
// =====================================================================

function semesters(): array
{
    return ['Semester 1', 'Semester 2'];
}

/** Academic years as YYYY/YYYY, newest first. */
function academic_years(int $from = 2019): array
{
    $last = (int) date('Y') + 1;
    $years = [];

    for ($year = $last; $year >= $from; $year--) {
        $years[] = $year . '/' . ($year + 1);
    }

    return $years;
}

function enrollment_years(int $from = 2019): array
{
    $last = (int) date('Y') + 1;
    $years = [];

    for ($year = $last; $year >= $from; $year--) {
        $years[] = (string) $year;
    }

    return $years;
}

/**
 * The academic year that is running right now.
 *
 * The teaching year is taken to begin in August, so the year turns over in
 * August rather than in January. Months are zero-indexed by PHP, so August
 * is month 7.
 */
function current_academic_year(): string
{
    $year = (int) date('Y');

    return date('n') >= 8 ? $year . '/' . ($year + 1) : ($year - 1) . '/' . $year;
}

/** A best guess at which semester is in progress, used to focus the dashboard. */
function current_semester(): string
{
    return date('n') >= 8 ? 'Semester 1' : 'Semester 2';
}

/**
 * Render <option> elements for a select.
 *
 * @param array<int|string,string> $options Value => label, or a flat list.
 */
function options(array $options, $selectedValue = null, string $placeholder = ''): string
{
    $html = '';

    if ($placeholder !== '') {
        $html .= '<option value=""' . selected($selectedValue, '') . '>' . e($placeholder) . '</option>';
    }

    foreach ($options as $value => $label) {
        if (is_int($value)) {
            $value = $label;
        }
        $html .= '<option value="' . e((string) $value) . '"' . selected($selectedValue, $value) . '>'
            . e((string) $label) . '</option>';
    }

    return $html;
}

// =====================================================================
// Pagination
// =====================================================================

/**
 * Work out the numbers for one page of results.
 *
 * @return array{total:int,per_page:int,page:int,pages:int,offset:int,from:int,to:int}
 */
function paginate(int $total, int $page, int $perPage): array
{
    $perPage = max(1, $perPage);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = max(1, min($page, $pages));
    $offset  = ($page - 1) * $perPage;

    return [
        'total'    => $total,
        'per_page' => $perPage,
        'page'     => $page,
        'pages'    => $pages,
        'offset'   => $offset,
        'from'     => $total === 0 ? 0 : $offset + 1,
        'to'       => min($total, $offset + $perPage),
    ];
}

/** Page sizes offered in the list toolbar. */
function page_size_options(): array
{
    return array_map('intval', explode(',', PAGE_SIZE_OPTIONS));
}

// =====================================================================
// Time
// =====================================================================

/** "3 hours ago" style relative time, used in activity ledgers. */
function time_ago(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '—';
    }

    $seconds = time() - $timestamp;

    if ($seconds < 60)     return 'just now';
    if ($seconds < 3600)   return floor($seconds / 60) . ' min ago';
    if ($seconds < 86400)  return floor($seconds / 3600) . ' hr ago';
    if ($seconds < 604800) return floor($seconds / 86400) . ' d ago';

    return date('j M Y', $timestamp);
}

/** Age in whole years from a date of birth. */
function age_from(?string $dateOfBirth): ?int
{
    if ($dateOfBirth === null || $dateOfBirth === '') {
        return null;
    }

    $dob = date_create($dateOfBirth);
    if ($dob === false) {
        return null;
    }

    $now = new DateTimeImmutable('today');

    return (int) $dob->diff($now)->y;
}
