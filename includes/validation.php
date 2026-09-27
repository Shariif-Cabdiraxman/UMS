<?php
/**
 * Input validation.
 *
 * Every module declares the rules for its form as an array and calls
 * validate(). Keeping the rules as data means they can be read in one place
 * next to the field names, and it means the same rule is enforced the same way
 * in nine different modules.
 *
 * A rule string looks like:
 *
 *     'required|max:60'
 *     'email|unique:students,email,{id}'
 *     'exists:departments,id'
 *     'in:Semester 1,Semester 2'
 *     'regex:/^[A-Z]{2}\d{4}$/'
 *
 * {id} in a unique: rule is replaced with the id of the record being edited,
 * so a record never collides with itself.
 *
 * Rules
 *   required     not empty after trimming
 *   nullable     allows an empty value; without it, empty is an error
 *   min:N        string length or numeric value at least N
 *   max:N        string length or numeric value at most N
 *   between:A,B  numeric value within an inclusive range
 *   int          whole number
 *   numeric      any number
 *   email        looks like an email address
 *   date        parses as a date
 *   past        a date in the past (used for dates of birth)
 *   future       a date in the future
 *   in:A,B,C     one of a fixed set of values
 *   exists:t,c   a row with that value exists in the table/column
 *   unique:t,c   no other row has that value in the table/column
 *   regex:/p/    matches a pattern
 */

declare(strict_types=1);

/**
 * @param array<string,string> $input  Field name => submitted value.
 * @param array<string,string> $rules  Field name => pipe-separated rules.
 * @param array<string,string> $labels Field name => human label, for messages.
 * @return array<string,string> Field name => first error message.
 */
function validate(array $input, array $rules, array $labels = []): array
{
    $errors = [];
    $id     = $input['id'] ?? null;

    foreach ($rules as $field => $ruleList) {
        $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $value = $input[$field] ?? null;
        $value = is_string($value) ? trim($value) : $value;

        $rulesForField = array_filter(explode('|', $ruleList));

        // A field with no value: only `required` objects.
        $isEmpty = $value === null || $value === '' || (is_array($value) && $value === []);

        if ($isEmpty) {
            foreach ($rulesForField as $rule) {
                if ($rule === 'required') {
                    $errors[$field] = $label . ' is required.';
                }
            }
            continue;
        }

        foreach ($rulesForField as $rule) {
            if (isset($errors[$field])) {
                break;
            }

            [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);

            // {id} lets a unique: rule ignore the row being edited.
            if ($argument !== null && strpos($argument, '{id}') !== false) {
                $argument = str_replace('{id}', (string) $id, $argument);
            }

            $error = apply_rule((string) $name, $argument, $value, $label, $field);
            if ($error !== null) {
                $errors[$field] = $error;
            }
        }
    }

    return $errors;
}

/**
 * Check one rule.
 *
 * @return string|null An error message, or null when the value passes.
 */
