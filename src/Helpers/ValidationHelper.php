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
}
?>
