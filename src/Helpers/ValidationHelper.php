<?php

class ValidationHelper {
    public static function validateRequired($fields, $data) {
        $errors = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = ucfirst($field) . " is required.";
            }
        }
        return $errors;
    }
}
?>
