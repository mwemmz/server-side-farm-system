<?php

class ValidationHelper {
    public static function validateRequired($fields, $data) {
        $errors = [];
        foreach ($fields as $field) {
            $v = $data[$field] ?? null;
            if ($v === null || trim((string) $v) === '') {
                $label = ucfirst(str_replace('_', ' ', preg_replace('/_id$/', '', $field)));
                $errors[$field] = $label . " is required.";
            }
        }
        return $errors;
    }

    /**
     * Validate a single numeric field. Returns an error string, or null when valid.
     * Empty values pass (use validateRequired for presence). Options:
     *   label, min, max, integer (bool), positive (bool)
     */
    public static function number($data, $field, $opts = []) {
        $label = $opts['label'] ?? ucfirst(str_replace('_', ' ', $field));
        $v = $data[$field] ?? null;
        if ($v === null || trim((string) $v) === '') {
            return null;
        }
        if (!is_numeric($v)) {
            return $label . " must be a number.";
        }
        $f = (float) $v;
        if (!empty($opts['integer']) && (float) (int) $f !== $f) {
            return $label . " must be a whole number.";
        }
        if (!empty($opts['positive']) && $f <= 0) {
            return $label . " must be greater than 0.";
        }
        if (isset($opts['min']) && $f < (float) $opts['min']) {
            return $label . " must be at least " . $opts['min'] . ".";
        }
        if (isset($opts['max']) && $f > (float) $opts['max']) {
            return $label . " must be at most " . $opts['max'] . ".";
        }
        return null;
    }

    /**
     * Validate that a submitted foreign-key id refers to an existing row.
     * Returns an error string, or null when valid. Empty values pass.
     */
    public static function exists($pdo, $table, $id, $label = null) {
        $label = $label ?? ucfirst(str_replace('_', ' ', $table));
        $id = trim((string) $id);
        if ($id === '') {
            return null;
        }
        if (!ctype_digit($id)) {
            return "Please select a valid {$label}.";
        }
        $allowed = ['farms', 'crops', 'suppliers', 'fields', 'livestock'];
        if (!in_array($table, $allowed, true)) {
            return null;
        }
        $st = $pdo->prepare("SELECT 1 FROM {$table} WHERE id = ?");
        $st->execute([(int) $id]);
        return $st->fetchColumn() ? null : "The selected {$label} no longer exists.";
    }

    /** True when $v is numeric and within [$min,$max] (either bound optional). */
    public static function numeric($v, $min = null, $max = null) {
        if ($v === null || trim((string) $v) === '' || !is_numeric($v)) return false;
        $f = (float) $v;
        if ($min !== null && $f < (float) $min) return false;
        if ($max !== null && $f > (float) $max) return false;
        return true;
    }

    /**
     * Coerce a submitted id to a safe positive integer, or null when invalid.
     * Rejects 0, negatives, floats, values over INT_MAX, arrays and non-digits,
     * so callers can never feed raw (int) casts of hostile input to the DB.
     */
    public static function id($v) {
        if (!is_scalar($v)) return null;
        $s = trim((string) $v);
        if ($s === '' || !ctype_digit($s)) return null;
        if (strlen($s) > 10) return null; // > 2147483647 would overflow an integer id column
        $n = (int) $s;
        return $n >= 1 ? $n : null;
    }

    /** True when $v is one of the allowed values (loose string compare). */
    public static function inList($v, $allowed) {
        foreach ($allowed as $a) {
            if ((string) $a === (string) $v) return true;
        }
        return false;
    }

    /** True when the id refers to an existing row (also guards the table name). */
    public static function existsIn($pdo, $table, $id) {
        $allowed = ['farms', 'fields', 'crops', 'livestock', 'suppliers', 'employees',
                    'equipment', 'inventory', 'storage_records', 'users', 'departments'];
        if (!in_array($table, $allowed, true)) return false;
        if (!ctype_digit(trim((string) $id))) return false;
        $st = $pdo->prepare("SELECT 1 FROM {$table} WHERE id = ?");
        $st->execute([(int) $id]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Guard a "something that already happened" date.
     *
     * Two call styles are supported:
     *   dateNotFuture($value)                     -> bool  (true = empty or not in the future)
     *   dateNotFuture($data, $field, $label = null) -> string|null (error message, or null when OK)
     * Empty values pass in both styles; use validateRequired for presence.
     */
    public static function dateNotFuture($a, $b = null, $label = null) {
        if (is_array($a)) {
            $data  = $a;
            $field = (string) $b;
            $label = $label ?? ucfirst(str_replace('_', ' ', $field));
            $v = $data[$field] ?? null;
            if ($v === null || trim((string) $v) === '') return null;
            $ts = strtotime((string) $v);
            if ($ts === false) return $label . " is not a valid date.";
            if ($ts > strtotime('today 23:59:59')) return $label . " cannot be in the future.";
            return null;
        }
        $v = $a;
        if ($v === null || trim((string) $v) === '') return true;
        $ts = strtotime((string) $v);
        if ($ts === false) return false;
        return $ts <= strtotime('today 23:59:59');
    }
}
?>
