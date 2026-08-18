<?php

class FormHelper {
    public static function generateForm($fields, $action, $method = 'POST', $errors = []) {
        $html = "<form action='$action' method='$method' class='space-y-4'>";
        foreach ($fields as $field) {
            $name = $field['name'];
            $label = $field['label'];
            $type = $field['type'] ?? 'text';
            $value = $field['value'] ?? '';
            
            $html .= "<div class='flex flex-col'>";
            $html .= "<label for='$name' class='text-sm font-medium text-gray-700'>$label</label>";
            $html .= "<input type='$type' name='$name' id='$name' value='$value' class='mt-1 p-2 border rounded-md " . (isset($errors[$name]) ? 'border-red-500' : '') . "'>";
            if (isset($errors[$name])) {
                $html .= "<span class='text-red-500 text-xs'>{$errors[$name]}</span>";
            }
            $html .= "</div>";
        }
        $html .= "<button type='submit' class='bg-green-600 text-white p-2 rounded-md'>Submit</button>";
        $html .= "</form>";
        return $html;
    }
}
