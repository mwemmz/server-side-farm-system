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
}
?>
