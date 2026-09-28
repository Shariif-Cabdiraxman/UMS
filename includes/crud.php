<?php
/**
 * Shared create / read / update / delete handling.
 *
 * The list, form and view screens of each module are different from one
 * another, but the parts that write to the database are the same. Those live
 * here so that every delete in the application is a CSRF-checked POST that
 * reports a blocked delete in the same words.
 */

declare(strict_types=1);

require_once __DIR__ . '/validation.php';

/**
 * Delete one record, reporting anything that stops it.
 *
 * Deleting something other tables point at is a normal thing to try, not a
 * bug, so a foreign key violation comes back as a message naming the
 * dependent records rather than as an error page.
 *
 * @param string $table   Table to delete from.
 * @param int    $id      Primary key.
 * @param string $noun    What the record is called, for the messages.
 * @param string $backTo  Where to send the visitor afterwards.
 * @param array  $blocked Optional map of errno => [noun, detail SQL].
 */
function delete_record(string $table, int $id, string $noun, string $backTo, array $blocked = []): void
{
    $table = safe_identifier($table);

    $exists = db_value('SELECT `id` FROM `' . $table . '` WHERE `id` = ?', 'i', [$id]);
    if ($exists === null) {
        flash_set('error', 'That ' . strtolower($noun) . ' no longer exists.');
        redirect($backTo);
    }

    // Check the usual dependents first, so the message can be specific.
    foreach ($blocked as $errno => $info) {
        [$childNoun, $detailSql, $detailLabel] = $info;

        $dependents = (int) db_value($detailSql, 'i', [$id]);

        if ($dependents > 0) {
            flash_set(
                'error',
                'This ' . strtolower($noun) . ' cannot be deleted because ' . $dependents . ' '
                . strtolower($childNoun) . ($dependents === 1 ? '' : 's')
                . ($dependents === 1 ? ' still refers to it' : ' still refer to it')
                . ($detailLabel !== '' ? ' (' . $detailLabel . ')' : '') . '. '
                . 'Reassign or remove ' . ($dependents === 1 ? 'it' : 'them') . ' first.'
            );
            redirect($backTo);
        }
    }

    try {
        db_execute('DELETE FROM `' . $table . '` WHERE `id` = ?', 'i', [$id]);
        flash_set('success', ucfirst($noun) . ' deleted.');
    } catch (mysqli_sql_exception $exception) {
        if (db_error_is([1451, 1452], $exception->getCode())) {
            flash_set(
                'error',
                'This ' . strtolower($noun) . ' cannot be deleted because other records still refer to it. '
                . 'Reassign or remove them first.'
            );
        } else {
            throw $exception;
        }
    }

    redirect($backTo);
}

/**
 * The `?` marker a form uses to mean "go back to the list".
 *
 * Keeps the post-save redirect target in one place so the list screen and the
 * form screen cannot drift apart.
 */
function list_path(string $module): string
{
    return $module . '/index.php';
}

/**
 * A short human summary of a record, used in confirmations.
 *
 * @param array $row
 */
function record_label(array $row, array $fields): string
{
    $parts = [];

    foreach ($fields as $field) {
        if (isset($row[$field]) && $row[$field] !== '') {
            $parts[] = (string) $row[$field];
        }
    }

    return $parts === [] ? 'record' : implode(' · ', $parts);
}

/**
 * The standard set of things a form must do before anything is written.
 *
 * Returns false and renders the stop response when the request is not
 * allowed, so a module can call it first and get on with the actual work.
 */
function guard_write(string $permission): void
{
    require_login();

    if (!is_post()) {
        not_found('That form is submitted by POST. Open the form from the list and try again.');
    }

    verify_csrf();
    require_permission($permission);
}

/**
 * Make sure a submitted foreign key points at a row that still exists.
 *
 * The validation layer checks this for the fields it knows about; this is for
 * the ones that are optional, where a blank is fine but a value that points
 * nowhere is not.
 */
function optional_exists(string $table, string $column, $value): bool
{
    if ($value === null || $value === '' || (int) $value === 0) {
        return true;
    }

    return db_value(
        'SELECT `id` FROM `' . safe_identifier($table) . '` WHERE `' . safe_identifier($column) . '` = ?',
        'i',
        [(int) $value]
    ) !== null;
}

/**
 * How many rows a table's string column would allow for a new record.
 *
 * Not a hard limit, just a hint shown next to a field so the person filling
 * the form is not surprised by a database error.
 */
function suggest_reference(string $prefix, int $year, string $table, string $column, int $width = 4): string
{
    $next = (int) db_value(
        'SELECT COALESCE(MAX(CAST(SUBSTRING(`' . safe_identifier($column) . '`, ?) AS UNSIGNED)), 0) + 1
           FROM `' . safe_identifier($table) . '`',
        'si',
        [strlen((string) $prefix) + 1]
    );

    return $prefix . str_pad((string) $next, $width, '0', STR_PAD_LEFT);
}