function apply_rule(string $name, ?string $argument, $value, string $label, string $field): ?string
{
    switch ($name) {
        case 'required':
            // Empty values never reach this function: validate() reports the
            // "is required" error before it gets here. Getting this far means
            // the field was filled in, so the rule is satisfied.
            return null;

        case 'nullable':
            return null;

        case 'min':
            $n = (float) $argument;
            if (is_numeric($value)) {
                return $value < $n ? $label . ' must be at least ' . $argument . '.' : null;
            }
            return mb_strlen((string) $value) < (int) $argument
                ? $label . ' must be at least ' . $argument . ' characters.'
                : null;

        case 'max':
            $n = (float) $argument;
            if (is_numeric($value)) {
                return $value > $n ? $label . ' may not be greater than ' . $argument . '.' : null;
            }
            return mb_strlen((string) $value) > (int) $argument
                ? $label . ' may not be longer than ' . $argument . ' characters.'
                : null;

        case 'between':
            [$low, $high] = array_pad(explode(',', (string) $argument), 2, '0');
            $number = (float) $value;
            return ($number < (float) $low || $number > (float) $high)
                ? $label . ' must be between ' . $low . ' and ' . $high . '.'
                : null;

        case 'int':
            return filter_var($value, FILTER_VALIDATE_INT) === false
                ? $label . ' must be a whole number.'
                : null;

        case 'numeric':
            return is_numeric($value) ? null : $label . ' must be a number.';

        case 'email':
            return filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? 'Enter a valid email address.'
                : null;

        case 'date':
            return validate_date_string((string) $value) === null
                ? $label . ' must be a valid date.'
                : null;

        case 'past':
            return strtotime((string) $value) > time()
                ? $label . ' must be a date in the past.'
                : null;

        case 'future':
            return strtotime((string) $value) < time()
                ? $label . ' must be a date in the future.'
                : null;

        case 'in':
            $allowed = explode(',', (string) $argument);
            return in_array((string) $value, $allowed, true)
                ? null
                : $label . ' must be one of: ' . implode(', ', $allowed) . '.';

        case 'exists':
            [$table, $column] = array_pad(explode(',', (string) $argument), 2, 'id');
            return table_count(safe_identifier($table), '`' . safe_identifier($column) . '` = ?', 'i', [(int) $value]) > 0
                ? null
                : 'The selected ' . strtolower($label) . ' does not exist.';

        case 'unique':
            [$table, $column, $exceptId] = array_pad(explode(',', (string) $argument), 3, null);
            $where  = '`' . safe_identifier($column) . '` = ?';
            $params = [(string) $value];
            $types  = 's';

            if ($exceptId !== null && $exceptId !== '' && $exceptId !== '0') {
                $where   .= ' AND `id` <> ?';
                $params[] = (int) $exceptId;
                $types   .= 'i';
            }

            return table_count(safe_identifier($table), $where, $types, $params) > 0
                ? 'That ' . strtolower($label) . ' is already in use.'
                : null;

        case 'regex':
            $pattern = (string) $argument;
            return @preg_match($pattern, (string) $value) === 1
                ? null
                : $label . ' is not in the expected format.';

        case 'matches':
            $pattern = (string) $argument;
            return @preg_match($pattern, (string) $value) === 1
                ? null
                : $label . ' is not in the expected format.';

        default:
            // An unknown rule is a programming mistake, not bad input, so it
            // is logged rather than shown to the visitor.
            error_log('[hagmah] unknown validation rule: ' . $name);

            return null;
    }
}

/**
 * Reject anything that is not a plain SQL identifier.
 *
 * Table and column names cannot be bound as parameters, so wherever a rule
 * brings one from the rule string it is filtered down to letters and
 * underscores first. A rule string is written in code, never taken from a
 * request, so this is a second line of defence rather than the main one.
 */
function safe_identifier(string $name): string
{
    $clean = preg_replace('/[^A-Za-z_]/', '', $name);

    if ($clean === '' || $clean === null) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }

    return $clean;
}

/**
 * True when a string is a date PHP can actually interpret.
 *
 * is_string() on a date column happily passes '0000-00-00' and '32 Jan 2024'
 * through to the database, so the value is checked properly first.
 */
function validate_date_string(string $value): ?string
{
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1) {
        return null;
    }

    if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return null;
    }

    return $value;
}

/**
 * Read a form into a plain array, casting the fields that are numbers.
 *
 * Keeping the cast in one place means a module never has to remember that
 * a foreign key arrives as a string and has to be an int before it is bound.
 */
function collect_input(array $intFields = [], array $floatFields = []): array
{
    $data = [];

    foreach ($_POST as $key => $value) {
        if ($key === 'csrf_token' || $key === 'action') {
            continue;
        }
        $data[$key] = is_array($value) ? $value : trim((string) $value);
    }

    foreach ($intFields as $field) {
        if (array_key_exists($field, $data) && $data[$field] !== '') {
            $data[$field] = (int) $data[$field];
        } elseif (array_key_exists($field, $data)) {
            $data[$field] = '';
        }
    }

    foreach ($floatFields as $field) {
        if (array_key_exists($field, $data) && $data[$field] !== '') {
            $data[$field] = (float) $data[$field];
        }
    }

    return $data;
}
