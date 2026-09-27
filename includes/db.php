<?php
/**
 * Thin wrapper around mysqli.
 *
 * Every query in this application goes through these functions, which means
 * every query is a prepared statement with bound parameters. Values are
 * never concatenated into SQL, so the application is not exposed to SQL
 * injection.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Prepare, bind and execute a statement.
 *
 * @param string $sql    SQL with `?` placeholders.
 * @param string $types  mysqli bind types, e.g. 'ssi'. Use '' for no params.
 * @param array  $params Values to bind, in placeholder order.
 * @return mysqli_stmt  The executed statement; call get_result() or affected_rows on it.
 */
function db_stmt(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);

    if ($stmt === false) {
        throw new RuntimeException('Could not prepare statement: ' . db()->error);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    return $stmt;
}

/**
 * Run a SELECT and return every row.
 *
 * @return array<int,array<string,mixed>>
 */
function db_all(string $sql, string $types = '', array $params = []): array
{
    $stmt  = db_stmt($sql, $types, $params);
    $result = $stmt->get_result();
    $rows   = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

/**
 * Run a SELECT and return the first row, or null when there is none.
 *
 * @return array<string,mixed>|null
 */
function db_row(string $sql, string $types = '', array $params = []): ?array
{
    $stmt   = db_stmt($sql, $types, $params);
    $result = $stmt->get_result();
    $row    = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

/**
 * Run a SELECT and return a single value from the first row.
 *
 * @return mixed|null
 */
function db_value(string $sql, string $types = '', array $params = [])
{
    $stmt   = db_stmt($sql, $types, $params);
    $result = $stmt->get_result();
    $row    = $result ? $result->fetch_row() : null;
    $stmt->close();

    return $row ? $row[0] : null;
}

/**
 * Run an INSERT, UPDATE or DELETE.
 *
 * @return int Number of affected rows.
 */
function db_execute(string $sql, string $types = '', array $params = []): int
{
    $stmt = db_stmt($sql, $types, $params);
    $rows = $stmt->affected_rows;
    $stmt->close();

    return max(0, (int) $rows);
}

/**
 * Run an INSERT and return the new primary key.
 */
function db_insert(string $sql, string $types = '', array $params = []): int
{
    $stmt = db_stmt($sql, $types, $params);
    $stmt->close();

    return (int) db()->insert_id;
}

/**
 * Does a row already exist matching these conditions?
 *
 * Handy for the "prevent duplicates" checks that mirror a UNIQUE index.
 *
 * @param array<int,mixed> $params
 */
function db_exists(string $sql, string $types, array $params): bool
{
    return db_value($sql, $types, $params) !== null;
}

/**
 * Count matching rows.
 *
 * Used for list totals, dashboard figures and the `exists:` / `unique:`
 * validation rules. The table name cannot be bound as a parameter, so it is
 * reduced to letters and underscores first; every value in $where is bound as
 * usual.
 */
function table_count(string $table, string $where = '', string $types = '', array $params = []): int
{
    $safe = preg_replace('/[^A-Za-z_]/', '', $table);

    if ($safe === '' || $safe === null) {
        throw new InvalidArgumentException('Invalid table name: ' . $table);
    }

    $sql = 'SELECT COUNT(*) AS `n` FROM `' . $safe . '`';

    if ($where !== '') {
        $sql .= ' WHERE ' . $where;
    }

    $row = db_row($sql, $types, $params);

    return (int) ($row['n'] ?? 0);
}

function db_begin(): void
{
    db()->begin_transaction();
}

function db_commit(): void
{
    db()->commit();
}

function db_rollback(): void
{
    db()->rollback();
}

/**
 * Translate a mysqli driver error number into something a person can act on.
 *
 * The three we actually hit in this application are:
 *   1062 duplicate entry, 1451/1452 foreign key constraint, 3819 check violated.
 */
function db_error_is(array $errorCodes, int $errno): bool
{
    return in_array($errno, $errorCodes, true);
}
