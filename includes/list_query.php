<?php
/**
 * Shared machinery for the record lists.
 *
 * Every module screen is a list, and they all need the same four things:
 * a search box, filters, sortable columns, and pagination. Building that once
 * here means a new module is a query plus a table rather than a whole page.
 *
 * Sorting is the one place where user input reaches SQL as a column name, so
 * it is resolved through an explicit whitelist of columns. The value from the
 * URL is looked up in that list and the SQL fragment is rebuilt from the
 * matched name; the raw string is never concatenated into the query.
 */

declare(strict_types=1);

require_once __DIR__ . '/charts.php';

// =====================================================================
// Reading list state from the URL
// =====================================================================

function query_string(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function query_int(string $key, int $default = 0): int
{
    $value = $_GET[$key] ?? $default;

    return is_scalar($value) ? (int) $value : $default;
}

/** A multi-select filter such as faculty[]=1&faculty[]=2. */
function query_int_array(string $key): array
{
    if (!isset($_GET[$key])) {
        return [];
    }

    if (!is_array($_GET[$key])) {
        $single = is_scalar($_GET[$key]) ? [(int) $_GET[$key]] : [];
        return array_values(array_unique($single));
    }

    return array_values(array_unique(array_map('intval', $_GET[$key])));
}

/** Read a per-page override, falling back to the configured default. */
function current_page_size(): int
{
    $allowed = page_size_options();
    $size = query_int('per_page', PAGE_SIZE_DEFAULT);

    return in_array($size, $allowed, true) ? $size : PAGE_SIZE_DEFAULT;
}

function current_page_number(): int
{
    return max(1, query_int('page', 1));
}

// =====================================================================
// Sorting
// =====================================================================

/**
 * Work out which column a list is sorted by.
 *
 * @param array<string,string> $allowed  Whitelist of URL name => SQL column.
 * @param string               $fallback Column used when nothing valid is asked for.
 * @return array{column:string,dir:string,sql:string}
 */
function sort_state(array $allowed, string $fallback): array
{
    $column = query_string('sort');
    $dir    = strtoupper(query_string('dir', 'asc'));

    if (!isset($allowed[$column])) {
        $column = $fallback;
        $dir    = 'asc';
    }

    if ($dir !== 'ASC' && $dir !== 'DESC') {
        $dir = 'asc';
    }

    return [
        'column' => $column,
        'dir'    => $dir,
        // Both halves come from the whitelist, never from the URL string.
        'sql'    => '`'.$allowed[$column].'` '.($dir === 'ASC' ? 'ASC' : 'DESC'),
    ];
}

/**
 * A sortable column header that toggles direction and keeps the current filters.
 */
function th_sort(string $key, string $label, array $state, string $classes = ''): string
{
    $isCurrent = $state['column'] === $key;
    $nextDir   = ($isCurrent && $state['dir'] === 'ASC') ? 'desc' : 'asc';

    $ariaSort = $isCurrent
        ? ($state['dir'] === 'ASC' ? ' aria-sort="ascending"' : ' aria-sort="descending"')
        : '';

    $iconName = !$isCurrent ? 'sort' : ($state['dir'] === 'ASC' ? 'chevron-up' : 'chevron-down');

    $mark = '<span class="th__mark">'.icon($iconName, 13).'</span>';

    return '<th class="' . e($classes) . '"' . $ariaSort . '>'
        . '<a class="th__sort" href="' . e(with_query(['sort' => $key, 'dir' => $nextDir, 'page' => null])) . '">'
        . '<span class="th__label">' . e($label) . '</span>' . $mark
        . '</a></th>';
}

/** A plain, unsortable header cell. */
function th(string $label, string $classes = ''): string
{
    return '<th class="' . e($classes) . '"><span class="th__label">' . e($label) . '</span></th>';
}

// =====================================================================
// Page furniture
// =====================================================================

/**
 * The heading block at the top of every module screen.
 *
 * @param array{eyebrow?:string,title:string,subtitle?:string,actions?:string,meta?:string} $options
 */
function render_page_head(array $options): void
{
    ?>
    <header class="pagehead">
        <div class="pagehead__text">
            <?php if (!empty($options['eyebrow'])): ?>
                <p class="pagehead__eyebrow"><?= e($options['eyebrow']) ?></p>
            <?php endif; ?>
            <h1 class="pagehead__title"><?= e($options['title']) ?></h1>
            <?php if (!empty($options['subtitle'])): ?>
                <p class="pagehead__subtitle"><?= e($options['subtitle']) ?></p>
            <?php endif; ?>
            <?php if (!empty($options['meta'])): ?>
                <p class="pagehead__meta"><?= $options['meta'] ?></p>
            <?php endif; ?>
        </div>
        <?php if (!empty($options['actions'])): ?>
            <div class="pagehead__actions"><?= $options['actions'] ?></div>
        <?php endif; ?>
    </header>
    <?php
}

/**
 * The toolbar above a list: a search box plus whatever filters a module needs.
 *
 * @param array{action:string,placeholder:string,filters?:string,extra?:string,hidden?:array} $options
 */
function render_list_toolbar(array $options): void
{
    ?>
    <form class="toolbar" method="get" action="<?= e($options['action']) ?>" role="search">
        <?php if (!empty($options['hidden'])): ?>
            <?php foreach ($options['hidden'] as $name => $value): ?>
                <input type="hidden" name="<?= e($name) ?>" value="<?= e((string) $value) ?>">
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="toolbar__search">
            <?= icon('search', 16) ?>
            <input
                type="search"
                name="q"
                value="<?= e(query_string('q')) ?>"
                placeholder="<?= e($options['placeholder']) ?>"
                aria-label="<?= e($options['placeholder']) ?>"
                autocomplete="off">
        </div>

        <?php if (!empty($options['filters'])): ?>
            <div class="toolbar__filters"><?= $options['filters'] ?></div>
        <?php endif; ?>

        <div class="toolbar__actions">
            <?php if (!empty($options['extra'])): ?>
                <?= $options['extra'] ?>
            <?php endif; ?>
            <button class="btn btn--default" type="submit"><?= icon('search', 15) ?><span>Search</span></button>
            <?php if (query_string('q') !== '' || count(array_diff(array_keys($_GET), ['page'])) > 0): ?>
                <a class="btn btn--quiet" href="<?= e($options['action']) ?>">Reset</a>
            <?php endif; ?>
        </div>
    </form>
    <?php
}

/**
 * The row count and pagination controls beneath a list.
 *
 * @param array $page Output of paginate().
 */
function render_list_footer(array $page, string $path, array $opts = []): void
{
    $sizes = page_size_options();
    ?>
    <div class="listfoot">
        <p class="listfoot__count">
            <?php if ($page['total'] === 0): ?>
                No records
            <?php else: ?>
                Showing <strong><?= e((string) $page['from']) ?></strong>–<strong><?= e((string) $page['to']) ?></strong>
                of <strong><?= e((string) $page['total']) ?></strong>
                <?= e(strtolower($opts['noun'] ?? 'record')) ?><?= $page['total'] === 1 ? '' : 's' ?>
            <?php endif; ?>
        </p>

        <div class="listfoot__controls">
            <form class="perpage" method="get" action="<?= e($path) ?>">
                <?php foreach ($_GET as $k => $v): ?>
                    <?php if ($k === 'per_page' || $k === 'page' || is_array($v)) continue; ?>
                    <input type="hidden" name="<?= e((string) $k) ?>" value="<?= e((string) $v) ?>">
                <?php endforeach; ?>
                <label for="per_page">Rows</label>
                <select id="per_page" name="per_page" onchange="this.form.submit()">
                    <?= options($sizes, $page['per_page']) ?>
                </select>
                <noscript><button class="btn btn--default btn--sm" type="submit">Apply</button></noscript>
            </form>

            <?php if ($page['pages'] > 1): ?>
                <nav class="pager" aria-label="Pagination">
                    <?= render_pager_links($page, $path) ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/** Numbered pager with the current page centred and ellipses either side. */
function render_pager_links(array $page, string $path): string
{
    $current = $page['page'];
    $pages   = $page['pages'];
    $html    = '';

    $link = function (int $target, string $label, string $classes = '', bool $disabled = false) use ($path): string {
        if ($disabled) {
            return '<span class="pager__item is-disabled">' . $label . '</span>';
        }

        return '<a class="pager__item ' . $classes . '" href="'
            . e($path . with_query(['page' => $target > 1 ? $target : null])) . '">' . $label . '</a>';
    };

    $html .= $link($current - 1, icon('chevron-left', 14), 'pager__arrow', $current === 1);

    // Always show first, last and a window around the current page.
    $window = [];
    for ($i = $current - 1; $i <= $current + 1; $i++) {
        if ($i >= 1 && $i <= $pages) {
            $window[] = $i;
        }
    }

    $numbers = array_values(array_unique(array_merge([1], $window, [$pages])));
    sort($numbers);

    $previous = 0;
    foreach ($numbers as $number) {
        if ($previous > 0 && $number - $previous > 1) {
            $html .= '<span class="pager__gap" aria-hidden="true">…</span>';
        }
        $html .= $link(
            $number,
            (string) $number,
            $number === $current ? 'is-current' : '',
            $number === $current
        );
        $previous = $number;
    }

    $html .= $link($current + 1, icon('chevron-right', 14), 'pager__arrow', $current === $pages);

    return $html;
}

/** Shown when a filtered list has no rows. */
function render_empty_state(string $iconName, string $title, string $message, string $action = ''): void
{
    ?>
    <div class="empty">
        <div class="empty__icon"><?= icon($iconName, 26) ?></div>
        <p class="empty__title"><?= e($title) ?></p>
        <p class="empty__message"><?= e($message) ?></p>
        <?php if ($action !== ''): ?>
            <div class="empty__action"><?= $action ?></div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * The small note shown at the top of a module the signed-in role cannot write to.
 *
 * The permission that governs the module is passed in rather than guessed, so
 * a registrar is told a section is read-only on the screen they are actually
 * looking at.
 */
function render_readonly_notice(string $permission, string $moduleNoun = 'section'): void
{
    if (can($permission)) {
        return;
    }
    ?>
    <p class="notice notice--quiet">
        <?= icon('lock', 15) ?>
        <span>You are signed in as a <?= e(humanize(current_user()['role'])) ?>, so this <?= e($moduleNoun) ?>
        is read-only. An administrator can make changes here.</span>
    </p>
    <?php
}

// =====================================================================
// Inline forms
// =====================================================================

/**
 * A delete control.
 *
 * Deletes go through POST with a CSRF token rather than a link, so they
 * cannot be triggered by a crawler, a prefetch, or a link somebody shares.
 * The `action` field is what the list screen switches on to route the
 * submission to delete_record().
 */
function render_delete_form(string $postTo, int $id, string $label = 'Delete'): string
{
    return '<form class="deletefrom" method="post" action="' . e($postTo) . '">'
        . csrf_field()
        . '<input type="hidden" name="action" value="delete">'
        . '<input type="hidden" name="delete_id" value="' . e((string) $id) . '">'
        . '<button class="iconbtn iconbtn--danger" type="submit"'
        . ' data-confirm="' . e(rtrim($label, ' ?.')) . '? This cannot be undone."'
        . ' title="' . e(rtrim($label, ' ?.')) . '">'
        . icon('trash', 15) . '<span class="sr-only">' . e(rtrim($label, ' ?.')) . '</span></button>'
        . '</form>';
}

/** A small monogram standing in for a photograph. */
function render_avatar(string $first, string $last, string $href = ''): string
{
    $tag = $href !== '' ? 'a' : 'span';
    $attributes = $href !== '' ? ' href="' . e($href) . '"' : '';

    return '<' . $tag . ' class="avatar"' . $attributes . ' aria-hidden="true">'
        . e(initials($first, $last)) . '</' . $tag . '>';
}

// =====================================================================
// Form fields
// =====================================================================

/**
 * Wrap a control in a labelled field, including its error message.
 *
 * @param string $label    Visible label text.
 * @param string $control  The input/select markup.
 * @param array  $options  hint, required, wide, id
 */
function field(string $label, string $control, array $options = []): string
{
    $id = $options['id'] ?? '';
    $error = $id !== '' ? error_for($id) : null;
    $classes = 'field' . (!empty($options['wide']) ? ' field--wide' : '');

    return '<div class="' . $classes . '">'
        . '<label class="field__label" for="' . e($id) . '">' . e($label)
        . (!empty($options['required']) ? '<span class="field__req" aria-hidden="true">*</span>' : '')
        . '</label>'
        . '<div class="field__control' . ($error !== null ? ' is-invalid' : '') . '">' . $control . '</div>'
        . ($error !== null ? '<p class="field__error">' . icon('alert', 13) . e($error) . '</p>' : '')
        . (!empty($options['hint']) && $error === null
            ? '<p class="field__hint">' . e($options['hint']) . '</p>' : '')
        . '</div>';
}

/**
 * Build a labelled input. The common case, kept short at the call site.
 */
function text_field(string $name, string $label, array $options = []): string
{
    $id = $options['id'] ?? $name;
    $type = $options['type'] ?? 'text';
    $value = array_key_exists('value', $options) ? $options['value'] : old($name);

    $attributes = '';
    if (!empty($options['placeholder'])) $attributes .= ' placeholder="' . e($options['placeholder']) . '"';
    if (!empty($options['maxlength']))    $attributes .= ' maxlength="' . (int) $options['maxlength'] . '"';
    if (!empty($options['min']))          $attributes .= ' min="' . e((string) $options['min']) . '"';
    if (!empty($options['max']))          $attributes .= ' max="' . e((string) $options['max']) . '"';
    if (!empty($options['step']))         $attributes .= ' step="' . e((string) $options['step']) . '"';
    if (!empty($options['autocomplete'])) $attributes .= ' autocomplete="' . e($options['autocomplete']) . '"';
    if (!empty($options['inputmode']))    $attributes .= ' inputmode="' . e($options['inputmode']) . '"';
    if (!empty($options['required']))     $attributes .= ' required';
    if (!empty($options['readonly']))     $attributes .= ' readonly';

    return field($label, '<input type="' . e($type) . '" id="' . e($id) . '" name="' . e($name) . '"'
        . ' value="' . e((string) $value) . '"' . $attributes . '>', $options + ['id' => $id]);
}

/**
 * Build a labelled select.
 *
 * @param array $options_list Value => label pairs.
 */
function select_field(string $name, string $label, array $options_list, array $options = []): string
{
    $id = $options['id'] ?? $name;
    $value = array_key_exists('value', $options) ? $options['value'] : old($name);

    $attributes = '';
    if (!empty($options['required']))  $attributes .= ' required';
    if (!empty($options['placeholder'])) {
        $attributes .= ' data-has-placeholder="1"';
    }

    return field($label, '<select id="' . e($id) . '" name="' . e($name) . '"' . $attributes . '>'
        . options($options_list, $value, $options['placeholder'] ?? '')
        . '</select>', $options + ['id' => $id]);
}

/** Build a labelled textarea. */
function textarea_field(string $name, string $label, array $options = []): string
{
    $id = $options['id'] ?? $name;
    $value = array_key_exists('value', $options) ? $options['value'] : old($name);

    $attributes = '';
    if (!empty($options['rows']))     $attributes .= ' rows="' . (int) $options['rows'] . '"';
    if (!empty($options['maxlength'])) $attributes .= ' maxlength="' . (int) $options['maxlength'] . '"';
    if (!empty($options['placeholder'])) $attributes .= ' placeholder="' . e($options['placeholder']) . '"';
    if (!empty($options['required']))  $attributes .= ' required';

    return field($label, '<textarea id="' . e($id) . '" name="' . e($name) . '"' . $attributes . '>'
        . e((string) $value) . '</textarea>', $options + ['id' => $id]);
}

// =====================================================================
// Small shared queries
// =====================================================================

/**
 * Faculty list as name => id, for filter dropdowns and selects.
 *
 * @return array<string,int>
 */
function faculty_options(bool $withAll = false): array
{
    $rows = db_all('SELECT `id`, `name` FROM `faculties` ORDER BY `name`');
    $list = array_column($rows, 'name', 'id');

    return $withAll ? ['' => 'All faculties'] + $list : $list;
}

/** @return array<string,int> */
function department_options($facultyId = null, bool $withAll = false): array
{
    if ($facultyId) {
        $rows = db_all(
            'SELECT `id`, `name` FROM `departments` WHERE `faculty_id` = ? ORDER BY `name`',
            'i',
            [(int) $facultyId]
        );
    } else {
        $rows = db_all('SELECT `id`, `name` FROM `departments` ORDER BY `name`');
    }

    $list = array_column($rows, 'name', 'id');

    return $withAll ? ['' => 'All departments'] + $list : $list;
}

/** @return array<string,int> */
function lecturer_options($departmentId = null, bool $withAll = false): array
{
    if ($departmentId) {
        $rows = db_all(
            'SELECT `id`, CONCAT(`first_name`, \' \', `last_name`) AS `name`
               FROM `lecturers`
              WHERE `department_id` = ?
              ORDER BY `last_name`, `first_name`',
            'i',
            [(int) $departmentId]
        );
    } else {
        $rows = db_all(
            'SELECT `id`, CONCAT(`first_name`, \' \', `last_name`) AS `name`
               FROM `lecturers`
              ORDER BY `last_name`, `first_name`'
        );
    }

    $list = array_column($rows, 'name', 'id');

    return $withAll ? ['' => 'No dean assigned'] + $list : $list;
}

/**
 * Course list as "CODE — Name" => id, for enrollment and grade selects.
 *
 * @return array<string,int>
 */
function course_options($departmentId = null, $semester = null, bool $withAll = false): array
{
    $where  = [];
    $params = [];
    $types  = '';

    if ($departmentId) {
        $where[] = 'c.`department_id` = ?';
        $params[] = (int) $departmentId;
        $types   .= 'i';
    }

    if ($semester !== null) {
        $where[] = 'c.`semester` = ?';
        $params[] = $semester;
        $types   .= 's';
    }

    $rows = db_all(
        'SELECT c.`id`, CONCAT(c.`course_code`, \' — \', c.`course_name`) AS `label`
           FROM `courses` c
          ' . ($where === [] ? '' : 'WHERE ' . implode(' AND ', $where)) . '
          ORDER BY c.`course_code`',
        $types,
        $params
    );

    $list = array_column($rows, 'label', 'id');

    return $withAll ? ['' => 'Select a course'] + $list : $list;
}

/** @return array<string,int> */
function student_options($departmentId = null, bool $withAll = false): array
{
    if ($departmentId) {
        $rows = db_all(
            'SELECT `id`, CONCAT(`first_name`, \' \', `last_name`, \' (\', `student_no`, \')\') AS `name`
               FROM `students`
              WHERE `department_id` = ?
              ORDER BY `last_name`, `first_name`',
            'i',
            [(int) $departmentId]
        );
    } else {
        $rows = db_all(
            'SELECT `id`, CONCAT(`first_name`, \' \', `last_name`, \' (\', `student_no`, \')\') AS `name`
               FROM `students`
              ORDER BY `last_name`, `first_name`'
        );
    }

    $list = array_column($rows, 'name', 'id');

    return $withAll ? ['' => 'Select a student'] + $list : $list;
}

// =====================================================================
// Enum vocabularies
//
// The columns that are ENUMs in the schema, listed once here so a select in
// a form and a filter in a toolbar can never drift apart, and neither can
// fall out of step with the database.
// =====================================================================

function gender_options(): array
{
    return ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];
}

function student_status_options(): array
{
    return ['active' => 'Active', 'inactive' => 'Inactive', 'graduated' => 'Graduated', 'suspended' => 'Suspended'];
}

function lecturer_status_options(): array
{
    return ['active' => 'Active', 'on_leave' => 'On leave', 'inactive' => 'Inactive'];
}

function enrollment_status_options(): array
{
    return ['enrolled' => 'Enrolled', 'completed' => 'Completed', 'dropped' => 'Dropped', 'failed' => 'Failed'];
}

function announcement_status_options(): array
{
    return ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];
}

function role_options(): array
{
    return ['admin' => 'Administrator', 'registrar' => 'Registrar'];
}

function user_status_options(): array
{
    return ['active' => 'Active', 'inactive' => 'Inactive'];
}

/** The assessments a mark can be recorded against. */
function assessment_options(bool $withAll = false): array
{
    $list = [
        'Midterm Examination' => 'Midterm Examination',
        'Final Examination'   => 'Final Examination',
        'Continuous Assessment' => 'Continuous assessment',
        'Practical'           => 'Practical / laboratory',
        'Project'             => 'Project or dissertation',
    ];

    return $withAll ? ['' => 'Select an assessment'] + $list : $list;
}

// =====================================================================
// Enrollments as options
// =====================================================================

/**
 * Enrollments as "<COURSE-CODE> — <student> (<year>, <semester>)" => id.
 *
 * The grade form needs to name the thing a mark belongs to. Listing the
 * course code, the student and the term together is the only way to tell
 * two rows apart at a glance, because the same student appears once per
 * course they take.
 *
 * @return array<string,int>
 */
function enrollment_options(
    $studentId = null,
    $courseId = null,
    $year = null,
    $semester = null,
    bool $withAll = false
): array {
    $where  = [];
    $params = [];
    $types  = '';

    if ($studentId) {
        $where[]  = 'e.`student_id` = ?';
        $params[] = (int) $studentId;
        $types   .= 'i';
    }

    if ($courseId) {
        $where[]  = 'e.`course_id` = ?';
        $params[] = (int) $courseId;
        $types   .= 'i';
    }

    if ($year !== null && $year !== '') {
        $where[]  = 'e.`academic_year` = ?';
        $params[] = (string) $year;
        $types   .= 's';
    }

    if ($semester !== null && $semester !== '') {
        $where[]  = 'e.`semester` = ?';
        $params[] = (string) $semester;
        $types   .= 's';
    }

    $rows = db_all(
        'SELECT e.`id`,
                CONCAT(
                    c.`course_code`, \' — \',
                    s.`first_name`, \' \', s.`last_name`,
                    \' (\', e.`academic_year`, \', \', e.`semester`, \')\'
                ) AS `label`
           FROM `enrollments` e
           JOIN `students` s ON s.`id` = e.`student_id`
           JOIN `courses`  c ON c.`id` = e.`course_id`
           '
        . ($where === [] ? '' : 'WHERE ' . implode(' AND ', $where) . ' ')
        . 'ORDER BY e.`academic_year` DESC, e.`semester` DESC, c.`course_code`, s.`last_name`',
        $types,
        $params
    );

    $list = array_column($rows, 'label', 'id');

    return $withAll ? ['' => 'Select an enrollment'] + $list : $list;
}

// =====================================================================
// Record detail rendering
// =====================================================================

/**
 * A definition list of a record's fields.
 *
 * Detail pages are mostly this, so it is rendered from data: the module
 * supplies a list of term/description pairs and gets consistent markup
 * back, including the two-column default and the single-column variant
 * used for long values.
 *
 * @param array<int,array{term:string,desc:string}> $rows
 */
function render_deflist(array $rows, bool $single = false): void
{
    if ($rows === []) {
        return;
    }
    ?>
    <dl class="deflist<?= $single ? ' deflist--single' : '' ?>">
        <?php foreach ($rows as $row): ?>
            <div>
                <dt class="deflist__term"><?= e($row['term']) ?></dt>
                <dd class="deflist__desc"><?= $row['desc'] ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
    <?php
}

/**
 * A short link-list of the records hanging off the one being viewed.
 *
 * Detail pages are mostly this: the child records of a faculty, a department,
 * a course. Rendering them from data keeps the markup identical everywhere and
 * keeps each view down to the queries and the labels.
 *
 * @param array<int,array{label:string,meta?:string,href:string,trail?:string}> $items
 * @param string $emptyMessage Said when there is nothing linked yet.
 * @param string $iconName     Icon for the empty state.
 */
function render_related_list(array $items, string $emptyMessage, string $iconName = 'document'): void
{
    if ($items === []) {
        render_empty_state($iconName, 'Nothing linked yet', $emptyMessage);

        return;
    }
    ?>
    <ul class="relatedlist">
        <?php foreach ($items as $item): ?>
            <li class="relatedlist__item">
                <a class="relatedlist__link" href="<?= e($item['href']) ?>">
                    <span class="relatedlist__label"><?= e($item['label']) ?></span>
                    <?php if (!empty($item['meta'])): ?>
                        <span class="relatedlist__meta"><?= e($item['meta']) ?></span>
                    <?php endif; ?>
                </a>
                <?php if (!empty($item['trail'])): ?>
                    <span class="relatedlist__trail"><?= $item['trail'] ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}

/**
 * A filter dropdown for the list toolbar.
 *
 * The label is visually hidden but present, so a screen reader announces
 * what the control filters, and the control posts back with the rest of
 * the query string so the other filters survive.
 *
 * @param array<int|string,string> $optionsList
 */
function filter_select(string $name, string $label, array $optionsList, $current, string $anyLabel = 'All'): string
{
    $id = 'filter-' . $name;

    return '<span class="filterfield">'
        . '<label class="sr-only" for="' . e($id) . '">' . e($label) . '</label>'
        . '<select id="' . e($id) . '" name="' . e($name) . '">'
        . '<option value="">' . e($anyLabel) . '</option>'
        . options($optionsList, $current)
        . '</select></span>';
}

/** Find a student row by their reference, which is how a student is addressed in URLs. */
function find_student_by_no(string $studentNo): ?array
{
    return db_row('SELECT * FROM `students` WHERE `student_no` = ? LIMIT 1', 's', [$studentNo]);
}

/**
 * The "back to list" and "edit" buttons shown on a detail page.
 *
 * @param string $listPath Where "back" goes.
 * @param string $editPath Full path to the edit form, or '' when read-only.
 */
function record_actions(string $listPath, string $editPath = ''): string
{
    $html = '<a class="btn btn--quiet" href="' . e(url($listPath)) . '">' . icon('arrow-left', 15)
        . '<span>Back to list</span></a>';

    if ($editPath !== '') {
        $html .= '<a class="btn btn--primary" href="' . e(url($editPath)) . '">'
            . icon('edit', 15) . '<span>Edit</span></a>';
    }

    return $html;
}
